<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

/**
 * Coalesce public click logging so one client cannot flood the clicks table or webhooks.
 *
 * Throttled requests still redirect. Disable with the vs_relink_click_throttle setting.
 */
final class ClickThrottle {

	/**
	 * Whether this click should be stored and forwarded to the webhook.
	 */
	public static function allow( int $link_id, string $ip ): bool {
		$enabled = get_option( 'vs_relink_click_throttle', '1' );
		if ( '1' !== $enabled && 1 !== $enabled && true !== $enabled ) {
			return true;
		}

		$window = (int) apply_filters( 'vs_relink_click_throttle_window', 60 );
		if ( $window < 1 ) {
			return true;
		}

		$ip_key   = 'vsrl_ip_' . md5( $ip );
		$pair_key = 'vsrl_ck_' . md5( $link_id . '|' . $ip );

		if ( get_transient( $pair_key ) ) {
			return false;
		}

		$ip_limit = (int) apply_filters( 'vs_relink_click_ip_limit', 30 );
		$ip_count = (int) get_transient( $ip_key );
		if ( $ip_limit > 0 && $ip_count >= $ip_limit ) {
			return false;
		}

		set_transient( $pair_key, 1, $window );
		set_transient( $ip_key, $ip_count + 1, $window );

		return true;
	}
}
