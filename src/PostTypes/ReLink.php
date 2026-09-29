<?php

declare(strict_types=1);

namespace Vs\ReLink\PostTypes;

use Vs\ReLink\Storage\Ids;

/**
 * Custom Post Type registration for ReLinks.
 */
final class ReLink {

	/**
	 * Post type slug (stable storage ID).
	 */
	public const POST_TYPE = Ids::POST_TYPE;

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'               => _x( 'ReLinks', 'post type general name', 'vs-relink' ),
			'singular_name'      => _x( 'ReLink', 'post type singular name', 'vs-relink' ),
			'menu_name'          => _x( 'ReLinks', 'admin menu', 'vs-relink' ),
			'name_admin_bar'     => _x( 'ReLink', 'add new on admin bar', 'vs-relink' ),
			'add_new'            => _x( 'Add New', 'relink', 'vs-relink' ),
			'add_new_item'       => __( 'Add New ReLink', 'vs-relink' ),
			'new_item'           => __( 'New ReLink', 'vs-relink' ),
			'edit_item'          => __( 'Edit ReLink', 'vs-relink' ),
			'view_item'          => __( 'View ReLink', 'vs-relink' ),
			'all_items'          => __( 'All ReLinks', 'vs-relink' ),
			'search_items'       => __( 'Search ReLinks', 'vs-relink' ),
			'not_found'          => __( 'No relinks found.', 'vs-relink' ),
			'not_found_in_trash' => __( 'No relinks found in Trash.', 'vs-relink' ),
		);

		$base = get_option( 'vs_relink_base', 're' );
		$base = $base ? (string) $base : '';

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => $base ? $base : self::POST_TYPE,
				'with_front' => false,
				'feeds'      => false,
			),
			'capability_type'    => array( 'relink', 'relinks' ),
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'hierarchical'       => true,
			'menu_position'      => 30,
			'menu_icon'          => 'dashicons-admin-links',
			'supports'           => array( 'title', 'page-attributes' ),
			'show_in_rest'       => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
