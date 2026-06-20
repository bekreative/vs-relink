<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Taxonomies\Partner;

/**
 * Partner URL detection, merging, and duplicate lookup.
 */
final class PartnerUrlBuilder {

	/**
	 * Normalize an original product URL for storage and comparison.
	 */
	public static function normalize_original_url( string $url ): string {
		$url = trim( $url );
		if ( $url === '' ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return $url;
		}

		$scheme = strtolower( $parsed['scheme'] ?? 'https' );
		$host   = Partner::normalize_host( $parsed['host'] );
		$path   = $parsed['path'] ?? '/';
		$path   = untrailingslashit( $path );
		if ( $path === '' ) {
			$path = '/';
		}

		$query = '';
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query_args );
			if ( is_array( $query_args ) && $query_args !== [] ) {
				ksort( $query_args );
				$query = '?' . http_build_query( $query_args, '', '&', PHP_QUERY_RFC3986 );
			}
		}

		return $scheme . '://' . $host . $path . $query;
	}

	/**
	 * Extract a short slug from the URL path.
	 */
	public static function extract_slug_from_url( string $url ): string {
		$parsed = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parsed ) ) {
			return '';
		}

		$path  = trim( (string) ( $parsed['path'] ?? '' ), '/' );
		$parts = $path !== '' ? explode( '/', $path ) : [];
		$last  = (string) ( array_pop( $parts ) ?: '' );

		return sanitize_title( $last );
	}

	/**
	 * Detect partner term ID from URL host.
	 */
	public static function detect_partner_for_url( string $url ): ?int {
		$parsed = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return null;
		}

		$host = Partner::normalize_host( $parsed['host'] );

		foreach ( Partner::get_all_with_meta() as $partner ) {
			$domains = Partner::parse_domains( $partner['domains'] );
			if ( in_array( $host, $domains, true ) ) {
				return (int) $partner['term_id'];
			}
		}

		return null;
	}

	/**
	 * Build target URL by merging original URL with partner suffix.
	 */
	public static function build_target_url( string $original_url, string $suffix ): string {
		$original_url = self::normalize_original_url( $original_url );
		$suffix       = trim( $suffix );

		if ( $original_url === '' ) {
			return '';
		}

		if ( $suffix === '' ) {
			return $original_url;
		}

		$parsed = wp_parse_url( $original_url );
		if ( ! is_array( $parsed ) ) {
			return $original_url;
		}

		$base = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );
		if ( ! empty( $parsed['port'] ) ) {
			$base .= ':' . $parsed['port'];
		}
		$base .= $parsed['path'] ?? '/';

		$existing_args = [];
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $existing_args );
		}

		$suffix_query = ltrim( $suffix, '?&' );
		$suffix_args  = [];
		parse_str( $suffix_query, $suffix_args );

		$merged = array_merge( $existing_args, $suffix_args );
		if ( $merged === [] ) {
			return $base;
		}

		return $base . '?' . http_build_query( $merged, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Get partner URL suffix for a term.
	 */
	public static function get_partner_suffix( int $partner_term_id ): string {
		return (string) get_term_meta( $partner_term_id, Partner::META_URL_SUFFIX, true );
	}

	/**
	 * Find existing link for original URL + partner combination.
	 */
	public static function find_existing_link( string $original_url, int $partner_term_id, int $exclude_post_id = 0 ): ?int {
		$normalized = self::normalize_original_url( $original_url );
		if ( $normalized === '' || $partner_term_id <= 0 ) {
			return null;
		}

		$query = new \WP_Query(
			[
				'post_type'      => ReLink::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post__not_in'   => $exclude_post_id > 0 ? [ $exclude_post_id ] : [],
				'meta_query'     => [
					[
						'key'   => '_lw_relink_original_url',
						'value' => $normalized,
					],
				],
				'tax_query'      => [
					[
						'taxonomy' => Partner::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => [ $partner_term_id ],
					],
				],
			]
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		return (int) $query->posts[0];
	}

	/**
	 * Check whether a slug is already used by another ReLink.
	 */
	public static function slug_exists( string $slug, int $exclude_post_id = 0 ): bool {
		$slug = sanitize_title( $slug );
		if ( $slug === '' ) {
			return false;
		}

		$existing = get_page_by_path( $slug, OBJECT, ReLink::POST_TYPE );
		if ( ! $existing ) {
			return false;
		}

		return $exclude_post_id <= 0 || (int) $existing->ID !== $exclude_post_id;
	}

	/**
	 * Resolve a unique slug, appending partner slug on collision.
	 */
	public static function resolve_unique_slug( string $base_slug, string $partner_slug = '', int $exclude_post_id = 0 ): string {
		$base_slug = sanitize_title( $base_slug );
		if ( $base_slug === '' ) {
			$base_slug = 'link';
		}

		if ( ! self::slug_exists( $base_slug, $exclude_post_id ) ) {
			return $base_slug;
		}

		if ( $partner_slug !== '' ) {
			$candidate = sanitize_title( $base_slug . '-' . $partner_slug );
			if ( ! self::slug_exists( $candidate, $exclude_post_id ) ) {
				return $candidate;
			}
		}

		$counter = 2;
		while ( self::slug_exists( $base_slug . '-' . $counter, $exclude_post_id ) ) {
			++$counter;
		}

		return $base_slug . '-' . $counter;
	}
}
