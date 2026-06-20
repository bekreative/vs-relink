<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\PostTypes\ReLink;

/**
 * Custom native link editor (replaces block editor screen).
 */
final class LinkEditorPage {

	public const PAGE_SLUG = 'vs-relink-edit';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'register_page' ] );
		add_action( 'admin_post_lw_relink_save_link', [ self::class, 'handle_save' ] );
		add_action( 'load-post-new.php', [ self::class, 'redirect_new_post' ] );
		add_action( 'load-post.php', [ self::class, 'redirect_edit_post' ] );
		add_filter( 'get_edit_post_link', [ self::class, 'filter_edit_link' ], 10, 3 );
		add_filter( 'use_block_editor_for_post_type', [ self::class, 'disable_block_editor' ], 10, 2 );
		add_filter( 'post_row_actions', [ self::class, 'row_actions' ], 10, 2 );
	}

	/**
	 * Hidden submenu for the custom editor.
	 */
	public static function register_page(): void {
		add_submenu_page(
			'',
			__( 'Edit ReLink', 'vs-relink' ),
			__( 'Edit ReLink', 'vs-relink' ),
			'edit_posts',
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}

	/**
	 * Disable Gutenberg for ReLinks.
	 *
	 * @param bool   $use       Whether to use block editor.
	 * @param string $post_type Post type.
	 */
	public static function disable_block_editor( bool $use, string $post_type ): bool {
		if ( ReLink::POST_TYPE === $post_type ) {
			return false;
		}

		return $use;
	}

	/**
	 * Redirect post-new.php to custom editor.
	 */
	public static function redirect_new_post(): void {
		if ( ( $_GET['post_type'] ?? '' ) !== ReLink::POST_TYPE ) {
			return;
		}

		$post_id = wp_insert_post(
			[
				'post_type'   => ReLink::POST_TYPE,
				'post_status' => 'auto-draft',
				'post_title'  => '',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return;
		}

		wp_safe_redirect( self::editor_url( (int) $post_id ) );
		exit;
	}

	/**
	 * Redirect post.php to custom editor.
	 */
	public static function redirect_edit_post(): void {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $post_id <= 0 || get_post_type( $post_id ) !== ReLink::POST_TYPE ) {
			return;
		}

		wp_safe_redirect( self::editor_url( $post_id ) );
		exit;
	}

	/**
	 * Custom edit URL for list table and row actions.
	 *
	 * @param string  $link    Default link.
	 * @param int     $post_id Post ID.
	 * @param string  $context Link context.
	 */
	public static function filter_edit_link( string $link, int $post_id, string $context ): string {
		if ( get_post_type( $post_id ) === ReLink::POST_TYPE && $context === 'display' ) {
			return self::editor_url( $post_id );
		}

		return $link;
	}

	/**
	 * Ensure row actions use custom editor.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param \WP_Post              $post    Post object.
	 * @return array<string, string>
	 */
	public static function row_actions( array $actions, $post ): array {
		if ( $post->post_type !== ReLink::POST_TYPE ) {
			return $actions;
		}

		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( self::editor_url( (int) $post->ID ) ),
				esc_attr(
					sprintf(
						/* translators: %s: post title */
						__( 'Edit &#8220;%s&#8221;', 'vs-relink' ),
						$post->post_title
					)
				),
				__( 'Edit', 'vs-relink' )
			);
		}

		return $actions;
	}

	/**
	 * Editor page URL.
	 */
	public static function editor_url( int $post_id = 0 ): string {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		if ( $post_id > 0 ) {
			$url = add_query_arg( 'link_id', $post_id, $url );
		}

		return $url;
	}

	/**
	 * Render editor page.
	 */
	public static function render_page(): void {
		$link_id = isset( $_GET['link_id'] ) ? (int) $_GET['link_id'] : 0;

		if ( $link_id <= 0 ) {
			$post_id = wp_insert_post(
				[
					'post_type'   => ReLink::POST_TYPE,
					'post_status' => 'auto-draft',
					'post_title'  => '',
				],
				true
			);

			if ( is_wp_error( $post_id ) ) {
				wp_die( esc_html( $post_id->get_error_message() ) );
			}

			wp_safe_redirect( self::editor_url( (int) $post_id ) );
			exit;
		}

		$post = get_post( $link_id );
		if ( ! $post || $post->post_type !== ReLink::POST_TYPE ) {
			wp_die( esc_html__( 'Link not found.', 'vs-relink' ) );
		}

		if ( ! current_user_can( 'edit_post', $link_id ) ) {
			wp_die( esc_html__( 'You cannot edit this link.', 'vs-relink' ) );
		}

		$saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
		require VS_RELINK_PATH . 'src/Admin/Views/link-editor-view.php';
	}

	/**
	 * Handle form save.
	 */
	public static function handle_save(): void {
		$post_id = isset( $_POST['link_id'] ) ? (int) $_POST['link_id'] : 0;
		check_admin_referer( 'lw_relink_save_link_' . $post_id );

		if ( $post_id <= 0 ) {
			wp_die( esc_html__( 'Invalid link.', 'vs-relink' ) );
		}

		$result = LinkEditorService::save( $post_id, wp_unslash( $_POST ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$redirect = add_query_arg(
			[
				'page'    => self::PAGE_SLUG,
				'link_id' => $post_id,
				'saved'   => '1',
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
