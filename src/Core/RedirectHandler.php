<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

use Vs\ReLink\PostTypes\ReLink;

/**
 * Handles link redirection logic.
 */
final class RedirectHandler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'handle_redirection' ], 5 );
	}

	/**
	 * Handle the redirection.
	 *
	 * @return void
	 */
	public function handle_redirection(): void {
		if ( is_singular( ReLink::POST_TYPE ) ) {
			$this->execute_redirection( get_queried_object_id() );
			return;
		}

		if ( ! is_404() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$parsed_path = parse_url( $request_uri, PHP_URL_PATH );
		$path        = is_string( $parsed_path ) ? trim( $parsed_path, '/' ) : '';

		$base = get_option( 'vs_relink_base', 're' );
		if ( $base && str_starts_with( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) + 1 );
		}

		$link = get_page_by_path( $path, OBJECT, ReLink::POST_TYPE );
		if ( $link ) {
			$this->execute_redirection( (int) $link->ID );
		}
	}

	/**
	 * Execute the redirection for a specific ID.
	 */
	private function execute_redirection( int $link_id ): void {
		$target_url      = (string) get_post_meta( $link_id, '_vs_relink_target_url', true );
		$redirect_type   = UrlGuard::redirect_code( get_post_meta( $link_id, '_vs_relink_type', true ) ?: 301 );
		$forward_params  = get_post_meta( $link_id, '_vs_relink_forward_params', true ) === 'yes';
		$enable_tracking = get_post_meta( $link_id, '_vs_relink_tracking', true ) !== 'no';

		if ( $target_url === '' || ! UrlGuard::is_http_url( $target_url ) ) {
			return;
		}

		if ( $forward_params && ! empty( $_GET ) ) {
			$target_url = ForwardParams::append( $target_url, wp_unslash( $_GET ) );
		}

		if ( ! UrlGuard::is_http_url( $target_url ) ) {
			return;
		}

		if ( $enable_tracking ) {
			$this->record_click( $link_id );
		}

		wp_redirect( $target_url, $redirect_type, 'LW-ReLink' );
		exit;
	}

	/**
	 * Record a click in the database.
	 */
	private function record_click( int $link_id ): void {
		global $wpdb;

		$is_bot       = $this->is_bot();
		$exclude_bots = get_option( 'vs_relink_exclude_bots', '1' ) === '1';

		if ( $is_bot && $exclude_bots ) {
			return;
		}

		$ip = ClientIp::from_request();
		if ( ! ClickThrottle::allow( $link_id, $ip ) ) {
			return;
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$referer = substr( $referer, 0, 500 );
		$ua      = substr( $ua, 0, 500 );

		$wpdb->insert(
			\Vs\ReLink\Database\Schema::get_clicks_table(),
			[
				'link_id'    => $link_id,
				'ip_address' => substr( $ip, 0, 45 ),
				'referer'    => $referer,
				'user_agent' => $ua,
				'is_bot'     => $is_bot ? 1 : 0,
			],
			[ '%d', '%s', '%s', '%s', '%d' ]
		);

		WebhookService::trigger(
			$link_id,
			[
				'ip'      => $ip,
				'referer' => $referer,
				'ua'      => $ua,
				'is_bot'  => $is_bot,
			]
		);
	}

	/**
	 * Simple bot detection.
	 */
	private function is_bot(): bool {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		if ( $ua === '' ) {
			return true;
		}

		$bots = [ 'bot', 'crawl', 'slurp', 'spider', 'mediapartners', 'chrome-lighthouse' ];
		foreach ( $bots as $bot ) {
			if ( stripos( $ua, $bot ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
