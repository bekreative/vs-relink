<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Database\Schema;
use Vs\ReLink\Core\WebhookService;

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
		global $wp_query;

		// If it's already identified as a ReLink, proceed.
		if ( is_singular( ReLink::POST_TYPE ) ) {
			$this->execute_redirection( get_queried_object_id() );
			return;
		}

		// If it's a 404, check if the path matches a ReLink hierarchy.
		if ( is_404() ) {
			$path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
			
			// Remove base if present
			$base = get_option( 'lw_relink_base', 're' );
			if ( $base && str_starts_with( $path, $base . '/' ) ) {
				$path = substr( $path, strlen( $base ) + 1 );
			}

			$link = get_page_by_path( $path, OBJECT, ReLink::POST_TYPE );
			if ( $link ) {
				$this->execute_redirection( $link->ID );
			}
		}
	}

	/**
	 * Execute the redirection for a specific ID.
	 * 
	 * @param int $link_id The ReLink post ID.
	 * @return void
	 */
	private function execute_redirection( int $link_id ): void {
		$target_url    = get_post_meta( $link_id, '_lw_relink_target_url', true );
		$redirect_type = (int) get_post_meta( $link_id, '_lw_relink_type', true ) ?: 301;
		$forward_params = get_post_meta( $link_id, '_lw_relink_forward_params', true ) === 'yes';
		$enable_tracking = get_post_meta( $link_id, '_lw_relink_tracking', true ) !== 'no';

		if ( empty( $target_url ) ) {
			return;
		}

		// Forward parameters if enabled.
		if ( $forward_params && ! empty( $_GET ) ) {
			$target_url = add_query_arg( $_GET, $target_url );
		}

		// Record the click.
		if ( $enable_tracking ) {
			$this->record_click( $link_id );
		}

		// Execute redirect correctly based on type.
		wp_redirect( $target_url, $redirect_type, 'LW-ReLink' );
		exit;
	}

	/**
	 * Record a click in the database.
	 *
	 * @param int $link_id The link post ID.
	 * @return void
	 */
	private function record_click( int $link_id ): void {
		global $wpdb;

		$is_bot = $this->is_bot();
		$exclude_bots = get_option( 'lw_relink_exclude_bots', '1' ) === '1';

		if ( $is_bot && $exclude_bots ) {
			return; // Skip bot if setting is active
		}

		$ip      = $this->get_ip();
		$referer = $_SERVER['HTTP_REFERER'] ?? '';
		$ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';

		$wpdb->insert(
			Schema::get_clicks_table(),
			[
				'link_id'    => $link_id,
				'ip_address' => $ip,
				'referer'    => $referer,
				'user_agent' => $ua,
				'is_bot'     => $is_bot ? 1 : 0,
			],
			[ '%d', '%s', '%s', '%s', '%d' ]
		);

		// Trigger Webhook
		WebhookService::trigger( $link_id, [
			'ip'      => $ip,
			'referer' => $referer,
			'ua'      => $ua,
			'is_bot'  => $is_bot,
		] );
	}

	/**
	 * Simple bot detection.
	 *
	 * @return bool
	 */
	private function is_bot(): bool {
		$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
		if ( empty( $ua ) ) {
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

	/**
	 * Get the visitor's IP address.
	 *
	 * @return string
	 */
	private function get_ip(): string {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			return $_SERVER['HTTP_CLIENT_IP'];
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}
}
