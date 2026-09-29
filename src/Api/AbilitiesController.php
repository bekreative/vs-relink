<?php

declare(strict_types=1);

namespace Vs\ReLink\Api;

use WP_REST_Request;
use WP_REST_Response;
use Vs\ReLink\Admin\LinkChecker;
use Vs\ReLink\Admin\DataService;
use Vs\ReLink\Core\AuditLog;
use Vs\ReLink\Core\Capabilities;
use Vs\ReLink\Core\CreateRateLimit;
use Vs\ReLink\Core\LinkFactory;
use Vs\ReLink\Core\UrlGuard;
use Vs\ReLink\Stats\StatsRepository;

/**
 * Exposes plugin functionalities via the WordPress Abilities API pattern.
 */
final class AbilitiesController {

	private const NAMESPACE = 'wp-abilities/v1';

	private const BASE = 'abilities';

	private const HEALTH_LIMIT = 10;

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
		$this->route(
			'relink/health-check',
			[ $this, 'ability_health_check' ],
			[ $this, 'can_manage_options' ],
			[
				'limit'  => [
					'description'       => __( 'Maximum links to check in this call.', 'vs-relink' ),
					'type'              => 'integer',
					'default'           => self::HEALTH_LIMIT,
					'sanitize_callback' => [ $this, 'sanitize_health_limit' ],
				],
				'offset' => [
					'description'       => __( 'Number of published links to skip.', 'vs-relink' ),
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => [ $this, 'sanitize_offset' ],
				],
			]
		);

		$this->route(
			'relink/get-stats',
			[ $this, 'ability_get_stats' ],
			[ $this, 'can_use_bot_api' ],
			[
				'days'     => [
					'type'              => 'integer',
					'default'           => 30,
					'sanitize_callback' => [ $this, 'sanitize_days' ],
				],
				'link_id'  => [
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				],
				'group_id' => [
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				],
			]
		);

		$this->route( 'relink/export', [ $this, 'ability_export' ], [ $this, 'can_manage_options' ] );

		$write_args = $this->link_args( false );
		$this->route( 'relink/create-link', [ $this, 'ability_create_link' ], [ $this, 'can_use_bot_api' ], $write_args );
		$this->route( 'relink/preview-link', [ $this, 'ability_preview_link' ], [ $this, 'can_use_bot_api' ], $write_args );
		$this->route( 'relink/lookup-link', [ $this, 'ability_lookup_link' ], [ $this, 'can_use_bot_api' ], $this->link_args( true ) );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function route( string $name, callable $callback, callable $permission, array $args = [] ): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/' . $name . '/run',
			[
				'methods'             => 'POST',
				'callback'            => $callback,
				'permission_callback' => $permission,
				'args'                => $args,
			]
		);
	}

	public function can_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}

	public function can_use_bot_api(): bool {
		return Capabilities::current_user_may_use_bot_api();
	}

	public function sanitize_health_limit( mixed $value ): int {
		$limit = (int) $value;
		if ( $limit < 1 ) {
			return self::HEALTH_LIMIT;
		}

		return min( self::HEALTH_LIMIT, $limit );
	}

	public function sanitize_offset( mixed $value ): int {
		$offset = (int) $value;
		if ( $offset < 0 ) {
			return 0;
		}

		return min( 100000, $offset );
	}

	public function sanitize_days( mixed $value ): int {
		$days = (int) $value;
		if ( $days < 1 ) {
			return 1;
		}

		return min( 366, $days );
	}

	/**
	 * Ability: Run a bounded health check page.
	 */
	public function ability_health_check( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$lock    = 'vsrl_health_' . $user_id;
		if ( $user_id > 0 && get_transient( $lock ) ) {
			return $this->error( 'rate_limited', __( 'Health check was run recently. Try again shortly.', 'vs-relink' ), 429 );
		}
		if ( $user_id > 0 ) {
			set_transient( $lock, 1, 30 );
		}

		$limit  = (int) $request->get_param( 'limit' );
		$offset = (int) $request->get_param( 'offset' );
		if ( $limit < 1 ) {
			$limit = self::HEALTH_LIMIT;
		}
		$limit = min( self::HEALTH_LIMIT, $limit );

		$ids = LinkChecker::get_all_relink_ids();
		if ( ! is_array( $ids ) ) {
			$ids = [];
		}
		$total = count( $ids );
		$page  = array_slice( $ids, $offset, $limit );

		$results = [];
		foreach ( $page as $id ) {
			$results[] = LinkChecker::check_link( (int) $id, 3 );
		}

		$next = $offset + count( $page );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'total'         => $total,
					'offset'        => $offset,
					'limit'         => $limit,
					'checked'       => count( $results ),
					'next_offset'   => $next < $total ? $next : null,
					'total_checked' => count( $results ),
					'results'       => $results,
				],
			],
			200
		);
	}

	/**
	 * Ability: Get detailed statistics.
	 */
	public function ability_get_stats( WP_REST_Request $request ): WP_REST_Response {
		$days     = (int) $request->get_param( 'days' );
		$link_id  = (int) $request->get_param( 'link_id' );
		$group_id = (int) $request->get_param( 'group_id' );
		if ( $days < 1 ) {
			$days = 30;
		}

		$stats_repo = new StatsRepository();

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'trend' => $stats_repo->get_click_trend( $days, $link_id, $group_id ),
					'top'   => $stats_repo->get_top_links( 10 ),
				],
			],
			200
		);
	}

	/**
	 * Ability: Export all links as JSON. Administrators only.
	 */
	public function ability_export( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => DataService::export_to_json(),
			],
			200
		);
	}

	/**
	 * Ability: Create affiliate link from original URL + partner.
	 */
	public function ability_create_link( WP_REST_Request $request ): WP_REST_Response {
		if ( ! CreateRateLimit::allow( get_current_user_id() ) ) {
			$response = $this->error( 'rate_limited', __( 'Create rate limit reached. Try again later.', 'vs-relink' ), 429 );
			$response->header( 'Retry-After', '3600' );

			return $response;
		}

		$args   = $this->link_payload( $request );
		$result = LinkFactory::create( $args );
		if ( is_wp_error( $result ) ) {
			return $this->from_error( $result );
		}

		AuditLog::record_create( $result, (string) $args['original_url'], 'rest' );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $result,
			],
			200
		);
	}

	/**
	 * Ability: Dry-run. Same resolution as create, nothing is saved.
	 */
	public function ability_preview_link( WP_REST_Request $request ): WP_REST_Response {
		$result = LinkFactory::preview( $this->link_payload( $request ) );
		if ( is_wp_error( $result ) ) {
			return $this->from_error( $result );
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $result,
			],
			200
		);
	}

	/**
	 * Ability: Look up an existing short URL. 404 when the pair is new.
	 */
	public function ability_lookup_link( WP_REST_Request $request ): WP_REST_Response {
		$result = LinkFactory::lookup( $this->link_payload( $request ) );
		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data()['status'] ?? 400;

			return $this->from_error( $result, is_int( $status ) ? $status : 400 );
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $result,
			],
			200
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function link_args( bool $partner_required ): array {
		return [
			'original_url'   => [
				'description'       => __( 'Clean product URL (http or https).', 'vs-relink' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => static function ( $value ): string {
					return esc_url_raw( (string) $value );
				},
			],
			'partner'        => [
				'description'       => __( 'Slug of an existing partner. Required when the domain is not already mapped.', 'vs-relink' ),
				'type'              => 'string',
				'required'          => $partner_required,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'short_slug'     => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_title',
			],
			'title'          => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'redirect_type'  => [
				'description'       => __( '301, 302, or 307. Other values are stored as 301.', 'vs-relink' ),
				'type'              => 'string',
				'default'           => '301',
				'sanitize_callback' => static function ( $value ): string {
					return (string) UrlGuard::redirect_code( $value );
				},
			],
			'tracking'       => [
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'nofollow'       => [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'sponsored'      => [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'forward_params' => [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'keywords'       => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function link_payload( WP_REST_Request $request ): array {
		return [
			'original_url'   => (string) $request->get_param( 'original_url' ),
			'partner'        => (string) ( $request->get_param( 'partner' ) ?? '' ),
			'short_slug'     => (string) ( $request->get_param( 'short_slug' ) ?? '' ),
			'title'          => (string) ( $request->get_param( 'title' ) ?? '' ),
			'redirect_type'  => (string) ( $request->get_param( 'redirect_type' ) ?? '301' ),
			'tracking'       => $request->get_param( 'tracking' ) === null ? true : (bool) $request->get_param( 'tracking' ),
			'nofollow'       => (bool) $request->get_param( 'nofollow' ),
			'sponsored'      => (bool) $request->get_param( 'sponsored' ),
			'forward_params' => (bool) $request->get_param( 'forward_params' ),
			'keywords'       => (string) ( $request->get_param( 'keywords' ) ?? '' ),
		];
	}

	private function from_error( \WP_Error $error, int $status = 400 ): WP_REST_Response {
		if ( $error->get_error_code() === 'not_found' ) {
			$status = 404;
		}

		return $this->error( $error->get_error_code(), $error->get_error_message(), $status );
	}

	private function error( string $code, string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success' => false,
				'code'    => $code,
				'message' => $message,
			],
			$status
		);
	}
}
