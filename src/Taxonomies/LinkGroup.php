<?php

declare(strict_types=1);

namespace Vs\ReLink\Taxonomies;

/**
 * Custom Taxonomy registration for Link Groups (Folders).
 */
final class LinkGroup {

	/**
	 * Taxonomy slug.
	 */
	public const TAXONOMY = 'lw_link_group';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = [
			'name'              => _x( 'Link Groups', 'taxonomy general name', 'vs-relink' ),
			'singular_name'     => _x( 'Link Group', 'taxonomy singular name', 'vs-relink' ),
			'search_items'      => __( 'Search Link Groups', 'vs-relink' ),
			'all_items'         => __( 'All Link Groups', 'vs-relink' ),
			'parent_item'       => __( 'Parent Link Group', 'vs-relink' ),
			'parent_item_colon' => __( 'Parent Link Group:', 'vs-relink' ),
			'edit_item'         => __( 'Edit Link Group', 'vs-relink' ),
			'update_item'       => __( 'Update Link Group', 'vs-relink' ),
			'add_new_item'      => __( 'Add New Link Group', 'vs-relink' ),
			'new_item_name'     => __( 'New Link Group Name', 'vs-relink' ),
			'menu_name'         => __( 'Groups (Folders)', 'vs-relink' ),
		];

		$args = [
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 're-group' ],
			'show_in_rest'      => true,
		];

		register_taxonomy( self::TAXONOMY, [ \Vs\ReLink\PostTypes\ReLink::POST_TYPE ], $args );
	}
}
