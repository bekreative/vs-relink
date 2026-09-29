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
	 * When a secret is stored, the raw JSON body is signed as
	 * X-VS-Relink-Signature: sha256=<hex hmac>.
	 *
	 * @param array<string, mixed> $data Click data (IP, referer, etc).
	 */
	public static function trigger( int $link_id, array $data ): void {
		$webhook_url = (string) get_option( 'vs_relink_webhook_url' );
		if ( $webhook_url === '' || ! UrlGuard::is_http_url( $webhook_url ) ) {
			return;
		}

		$payload = [
			'event'     => 'link_click',
			'link_id'   => $link_id,
			'title'     => get_the_title( $link_id ),
			'timestamp' => current_time( 'mysql' ),
			'visitor'   => $data,
		];

		$body = wp_json_encode( $payload );
		if ( ! is_string( $body ) || $body === '' ) {
			return;
		}

		$headers = [ 'Content-Type' => 'application/json' ];
		$secret  = (string) get_option( 'vs_relink_webhook_secret', '' );
		if ( $secret !== '' ) {
			$headers['X-VS-Relink-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		}

		wp_safe_remote_post(
			$webhook_url,
			[
				'method'      => 'POST',
				'timeout'     => 5,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => false,
				'headers'     => $headers,
				'body'        => $body,
			]
		);
	}
}
