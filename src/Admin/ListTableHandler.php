<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\Admin\ReportsHelper;
use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Stats\StatsRepository;
use Vs\ReLink\Taxonomies\LinkGroup;
use Vs\ReLink\Taxonomies\Partner;

/**
 * Customizes the Relink list table columns.
 */
final class ListTableHandler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'manage_' . ReLink::POST_TYPE . '_posts_columns', [ $this, 'add_columns' ] );
		add_action( 'manage_' . ReLink::POST_TYPE . '_posts_custom_column', [ $this, 'render_columns' ], 10, 2 );
		add_filter( 'manage_edit-' . ReLink::POST_TYPE . '_sortable_columns', [ $this, 'sortable_columns' ] );
	}

	/**
	 * Add custom columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_columns( array $columns ): array {
		$new_columns = [];
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( $key === 'title' ) {
				$new_columns['short_url'] = __( 'Short URL', 'vs-relink' );
				$new_columns['partner'] = __( 'Partner', 'vs-relink' );
				$new_columns['target_url'] = __( 'Target URL', 'vs-relink' );
			}
		}
		$new_columns['clicks'] = __( 'Clicks', 'vs-relink' );
		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_columns( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'short_url':
				$url = get_permalink( $post_id );
				echo '<code>' . esc_html( str_replace( [ 'https://', 'http://' ], '', $url ) ) . '</code>';
				echo '<button type="button" class="button button-small vs-relink-copy" data-url="' . esc_url( $url ) . '" style="margin-left: 5px;"><span class="dashicons dashicons-admin-page" style="font-size: 14px; vertical-align: middle; margin-top: -2px;"></span></button>';
				break;

			case 'partner':
				$terms = wp_get_object_terms( $post_id, Partner::TAXONOMY );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					echo esc_html( $terms[0]->name );
				} else {
					echo '—';
				}
				break;

			case 'target_url':
				$target = get_post_meta( $post_id, '_lw_relink_target_url', true );
				echo '<a href="' . esc_url( $target ) . '" target="_blank" style="display:inline-block; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . esc_html( $target ) . '</a>';
				break;

			case 'clicks':
				$count = StatsRepository::get_total_clicks( $post_id );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				echo ReportsHelper::render_click_link( $count, $post_id );
				break;
		}
	}

	/**
	 * Make columns sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function sortable_columns( array $columns ): array {
		$columns['clicks'] = 'clicks';
		return $columns;
	}
}
