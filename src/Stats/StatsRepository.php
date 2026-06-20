<?php

declare(strict_types=1);

namespace Vs\ReLink\Stats;

use Vs\ReLink\Database\Schema;
use Vs\ReLink\PostTypes\ReLink;

/**
 * Handles data retrieval for statistics.
 */
final class StatsRepository {

	/**
	 * Get total clicks for a link or all links.
	 *
	 * @param int|null $link_id Optional link ID.
	 * @return int
	 */
	public static function get_total_clicks( ?int $link_id = null ): int {
		global $wpdb;

		$table = Schema::get_clicks_table();
		$where = $link_id ? $wpdb->prepare( 'WHERE link_id = %d', $link_id ) : '';

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table $where" );
	}

	/**
	 * Get clicks within a period with optional filters.
	 */
	public static function get_clicks_in_period( int $days, ?int $link_id = null, ?int $group_id = null ): int {
		global $wpdb;

		$table = Schema::get_clicks_table();
		$where = self::build_click_where( $days, $link_id, $group_id );

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table c $where" );
	}

	/**
	 * Count unique visitors (distinct IPs) in a period.
	 */
	public static function get_unique_visitors( int $days, ?int $link_id = null, ?int $group_id = null ): int {
		global $wpdb;

		$table = Schema::get_clicks_table();
		$where = self::build_click_where( $days, $link_id, $group_id );
		$where .= " AND c.ip_address != ''";

		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT c.ip_address) FROM $table c $where" );
	}

	/**
	 * Count links with at least one click in the period.
	 */
	public static function get_active_links( int $days, ?int $group_id = null ): int {
		global $wpdb;

		$table = Schema::get_clicks_table();
		$where = self::build_click_where( $days, null, $group_id );

		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT c.link_id) FROM $table c $where" );
	}

	/**
	 * Percent change vs the previous period of equal length.
	 */
	public static function percent_change( int $current, int $previous ): float {
		if ( $previous <= 0 ) {
			return $current > 0 ? 100.0 : 0.0;
		}

		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}

	/**
	 * Get recent clicks.
	 *
	 * @param int $limit Number of clicks to return.
	 * @return array
	 */
	public static function get_recent_clicks( int $limit = 20 ): array {
		global $wpdb;

		$table = Schema::get_clicks_table();
		$posts = $wpdb->posts;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*, p.post_title 
			 FROM $table c 
			 JOIN $posts p ON c.link_id = p.ID 
			 ORDER BY c.timestamp DESC 
			 LIMIT %d",
			$limit
		), ARRAY_A );
	}

	/**
	 * Get clicks grouped by link.
	 *
	 * @return array
	 */
	public static function get_clicks_by_link(): array {
		global $wpdb;

		$table = Schema::get_clicks_table();
		$posts = $wpdb->posts;

		return $wpdb->get_results(
			"SELECT p.ID, p.post_title, COUNT(c.id) as click_count 
			 FROM $posts p 
			 LEFT JOIN $table c ON p.ID = c.link_id 
			 WHERE p.post_type = 'lw_relink' AND p.post_status = 'publish' 
			 GROUP BY p.ID 
			 ORDER BY click_count DESC",
			ARRAY_A
		);
	}

	/**
	 * Get click trends for a period with optional filters.
	 *
	 * @param int      $days     Number of days.
	 * @param int|null $link_id  Optional link filter.
	 * @param int|null $group_id Optional group filter.
	 * @return array
	 */
	public static function get_click_trends( int $days = 30, ?int $link_id = null, ?int $group_id = null ): array {
		global $wpdb;

		$table = Schema::get_clicks_table();
		$where = self::build_click_where( $days, $link_id, $group_id );

		return $wpdb->get_results(
			"SELECT DATE(c.timestamp) as date, COUNT(*) as count 
			 FROM $table c 
			 $where
			 GROUP BY DATE(c.timestamp)
			 ORDER BY date ASC",
			ARRAY_A
		);
	}

	/**
	 * Get top links by clicks in a period.
	 */
	public static function get_top_links( int $limit = 10, int $days = 30, ?int $group_id = null ): array {
		global $wpdb;

		$table = Schema::get_clicks_table();
		$posts = $wpdb->posts;
		$where = self::build_click_where( $days, null, $group_id );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, p.post_title, COUNT(c.id) as click_count 
			 FROM $posts p 
			 JOIN $table c ON p.ID = c.link_id 
			 $where AND p.post_type = %s AND p.post_status = 'publish'
			 GROUP BY p.ID 
			 ORDER BY click_count DESC 
			 LIMIT %d",
			ReLink::POST_TYPE,
			$limit
		), ARRAY_A );
	}

	/**
	 * Get paginated link stats with optional filtering.
	 *
	 * @param int      $limit    Number of items.
	 * @param int      $offset   Offset.
	 * @param int|null $group_id Optional category filter.
	 * @param int|null $link_id  Optional specific link filter.
	 * @param int      $days     Period for click counts.
	 * @return array
	 */
	public static function get_paginated_link_stats(
		int $limit = 20,
		int $offset = 0,
		?int $group_id = null,
		?int $link_id = null,
		int $days = 30
	): array {
		global $wpdb;

		$table      = Schema::get_clicks_table();
		$period_sql = $wpdb->prepare( 'c.timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );

		$where = "WHERE p.post_type = 'lw_relink' AND p.post_status = 'publish'";

		if ( $link_id ) {
			$where .= $wpdb->prepare( ' AND p.ID = %d', $link_id );
		}

		if ( $group_id ) {
			$where .= $wpdb->prepare(
				" AND p.ID IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d)",
				$group_id
			);
		}

		$sql = "
			SELECT p.ID, p.post_title,
			       SUM(CASE WHEN $period_sql THEN 1 ELSE 0 END) as click_count,
			       MAX(c.timestamp) as last_click
			FROM {$wpdb->posts} p
			LEFT JOIN $table c ON p.ID = c.link_id
			$where
			GROUP BY p.ID
			ORDER BY click_count DESC
			LIMIT %d OFFSET %d
		";

		return $wpdb->get_results( $wpdb->prepare( $sql, $limit, $offset ), ARRAY_A );
	}

	/**
	 * Build WHERE clause for click queries.
	 */
	private static function build_click_where( int $days, ?int $link_id, ?int $group_id ): string {
		global $wpdb;

		$clauses = [ $wpdb->prepare( 'c.timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days ) ];

		if ( $link_id ) {
			$clauses[] = $wpdb->prepare( 'c.link_id = %d', $link_id );
		}

		if ( $group_id ) {
			$clauses[] = $wpdb->prepare(
				"c.link_id IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d)",
				$group_id
			);
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}
}
