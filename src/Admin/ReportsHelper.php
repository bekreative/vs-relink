<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

/**
 * Builds admin reports URLs with active filters.
 */
final class ReportsHelper {

	/**
	 * Reports page slug.
	 */
	public const PAGE = 'vs-relink-reports';

	/**
	 * Build a filtered reports admin URL.
	 */
	public static function filter_url( ?int $link_id = null, ?int $group_id = null, int $days = 30 ): string {
		$args = [
			'post_type' => 'lw_relink',
			'page'      => self::PAGE,
			'days'      => max( 1, $days ),
		];

		if ( $link_id ) {
			$args['link_id'] = $link_id;
		}

		if ( $group_id ) {
			$args['group_id'] = $group_id;
		}

		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * Render a clickable click count link when count > 0.
	 */
	public static function render_click_link( int $count, int $link_id, int $days = 30, ?int $group_id = null ): string {
		$formatted = number_format_i18n( $count );

		if ( $count <= 0 || $link_id <= 0 ) {
			return '<span class="lwr-click-count lwr-click-count--zero">' . esc_html( $formatted ) . '</span>';
		}

		$url = esc_url( self::filter_url( $link_id, $group_id, $days ) );

		return '<a href="' . $url . '" class="lwr-click-count" title="' . esc_attr__( 'View click stats', 'vs-relink' ) . '">' . esc_html( $formatted ) . '</a>';
	}
}
