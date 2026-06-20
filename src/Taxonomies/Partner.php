<?php

declare(strict_types=1);

namespace Vs\ReLink\Taxonomies;

/**
 * Partner taxonomy for affiliate URL suffix configuration.
 */
final class Partner {

	/**
	 * Taxonomy slug.
	 */
	public const TAXONOMY = 'lw_relink_partner';

	/**
	 * Term meta: comma/newline-separated domains.
	 */
	public const META_DOMAINS = '_lw_partner_domains';

	/**
	 * Term meta: affiliate URL suffix (query string).
	 */
	public const META_URL_SUFFIX = '_lw_partner_url_suffix';

	/**
	 * Register the taxonomy.
	 */
	public static function register(): void {
		$labels = [
			'name'              => _x( 'Partners', 'taxonomy general name', 'vs-relink' ),
			'singular_name'     => _x( 'Partner', 'taxonomy singular name', 'vs-relink' ),
			'search_items'      => __( 'Search Partners', 'vs-relink' ),
			'all_items'         => __( 'All Partners', 'vs-relink' ),
			'edit_item'         => __( 'Edit Partner', 'vs-relink' ),
			'update_item'       => __( 'Update Partner', 'vs-relink' ),
			'add_new_item'      => __( 'Add New Partner', 'vs-relink' ),
			'new_item_name'     => __( 'New Partner Name', 'vs-relink' ),
			'menu_name'         => __( 'Partners', 'vs-relink' ),
		];

		$args = [
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => true,
			'rewrite'           => false,
			'show_in_rest'      => true,
		];

		register_taxonomy( self::TAXONOMY, [ \Vs\ReLink\PostTypes\ReLink::POST_TYPE ], $args );
	}

	/**
	 * Get all partner terms with meta.
	 *
	 * @return array<int, array{term_id: int, name: string, slug: string, domains: string, url_suffix: string}>
	 */
	public static function get_all_with_meta(): array {
		$terms = get_terms(
			[
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$partners = [];
		foreach ( $terms as $term ) {
			$partners[] = [
				'term_id'    => (int) $term->term_id,
				'name'       => $term->name,
				'slug'       => $term->slug,
				'domains'    => (string) get_term_meta( $term->term_id, self::META_DOMAINS, true ),
				'url_suffix' => (string) get_term_meta( $term->term_id, self::META_URL_SUFFIX, true ),
			];
		}

		return $partners;
	}

	/**
	 * Resolve partner by slug or numeric ID.
	 */
	public static function resolve_term( string|int $partner ): ?\WP_Term {
		if ( is_numeric( $partner ) ) {
			$term = get_term( (int) $partner, self::TAXONOMY );
			return ( $term && ! is_wp_error( $term ) ) ? $term : null;
		}

		$term = get_term_by( 'slug', sanitize_title( (string) $partner ), self::TAXONOMY );
		return ( $term && ! is_wp_error( $term ) ) ? $term : null;
	}

	/**
	 * Parse domains meta into normalized host list.
	 *
	 * @return string[]
	 */
	public static function parse_domains( string $domains_raw ): array {
		$parts = preg_split( '/[\s,]+/', strtolower( trim( $domains_raw ) ) ) ?: [];
		$hosts = [];

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( $part === '' ) {
				continue;
			}
			$hosts[] = self::normalize_host( $part );
		}

		return array_values( array_unique( $hosts ) );
	}

	/**
	 * Normalize a host for comparison (strip www.).
	 */
	public static function normalize_host( string $host ): string {
		$host = strtolower( trim( $host ) );
		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}
}
