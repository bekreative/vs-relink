<?php

declare(strict_types=1);

namespace Vs\ReLink\Database;

/**
 * Handles database schema creation and updates.
 */
final class Schema {

	/**
	 * Activate the schema.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		update_option( 'lw_relink_db_version', '1.1.0' );
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'lw_relink_clicks';

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			link_id bigint(20) NOT NULL,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			ip_address varchar(45) DEFAULT '' NOT NULL,
			referer text DEFAULT '' NOT NULL,
			user_agent text DEFAULT '' NOT NULL,
			is_bot tinyint(1) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			KEY link_id (link_id),
			KEY timestamp (timestamp)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get the clicks table name.
	 *
	 * @return string
	 */
	public static function get_clicks_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'lw_relink_clicks';
	}
}
