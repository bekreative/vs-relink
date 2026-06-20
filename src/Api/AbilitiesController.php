<?php

declare(strict_types=1);

namespace Vs\ReLink\Api;

use WP_REST_Request;
use WP_REST_Response;
use Vs\ReLink\Admin\LinkChecker;
use Vs\ReLink\Admin\DataService;
use Vs\ReLink\Core\LinkFactory;
use Vs\ReLink\Stats\StatsRepository;

/**
 * Exposes plugin functionalities via the WordPress Abilities API pattern.
 */
final class AbilitiesController {

	private const NAMESPACE = 'wp-abilities/v1';
	private const BASE      = 'abilities';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the REST routes.
	 */
	public function register_routes(): void {
		$abilities = [
			'relink/health-check' => [ $this, 'ability_health_check' ],
			'relink/get-stats'    => [ $this, 'ability_get_stats' ],
			'relink/export'       => [ $this, 'ability_export' ],
			'relink/create-link'  => [ $this, 'ability_create_link' ],
		];

		foreach ( $abilities as $name => $callback ) {
			register_rest_route( self::NAMESPACE, '/' . self::BASE . '/' . $name . '/run', [
				'methods'             => 'POST',
				'callback'            => $callback,
				'permission_callback' => [ $this, 'check_permission' ],
			] );
		}
	}

	/**
	 * Check if the user has permission to run abilities.
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Ability: Run health check or get latest results.
	 */
	public function ability_health_check( WP_REST_Request $request ): WP_REST_Response {
		$ids    = LinkChecker::get_all_relink_ids();
		$results = [];

		foreach ( $ids as $id ) {
			$results[] = LinkChecker::check_link( (int) $id );
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => [
				'total_checked' => count( $results ),
				'results'       => $results,
			],
		], 200 );
	}

	/**
	 * Ability: Get detailed statistics.
	 * 
	 * Input: { "days": 30, "link_id": 0, "group_id": 0 }
	 */
	public function ability_get_stats( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?? [];
		$days   = (int) ( $params['days'] ?? 30 );
		$link_id = (int) ( $params['link_id'] ?? 0 );
		$group_id = (int) ( $params['group_id'] ?? 0 );

		$stats_repo = new StatsRepository();
		
		return new WP_REST_Response( [
			'success' => true,
			'data'    => [
				'trend' => $stats_repo->get_click_trend( $days, $link_id, $group_id ),
				'top'   => $stats_repo->get_top_links( 10 ),
			],
		], 200 );
	}

	/**
	 * Ability: Export all links as JSON.
	 */
	public function ability_export( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( [
			'success' => true,
			'data'    => DataService::export_to_json(),
		], 200 );
	}

	/**
	 * Ability: Create affiliate link from original URL + partner.
	 *
	 * Input: { "original_url": "...", "partner": "slug", "short_slug": "", "title": "", "redirect_type": "301" }
	 */
	public function ability_create_link( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?? [];

		$result = LinkFactory::create(
			[
				'original_url'  => $params['original_url'] ?? '',
				'partner'       => $params['partner'] ?? '',
				'short_slug'    => $params['short_slug'] ?? '',
				'title'         => $params['title'] ?? '',
				'redirect_type' => $params['redirect_type'] ?? '301',
				'tracking'      => ! isset( $params['tracking'] ) || (bool) $params['tracking'],
				'nofollow'      => ! empty( $params['nofollow'] ),
				'sponsored'     => ! empty( $params['sponsored'] ),
				'forward_params' => ! empty( $params['forward_params'] ),
				'keywords'      => $params['keywords'] ?? '',
			]
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => $result->get_error_message(),
				],
				400
			);
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $result,
			],
			200
		);
	}
}
