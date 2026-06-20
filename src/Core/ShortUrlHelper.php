<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

use Vs\ReLink\PostTypes\ReLink;

/**
 * Short URL display and path editing helpers.
 */
final class ShortUrlHelper {

	/**
	 * Full short URL for a ReLink.
	 */
	public static function get_full_url( int $post_id ): string {
		return (string) get_permalink( $post_id );
	}

	/**
	 * Path without protocol (for display/copy).
	 */
	public static function get_display_path( int $post_id ): string {
		$url = self::get_full_url( $post_id );

		return rtrim( (string) preg_replace( '#^https?://#', '', $url ), '/' );
	}

	/**
	 * Relative path after site home (editable segment).
	 */
	public static function get_path_suffix( int $post_id ): string {
		$full = trailingslashit( self::get_full_url( $post_id ) );
		$home = trailingslashit( home_url( '/' ) );

		if ( str_starts_with( $full, $home ) ) {
			return untrailingslashit( substr( $full, strlen( $home ) ) );
		}

		return self::get_display_path( $post_id );
	}

	/**
	 * Update hierarchical slug path from user-edited suffix.
	 *
	 * @return true|\WP_Error
	 */
	public static function update_from_path_suffix( int $post_id, string $path_suffix ) {
		$path_suffix = trim( sanitize_text_field( $path_suffix ), '/' );

		if ( $path_suffix === '' ) {
			return new \WP_Error( 'empty_path', __( 'Short URL path cannot be empty.', 'vs-relink' ) );
		}

		$parts     = array_values( array_filter( explode( '/', $path_suffix ) ) );
		$leaf_slug = sanitize_title( (string) array_pop( $parts ) );

		if ( $leaf_slug === '' ) {
			return new \WP_Error( 'invalid_slug', __( 'Invalid short URL slug.', 'vs-relink' ) );
		}

		$parent_id = 0;
		$current   = '';

		foreach ( $parts as $part ) {
			$part_slug = sanitize_title( $part );
			$current   = $current ? $current . '/' . $part_slug : $part_slug;

			$parent = get_page_by_path( $current, OBJECT, ReLink::POST_TYPE );
			if ( ! $parent ) {
				return new \WP_Error( 'missing_parent', __( 'Parent path segment does not exist.', 'vs-relink' ) );
			}

			$parent_id = (int) $parent->ID;
		}

		$result = wp_update_post(
			[
				'ID'          => $post_id,
				'post_name'   => $leaf_slug,
				'post_parent' => $parent_id,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
