<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\Core\LinkFactory;
use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Taxonomies\LinkGroup;
use Vs\ReLink\Taxonomies\Partner;

/**
 * Handles JSON and htaccess exporting/importing.
 */
final class DataService {

	/**
	 * Export all links to a JSON array.
	 *
	 * @return array
	 */
	public static function export_to_json(): array {
		$args = [
			'post_type'      => ReLink::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		];

		$query = new \WP_Query( $args );
		$data  = [];

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$terms = wp_get_object_terms( $post->ID, LinkGroup::TAXONOMY );
				$group = ! empty( $terms ) && ! is_wp_error( $terms ) ? [ 'name' => $terms[0]->name, 'slug' => $terms[0]->slug ] : null;

				$partner_terms = wp_get_object_terms( $post->ID, Partner::TAXONOMY );
				$partner       = ( ! is_wp_error( $partner_terms ) && ! empty( $partner_terms ) )
					? [ 'name' => $partner_terms[0]->name, 'slug' => $partner_terms[0]->slug ]
					: null;

				$data[] = [
					'title'          => $post->post_title,
					'slug'           => $post->post_name,
					'description'    => $post->post_content,
					'original_url'   => get_post_meta( $post->ID, '_lw_relink_original_url', true ),
					'target_url'     => get_post_meta( $post->ID, '_lw_relink_target_url', true ),
					'redirect_type'  => get_post_meta( $post->ID, '_lw_relink_type', true ),
					'is_nofollow'    => get_post_meta( $post->ID, '_lw_relink_nofollow', true ),
					'is_sponsored'   => get_post_meta( $post->ID, '_lw_relink_sponsored', true ),
					'forward_params' => get_post_meta( $post->ID, '_lw_relink_forward_params', true ),
					'tracking'       => get_post_meta( $post->ID, '_lw_relink_tracking', true ),
					'group'          => $group,
					'partner'        => $partner,
				];
			}
		}

		return $data;
	}

	/**
	 * Import links from a JSON array with optional search and replace.
	 *
	 * @param array  $links        Array of links data.
	 * @param string $find_url     Optional string to find in target URL.
	 * @param string $replace_url  Optional string to replace with.
	 * @return array
	 */
	public static function import_from_json( array $links, string $find_url = '', string $replace_url = '' ): array {
		$imported = 0;
		$skipped  = 0;

		foreach ( $links as $link ) {
			if ( ! empty( $link['original_url'] ) && ! empty( $link['partner']['slug'] ) ) {
				$result = LinkFactory::create(
					[
						'original_url'  => $link['original_url'],
						'partner'       => $link['partner']['slug'],
						'short_slug'    => $link['slug'] ?? '',
						'title'         => $link['title'] ?? '',
						'redirect_type' => $link['redirect_type'] ?? '301',
						'tracking'      => ( $link['tracking'] ?? 'yes' ) !== 'no',
						'nofollow'      => ( $link['is_nofollow'] ?? '' ) === 'yes',
						'sponsored'     => ( $link['is_sponsored'] ?? '' ) === 'yes',
						'forward_params' => ( $link['forward_params'] ?? '' ) === 'yes',
					]
				);

				if ( is_wp_error( $result ) ) {
					$skipped++;
					continue;
				}

				if ( $result['existed'] ) {
					$skipped++;
					continue;
				}

				$imported++;
				continue;
			}

			// Check for duplicates
			$existing = get_page_by_path( $link['slug'], OBJECT, ReLink::POST_TYPE );
			if ( $existing ) {
				$skipped++;
				continue;
			}

			$target_url = $link['target_url'];
			if ( ! empty( $find_url ) && ! empty( $replace_url ) ) {
				$target_url = str_replace( $find_url, $replace_url, $target_url );
			}

			// Handle hierarchical slugs
			$parts = explode( '/', trim( $link['slug'], '/' ) );
			$parent_id = 0;
			$current_path = '';
			$final_name = $link['slug'];

			foreach ( $parts as $index => $part ) {
				$current_path .= ( $current_path ? '/' : '' ) . $part;
				if ( $index === count( $parts ) - 1 ) {
					$final_name = $part;
					break;
				}
				$existing = get_page_by_path( $current_path, OBJECT, ReLink::POST_TYPE );
				if ( $existing ) {
					$parent_id = $existing->ID;
				} else {
					$parent_id = wp_insert_post( [
						'post_title'  => ucfirst( $part ),
						'post_name'   => $part,
						'post_parent' => $parent_id,
						'post_type'   => ReLink::POST_TYPE,
						'post_status' => 'publish',
					] );
				}
			}

			$post_id = wp_insert_post( [
				'post_title'   => $link['title'],
				'post_name'    => $final_name,
				'post_parent'  => $parent_id,
				'post_type'    => ReLink::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => $link['description'] ?? '',
			] );

			if ( is_wp_error( $post_id ) ) {
				$skipped++;
				continue;
			}

			update_post_meta( $post_id, '_lw_relink_target_url', $target_url );
			update_post_meta( $post_id, '_lw_relink_type', $link['redirect_type'] ?? '301' );
			update_post_meta( $post_id, '_lw_relink_nofollow', $link['is_nofollow'] ?? 'no' );
			update_post_meta( $post_id, '_lw_relink_sponsored', $link['is_sponsored'] ?? 'no' );
			update_post_meta( $post_id, '_lw_relink_forward_params', $link['forward_params'] ?? 'no' );
			update_post_meta( $post_id, '_lw_relink_tracking', $link['tracking'] ?? 'yes' );

			if ( ! empty( $link['group'] ) ) {
				$term = wp_insert_term( $link['group']['name'], LinkGroup::TAXONOMY, [ 'slug' => $link['group']['slug'] ] );
				$term_id = is_wp_error( $term ) ? ( $term->get_error_data('term_exists') ?: null ) : $term['term_id'];
				if ( $term_id ) {
					wp_set_object_terms( $post_id, (int) $term_id, LinkGroup::TAXONOMY );
				}
			}

			$imported++;
		}

		return [
			'success' => true,
			'message' => sprintf( __( 'Import completed. %d imported, %d skipped.', 'vs-relink' ), $imported, $skipped ),
		];
	}

	/**
	 * Generate .htaccess redirect rules.
	 *
	 * @return string
	 */
	public static function generate_htaccess(): string {
		$args = [
			'post_type'      => ReLink::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		];

		$query = new \WP_Query( $args );
		$rules = "# LW ReLink Export\n";
		$rules .= "RewriteEngine On\n\n";

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$target_url = get_post_meta( $post->ID, '_lw_relink_target_url', true );
				$type       = get_post_meta( $post->ID, '_lw_relink_type', true ) ?: '301';
				
				// Get relative path for the link
				$base  = get_option( 'lw_relink_base', 're' );
				$terms = wp_get_object_terms( $post->ID, LinkGroup::TAXONOMY );
				$path  = '/';
				
				if ( $base ) {
					$path .= $base . '/';
				}

				if ( ! empty( $terms ) ) {
					$path .= $terms[0]->slug . '/';
				}
				$path .= $post->post_name;

				$rules .= "Redirect $type $path $target_url\n";
			}
		}

		return $rules;
	}
}
