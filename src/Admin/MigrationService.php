<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Taxonomies\LinkGroup;

/**
 * Handles migration from Pretty Link Lite.
 */
final class MigrationService {

	/**
	 * Run the migration.
	 *
	 * @return array Results of the migration.
	 */
	public static function run_pretty_link_migration(): array {
		global $wpdb;

		$pl_table = $wpdb->prefix . 'prli_links';
		
		// Check if source table exists.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$pl_table'" ) !== $pl_table ) {
			return [
				'success' => false,
				'message' => __( 'Pretty Link Lite table not found.', 'vs-relink' ),
			];
		}

		$links = $wpdb->get_results( "SELECT * FROM $pl_table", ARRAY_A );
		if ( empty( $links ) ) {
			return [
				'success' => true,
				'message' => __( 'No links found to migrate.', 'vs-relink' ),
				'count'   => 0,
			];
		}

		$migrated_count = 0;
		$errors = 0;

		foreach ( $links as $link ) {
			$post_id = self::migrate_single_link( $link );
			if ( $post_id ) {
				$migrated_count++;
			} else {
				$errors++;
			}
		}

		return [
			'success' => true,
			'message' => sprintf( __( 'Migration completed. %d links migrated, %d skipped/errors.', 'vs-relink' ), $migrated_count, $errors ),
			'count'   => $migrated_count,
		];
	}

	/**
	 * Migrate a single link.
	 *
	 * @param array $data Pretty Link raw data.
	 * @return int|bool New post ID or false on failure.
	 */
	private static function migrate_single_link( array $data ): int|bool {
		// Check if it already exists by slug.
		$existing = get_page_by_path( $data['slug'], OBJECT, ReLink::POST_TYPE );
		if ( $existing ) {
			return false; 
		}

		$slug_info = self::resolve_hierarchical_slug( $data['slug'] );
		
		$post_id = wp_insert_post( [
			'post_title'   => $data['name'] ?: $slug_info['name'],
			'post_name'    => $slug_info['name'],
			'post_parent'  => $slug_info['parent_id'],
			'post_type'    => ReLink::POST_TYPE,
			'post_status'  => 'publish',
			'post_content' => $data['description'] ?? '',
			'post_date'    => $data['created_at'] ?? current_time( 'mysql' ),
		] );

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Update Metadata.
		update_post_meta( $post_id, '_lw_relink_target_url', $data['url'] );
		update_post_meta( $post_id, '_lw_relink_type', $data['redirect_type'] ?? '301' );
		update_post_meta( $post_id, '_lw_relink_nofollow', ( $data['nofollow'] ?? 0 ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_lw_relink_sponsored', ( $data['sponsored'] ?? 0 ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_lw_relink_forward_params', ( $data['param_forwarding'] ?? 0 ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_lw_relink_tracking', ( $data['track_me'] ?? 1 ) ? 'yes' : 'no' );

		// Handle Groups if applicable.
		if ( ! empty( $data['group_id'] ) ) {
			self::assign_group( (int) $post_id, (int) $data['group_id'] );
		}

		return (int) $post_id;
	}

	/**
	 * Resolves a hierarchical slug and ensures parent posts exist.
	 *
	 * @param string $slug The full slug (e.g. 'reolink/gorangerpt').
	 * @return array ['name' => string, 'parent_id' => int]
	 */
	private static function resolve_hierarchical_slug( string $slug ): array {
		$parts = explode( '/', trim( $slug, '/' ) );
		$parent_id = 0;
		$current_path = '';

		foreach ( $parts as $index => $part ) {
			$current_path .= ( $current_path ? '/' : '' ) . $part;
			
			// If it's the last part, we don't create it here (it will be created by migrate_single_link)
			if ( $index === count( $parts ) - 1 ) {
				return [
					'name'      => $part,
					'parent_id' => $parent_id,
				];
			}

			// Check if this parent part exists
			$existing = get_page_by_path( $current_path, OBJECT, ReLink::POST_TYPE );
			
			if ( $existing ) {
				$parent_id = $existing->ID;
			} else {
				// Create a placeholder parent ReLink
				$parent_id = wp_insert_post( [
					'post_title'  => ucfirst( $part ),
					'post_name'   => $part,
					'post_parent' => $parent_id,
					'post_type'   => ReLink::POST_TYPE,
					'post_status' => 'publish',
				] );
			}
		}

		return [
			'name'      => $slug,
			'parent_id' => 0,
		];
	}

	/**
	 * Assign a group (folder) to the link.
	 *
	 * @param int $post_id The new Relink ID.
	 * @param int $pl_group_id The old Pretty Link group ID.
	 * @return void
	 */
	private static function assign_group( int $post_id, int $pl_group_id ): void {
		global $wpdb;
		$group_table = $wpdb->prefix . 'prli_groups';
		
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$group_table'" ) !== $group_table ) {
			return;
		}

		$group_data = $wpdb->get_row( $wpdb->prepare( "SELECT name, slug FROM $group_table WHERE id = %d", $pl_group_id ), ARRAY_A );
		
		if ( $group_data ) {
			$term = wp_insert_term( $group_data['name'], LinkGroup::TAXONOMY, [ 'slug' => $group_data['slug'] ] );
			$term_id = is_wp_error( $term ) ? ( $term->get_error_data('term_exists') ?: null ) : $term['term_id'];
			
			if ( $term_id ) {
				wp_set_object_terms( $post_id, (int) $term_id, LinkGroup::TAXONOMY );
			}
		}
	}
}
