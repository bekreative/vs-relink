<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

use Vs\ReLink\Database\Schema;

/**
 * Handles automatic cleanup of old click logs.
 */
final class LogRotation {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'vs_relink_daily_cleanup', array( $this, 'run_cleanup' ) );

		if ( ! wp_next_scheduled( 'vs_relink_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'vs_relink_daily_cleanup' );
		}
	}

	/**
	 * Run the database cleanup.
	 *
	 * @return void
	 */
	public function run_cleanup(): void {
		$days = (int) get_option( 'vs_relink_log_retention', '0' );
		if ( $days <= 0 ) {
			return;
		}

		global $wpdb;
		$table = Schema::get_clicks_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- clicks table name comes from Schema.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
