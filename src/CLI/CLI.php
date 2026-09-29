<?php

declare(strict_types=1);

namespace Vs\ReLink\CLI;

use WP_CLI;
use Vs\ReLink\Database\Schema;
use Vs\ReLink\Admin\LinkChecker;
use Vs\ReLink\Core\LinkFactory;

/**
 * WP-CLI Commands for VS ReLink.
 */
final class CLI {

	/**
	 * Register the commands.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		WP_CLI::add_command( 'relink check', array( self::class, 'health_check' ) );
		WP_CLI::add_command( 'relink stats', array( self::class, 'show_stats' ) );
		WP_CLI::add_command( 'relink create', array( self::class, 'create_link' ) );
		WP_CLI::add_command( 'relink migrate', array( self::class, 'migrate_legacy' ) );
	}

	/**
	 * Rename VS 1.x lw_* storage IDs to vs_*.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report remaining legacy rows without changing the database.
	 *
	 * ## EXAMPLES
	 *
	 *     wp relink migrate
	 *     wp relink migrate --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public static function migrate_legacy( $args, $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		$result  = \Vs\ReLink\Database\LegacyMigrator::migrate( $dry_run );

		WP_CLI::log( $dry_run ? 'Dry-run — no changes written.' : 'Running storage ID rename…' );
		foreach ( $result['before'] as $key => $count ) {
			$after = (int) ( $result['after'][ $key ] ?? 0 );
			WP_CLI::log( sprintf( '  %s: before=%d after=%d', $key, (int) $count, $after ) );
		}

		$remaining = array_sum( $result['after'] );
		if ( $remaining > 0 ) {
			WP_CLI::warning( sprintf( '%d legacy row(s) still remain.', $remaining ) );
			return;
		}

		WP_CLI::success( $dry_run ? 'No legacy storage IDs detected.' : 'Storage IDs are fully on vs_*.' );
	}

	/**
	 * Run health check for all links.
	 *
	 * ## EXAMPLES
	 *
	 *     wp relink check
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function health_check( $args, $assoc_args ): void {
		unset( $args, $assoc_args );

		$ids   = LinkChecker::get_all_relink_ids();
		$count = count( $ids );

		WP_CLI::log( "Checking $count links..." );

		$success = 0;
		$error   = 0;

		foreach ( $ids as $id ) {
			$result = LinkChecker::check_link( $id );
			if ( 200 === $result['status'] ) {
				++$success;
			} else {
				++$error;
				WP_CLI::warning( "Link #$id [{$result['post_title']}]: Status {$result['status']}" );
			}
		}

		WP_CLI::success( "Check complete. $success healthy, $error issues found." );
	}

	/**
	 * Show quick status overview.
	 *
	 * ## EXAMPLES
	 *
	 *     wp relink stats
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function show_stats( $args, $assoc_args ): void {
		unset( $args, $assoc_args );

		global $wpdb;
		$table = Schema::get_clicks_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- clicks table name comes from Schema.
		$total_clicks = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		$total_links  = wp_count_posts( 'vs_relink' )->publish;

		WP_CLI::line( 'VS ReLink Overview:' );
		WP_CLI::line( "- Total Links: $total_links" );
		WP_CLI::line( "- Total Clicks: $total_clicks" );
	}

	/**
	 * Create an affiliate ReLink from original URL + partner.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : Original product URL (required).
	 *
	 * [--partner=<slug>]
	 * : Partner slug. Auto-detected from domain when omitted.
	 *
	 * [--slug=<slug>]
	 * : Short URL slug. Auto-generated from URL path when omitted.
	 *
	 * [--title=<title>]
	 * : Link title.
	 *
	 * [--redirect-type=<code>]
	 * : Redirect type: 301, 302, or 307. Default: 301.
	 *
	 * [--dry-run]
	 * : Preview without creating.
	 *
	 * ## EXAMPLES
	 *
	 *     wp relink create --url="https://sonoff.tech/en-hu/products/sonoff-basic-din-rail-matter-over-wifi-smart-switch-basic-1gsp" --partner=sonoff-official
	 *     wp relink create --url="https://sonoff.tech/..." --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function create_link( $args, $assoc_args ): void {
		$url = $assoc_args['url'] ?? '';
		if ( '' === $url ) {
			WP_CLI::error( 'Missing --url argument.' );
		}

		$payload = array(
			'original_url'  => $url,
			'partner'       => $assoc_args['partner'] ?? '',
			'short_slug'    => $assoc_args['slug'] ?? '',
			'title'         => $assoc_args['title'] ?? '',
			'redirect_type' => $assoc_args['redirect-type'] ?? '301',
		);

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			$preview = LinkFactory::preview( $payload );
			if ( is_wp_error( $preview ) ) {
				WP_CLI::error( $preview->get_error_message() );
			}

			WP_CLI::line( 'Dry run — no link created.' );
			WP_CLI::line( 'Original URL: ' . $preview['original_url'] );
			WP_CLI::line( 'Partner: ' . $preview['partner_name'] . ' (' . $preview['partner'] . ')' );
			WP_CLI::line( 'Target URL: ' . $preview['target_url'] );
			WP_CLI::line( 'Short slug: ' . $preview['short_slug'] );
			WP_CLI::line( 'Redirect type: ' . $preview['redirect_type'] );
			if ( $preview['existed'] ) {
				WP_CLI::warning( 'Link already exists (ID: ' . $preview['link_id'] . ').' );
			}
			return;
		}

		$result = LinkFactory::create( $payload );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( $result['existed'] ) {
			WP_CLI::warning( 'Link already exists.' );
		} else {
			WP_CLI::success( 'Link created.' );
		}

		WP_CLI::line( 'ID: ' . $result['link_id'] );
		WP_CLI::line( 'Short URL: ' . $result['short_url'] );
		WP_CLI::line( 'Target URL: ' . $result['target_url'] );
		WP_CLI::line( 'Partner: ' . $result['partner'] );
	}
}
