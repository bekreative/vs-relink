<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Taxonomies\LinkGroup;

/**
 * Handles custom URL structures for ReLinks.
 */
final class Permalinks {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'post_type_link', [ $this, 'filter_relink_link' ], 10, 2 );
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
	}

	/**
	 * Filter the ReLink permalink.
	 *
	 * @param string   $post_link The post permalink.
	 * @param \WP_Post $post      The post object.
	 * @return string
	 */
	public function filter_relink_link( string $post_link, \WP_Post $post ): string {
		if ( $post->post_type !== ReLink::POST_TYPE ) {
			return $post_link;
		}

		$base = get_option( 'lw_relink_base', 're' );
		
		if ( ! $base ) {
			// Remove the default 'lw_relink/' part from the URL for root-level
			$post_type_slug = ReLink::POST_TYPE;
			$post_link = str_replace( "/{$post_type_slug}/", "/", $post_link );
		}

		return $post_link;
	}

	/**
	 * Add custom rewrite rules for hierarchical support.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		$base = get_option( 'lw_relink_base', 're' );
		
		if ( $base ) {
			// Base-level hierarchical: domain.com/re/parent/child
			add_rewrite_rule(
				'^' . preg_quote($base) . '/(.+)/?$',
				'index.php?' . ReLink::POST_TYPE . '=$matches[1]',
				'top'
			);
		} else {
			// Root-level hierarchical: domain.com/parent/child
			// We Use 'bottom' to avoid breaking standard pages, 
			// but we ensure ReLink is checked correctly.
			add_rewrite_rule(
				'^(.+)/?$',
				'index.php?' . ReLink::POST_TYPE . '=$matches[1]',
				'bottom'
			);
		}
	}
}
