<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\Core\LinkFactory;
use Vs\ReLink\Core\UrlGuard;
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
		$args = array(
			'post_type'      => ReLink::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		$query = new \WP_Query( $args );
		$data  = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$terms = wp_get_object_terms( $post->ID, LinkGroup::TAXONOMY );
				$group = ! empty( $terms ) && ! is_wp_error( $terms ) ? array(
					'name' => $terms[0]->name,
					'slug' => $terms[0]->slug,
				) : null;

				$partner_terms = wp_get_object_terms( $post->ID, Partner::TAXONOMY );
				$partner       = ( ! is_wp_error( $partner_terms ) && ! empty( $partner_terms ) )
					? array(
						'name' => $partner_terms[0]->name,
						'slug' => $partner_terms[0]->slug,
					)
					: null;

				$data[] = array(
					'title'          => $post->post_title,
					'slug'           => $post->post_name,
					'description'    => $post->post_content,
					'original_url'   => get_post_meta( $post->ID, '_vs_relink_original_url', true ),
					'target_url'     => get_post_meta( $post->ID, '_vs_relink_target_url', true ),
					'redirect_type'  => get_post_meta( $post->ID, '_vs_relink_type', true ),
					'is_nofollow'    => get_post_meta( $post->ID, '_vs_relink_nofollow', true ),
					'is_sponsored'   => get_post_meta( $post->ID, '_vs_relink_sponsored', true ),
					'forward_params' => get_post_meta( $post->ID, '_vs_relink_forward_params', true ),
					'tracking'       => get_post_meta( $post->ID, '_vs_relink_tracking', true ),
					'group'          => $group,
					'partner'        => $partner,
				);
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
		$limit    = (int) apply_filters( 'vs_relink_import_batch_limit', LinkFactory::IMPORT_BATCH_LIMIT );
		if ( $limit < 1 ) {
			$limit = LinkFactory::IMPORT_BATCH_LIMIT;
		}

		$processed = 0;
		foreach ( $links as $link ) {
			if ( $processed >= $limit ) {
				++$skipped;
				continue;
			}
			++$processed;

			if ( ! is_array( $link ) ) {
				++$skipped;
				continue;
			}

			$result = LinkFactory::import_record( $link, $find_url, $replace_url );
			if ( is_wp_error( $result ) || ! empty( $result['existed'] ) ) {
				++$skipped;
				continue;
			}

			++$imported;
		}

		$message = sprintf(
			/* translators: 1: imported row count, 2: skipped row count */
			__( 'Import completed. %1$d imported, %2$d skipped.', 'vs-relink' ),
			$imported,
			$skipped
		);
		if ( count( $links ) > $limit ) {
			$message .= ' ' . sprintf(
				/* translators: %d: maximum rows imported in one request */
				__( 'Batch limit is %d rows.', 'vs-relink' ),
				$limit
			);
		}

		return array(
			'success' => true,
			'message' => $message,
		);
	}

	/**
	 * Generate .htaccess redirect rules.
	 *
	 * @return string
	 */
	public static function generate_htaccess(): string {
		$args = array(
			'post_type'      => ReLink::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		$query  = new \WP_Query( $args );
		$rules  = "# VS ReLink Export\n";
		$rules .= "RewriteEngine On\n\n";

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$target_url = (string) get_post_meta( $post->ID, '_vs_relink_target_url', true );
				if ( ! UrlGuard::is_http_url( $target_url ) ) {
					continue;
				}
				$stored_type = get_post_meta( $post->ID, '_vs_relink_type', true );
				$type        = (string) UrlGuard::redirect_code( $stored_type ? $stored_type : '301' );

				// Get relative path for the link
				$base  = get_option( 'vs_relink_base', 're' );
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
