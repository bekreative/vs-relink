<?php
/**
 * One-time migration from lw-relink storage IDs to vs_relink.
 *
 * Temporary — remove once no live lw-relink sites remain.
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

	private const FLAG = 'vs_relink_lw_migrated';

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
	 * Run once if needed.
	 */
	public static function maybe_migrate(): void {
		if ( (bool) get_option( self::FLAG, false ) ) {
			return;
		}

		global $wpdb;

		$did = false;

		$legacy_posts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
				'lw_relink'
			)
		);

		if ( $legacy_posts > 0 ) {
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

		update_option( self::FLAG, '1', false );

		if ( $did ) {
			flush_rewrite_rules( false );
		}
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
