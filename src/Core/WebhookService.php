<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

/**
 * Handles outbound webhooks for click events.
 */
final class WebhookService {

	/**
	 * Trigger a webhook for a click event.
	 *
	 * @param int   $link_id Post ID of the link.
	 * @param array $data    Click data (IP, referer, etc).
	 * @return void
	 */
	public static function trigger( int $link_id, array $data ): void {
		$webhook_url = get_option( 'lw_relink_webhook_url' );
		if ( empty( $webhook_url ) ) {
			return;
		}

		$payload = [
			'event'     => 'link_click',
			'link_id'   => $link_id,
			'title'     => get_the_title( $link_id ),
			'timestamp' => current_time( 'mysql' ),
			'visitor'   => $data,
		];

		wp_safe_remote_post( $webhook_url, [
			'method'      => 'POST',
			'timeout'     => 5,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => false, // Non-blocking for performance
			'headers'     => [ 'Content-Type' => 'application/json' ],
			'body'        => json_encode( $payload ),
		] );
	}
}
