<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\PostTypes\ReLink;

/**
 * Handles checking the health and correctness of imported links.
 */
final class LinkChecker {

	/**
	 * Check a single link's health.
	 *
	 * @param int $post_id ReLink Post ID.
	 * @return array Results of the check.
	 */
	public static function check_link( int $post_id ): array {
		$target_url = get_post_meta( $post_id, '_lw_relink_target_url', true );
		$short_url  = get_permalink( $post_id );

		if ( empty( $target_url ) ) {
			return [ 'success' => false, 'message' => __( 'No target URL configured.', 'vs-relink' ) ];
		}

		// Test the redirection
		$response = wp_remote_head( $short_url, [ 'redirection' => 0, 'timeout' => 10 ] );
		
		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => __( 'Short URL unreachable.', 'vs-relink' ) . ' ' . $response->get_error_message() ];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$location    = wp_remote_retrieve_header( $response, 'location' );

		// Simplify comparison (handle trailing slashes, etc)
		$normalized_location = untrailingslashit( strtolower( (string) $location ) );
		$normalized_target   = untrailingslashit( strtolower( (string) $target_url ) );

		if ( in_array( $status_code, [ 301, 302, 307 ], true ) && $normalized_location === $normalized_target ) {
			return [ 
				'success' => true, 
				'message' => sprintf( __( 'Success! Redirects to %s', 'vs-relink' ), $location ) 
			];
		}

		return [ 
			'success' => false, 
			'message' => sprintf( __( 'Failure. Expected redirect to %s but got status %d and location %s', 'vs-relink' ), $target_url, $status_code, $location ?: 'None' ) 
		];
	}

	/**
	 * Get all ReLink IDs for batch checking.
	 *
	 * @return array
	 */
	public static function get_all_relink_ids(): array {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish'", ReLink::POST_TYPE ) );
	}
}
