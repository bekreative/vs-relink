<?php
/**
 * Upgrade-time rename of VS 1.x lw_* storage IDs to vs_relink.
 *
 * Keep until every live site reports zero remaining legacy rows.
 *
 * @package Vs\ReLink\Database
 */

declare(strict_types=1);

namespace Vs\ReLink\Database;

use Vs\ReLink\Storage\Ids;

/**
 * Migrates CPT, taxonomies, options, post meta, term meta, and clicks table.
 */
final class LegacyMigrator {

	public const FLAG = 'vs_relink_lw_migrated';

	public const NEEDS_REPAIR = 'vs_relink_lw_needs_repair';

	private const OPTION_MAP = [
		'lw_relink_db_version'    => Ids::OPTION_DB_VERSION,
		'lw_relink_base'          => Ids::OPTION_BASE,
		'lw_relink_exclude_bots'  => 'vs_relink_exclude_bots',
		'lw_relink_log_retention' => 'vs_relink_log_retention',
		'lw_relink_webhook_url'   => 'vs_relink_webhook_url',
	];

	private const META_MAP = [
		'_lw_relink_original_url'   => '_vs_relink_original_url',
		'_lw_relink_target_url'     => '_vs_relink_target_url',
		'_lw_relink_type'           => '_vs_relink_type',
		'_lw_relink_keywords'       => '_vs_relink_keywords',
		'_lw_relink_nofollow'       => '_vs_relink_nofollow',
		'_lw_relink_sponsored'      => '_vs_relink_sponsored',
		'_lw_relink_forward_params' => '_vs_relink_forward_params',
		'_lw_relink_tracking'       => '_vs_relink_tracking',
	];

	private const TERM_META_MAP = [
		'_lw_partner_domains'    => Ids::PARTNER_META_DOMAINS,
		'_lw_partner_url_suffix' => Ids::PARTNER_META_URL_SUFFIX,
	];

	/**
	 * Run on activation / plugins_loaded when legacy data remains.
	 */
	public static function maybe_migrate(): void {
		$counts = self::detect_remaining();
		$total  = array_sum( $counts );

		if ( $total <= 0 ) {
			update_option( self::FLAG, '1', false );
			delete_option( self::NEEDS_REPAIR );
			return;
		}

		// Flag set but leftovers → repair path.
		if ( (bool) get_option( self::FLAG, false ) ) {
			update_option( self::NEEDS_REPAIR, '1', false );
		}

		self::migrate( false );
	}

	/**
	 * @return array{posts:int,taxonomies:int,options:int,postmeta:int,termmeta:int,table:int,cron:int}
	 */
	public static function detect_remaining(): array {
		global $wpdb;

		$tax = 0;
		foreach ( [ 'lw_relink_partner', 'lw_link_group' ] as $taxonomy ) {
			$tax += (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
					$taxonomy
				)
			);
		}

		$options = 0;
		foreach ( array_keys( self::OPTION_MAP ) as $legacy ) {
			$exists = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					$legacy
				)
			);
			if ( $exists ) {
				++$options;
			}
		}

		$meta_keys = array_keys( self::META_MAP );
		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$postmeta = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)", ...$meta_keys ) );

		$term_keys = array_keys( self::TERM_META_MAP );
		$tph       = implode( ',', array_fill( 0, count( $term_keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$termmeta = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key IN ($tph)", ...$term_keys ) );

		$legacy_table = $wpdb->prefix . 'lw_relink_clicks';
		$table        = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) ) === $legacy_table ? 1 : 0;

		return [
			'posts'      => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
					'lw_relink'
				)
			),
			'taxonomies' => $tax,
			'options'    => $options,
			'postmeta'   => $postmeta,
			'termmeta'   => $termmeta,
			'table'      => $table,
			'cron'       => wp_next_scheduled( 'lw_relink_daily_cleanup' ) ? 1 : 0,
		];
	}

	/**
	 * @return array{dry_run:bool,before:array<string,int>,after:array<string,int>,changed:bool}
	 */
	public static function migrate( bool $dry_run = false ): array {
		$before = self::detect_remaining();

		if ( $dry_run || array_sum( $before ) <= 0 ) {
			if ( ! $dry_run && array_sum( $before ) <= 0 ) {
				update_option( self::FLAG, '1', false );
				delete_option( self::NEEDS_REPAIR );
			}

			return [
				'dry_run' => $dry_run,
				'before'  => $before,
				'after'   => $before,
				'changed' => false,
			];
		}

		global $wpdb;
		$did = false;

		if ( $before['posts'] > 0 ) {
			$wpdb->update( $wpdb->posts, [ 'post_type' => Ids::POST_TYPE ], [ 'post_type' => 'lw_relink' ] );
			$did = true;
		}

		$did = self::rename_taxonomy( 'lw_relink_partner', Ids::TAXONOMY_PARTNER ) || $did;
		$did = self::rename_taxonomy( 'lw_link_group', Ids::TAXONOMY_LINK_GROUP ) || $did;

		foreach ( self::OPTION_MAP as $legacy => $modern ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					$legacy
				)
			);
			if ( ! $row ) {
				continue;
			}
			$modern_exists = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					$modern
				)
			);
			if ( ! $modern_exists ) {
				update_option( $modern, maybe_unserialize( $row->option_value ), false );
			}
			delete_option( $legacy );
			$did = true;
		}

		foreach ( self::META_MAP as $legacy => $modern ) {
			$updated = $wpdb->update( $wpdb->postmeta, [ 'meta_key' => $modern ], [ 'meta_key' => $legacy ] );
			if ( false !== $updated && $updated > 0 ) {
				$did = true;
			}
		}

		foreach ( self::TERM_META_MAP as $legacy => $modern ) {
			$updated = $wpdb->update( $wpdb->termmeta, [ 'meta_key' => $modern ], [ 'meta_key' => $legacy ] );
			if ( false !== $updated && $updated > 0 ) {
				$did = true;
			}
		}

		$legacy_table  = $wpdb->prefix . 'lw_relink_clicks';
		$modern_table  = $wpdb->prefix . Ids::TABLE_CLICKS;
		$legacy_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) ) === $legacy_table;
		$modern_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $modern_table ) ) === $modern_table;
		if ( $legacy_exists && ! $modern_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "RENAME TABLE `{$legacy_table}` TO `{$modern_table}`" );
			$did = true;
		}

		$timestamp = wp_next_scheduled( 'lw_relink_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'lw_relink_daily_cleanup' );
			$did = true;
		}

		$after = self::detect_remaining();
		if ( array_sum( $after ) <= 0 ) {
			update_option( self::FLAG, '1', false );
			delete_option( self::NEEDS_REPAIR );
		} else {
			update_option( self::NEEDS_REPAIR, '1', false );
		}

		if ( $did ) {
			flush_rewrite_rules( false );
		}

		return [
			'dry_run' => false,
			'before'  => $before,
			'after'   => $after,
			'changed' => $did,
		];
	}

	public static function needs_admin_notice(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( (bool) get_option( self::NEEDS_REPAIR, false ) ) {
			return true;
		}

		return array_sum( self::detect_remaining() ) > 0;
	}

	private static function rename_taxonomy( string $legacy, string $modern ): bool {
		global $wpdb;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
				$legacy
			)
		);
		if ( $count <= 0 ) {
			return false;
		}

		$wpdb->update( $wpdb->term_taxonomy, [ 'taxonomy' => $modern ], [ 'taxonomy' => $legacy ] );
		return true;
	}
}
