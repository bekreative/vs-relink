<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

use Vs\ReLink\PostTypes\ReLink;

/**
 * Handles automatic keyword-to-link replacement in content.
 */
final class AutoLinker {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'auto_link_content' ), 20 );
	}

	/**
	 * Automatically link keywords in content.
	 *
	 * @param string $content The content.
	 * @return string
	 */
	public function auto_link_content( string $content ): string {
		if ( ! is_main_query() || ! in_the_loop() || is_admin() ) {
			return $content;
		}

		$links = $this->get_all_keyword_links();
		if ( empty( $links ) ) {
			return $content;
		}

		foreach ( $links as $link ) {
			$keywords  = explode( ',', $link['keywords'] );
			$permalink = get_permalink( $link['ID'] );

			foreach ( $keywords as $keyword ) {
				$keyword = trim( $keyword );
				if ( empty( $keyword ) ) {
					continue;
				}

				// Regex: Match keyword but not inside tags or already linked
				// Uses negative lookahead and lookbehind for basic HTML safety
				$pattern     = '/(?!(?:[^<]+>|[^>]+<\/a>))\b(' . preg_quote( $keyword, '/' ) . ')\b/i';
				$replacement = '<a href="' . esc_url( $permalink ) . '" class="vs-relink-auto">$1</a>';

				$content = preg_replace( $pattern, $replacement, $content, 1 ); // Only replace first occurrence per keyword
			}
		}

		return $content;
	}

	/**
	 * Get all links that have keywords set.
	 *
	 * @return array
	 */
	private function get_all_keyword_links(): array {
		global $wpdb;

		// Table identifiers are $wpdb properties. The post type is a placeholder.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT p.ID, m.meta_value as keywords
			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND m.meta_key = '_vs_relink_keywords'
			AND m.meta_value != ''
		",
				ReLink::POST_TYPE
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
