<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\Core\ClientIp;
use Vs\ReLink\Core\UrlGuard;

/**
 * Sanitizers for plugin options registered with the Settings API.
 */
final class SettingsSanitizer {

	public static function sanitize_base( mixed $value ): string {
		$base = sanitize_title( (string) $value );
		if ( strlen( $base ) > 50 ) {
			$base = substr( $base, 0, 50 );
		}

		return $base;
	}

	public static function sanitize_flag( mixed $value ): string {
		return ( $value === '1' || $value === 1 || $value === true ) ? '1' : '0';
	}

	/**
	 * Unknown values keep logs forever (0). Deletion only happens for an explicit positive choice.
	 */
	public static function sanitize_retention( mixed $value ): string {
		$allowed = [ '0', '30', '90', '180', '365' ];
		$raw     = (string) (int) $value;

		return in_array( $raw, $allowed, true ) ? $raw : '0';
	}

	public static function sanitize_webhook_url( mixed $value ): string {
		$url = esc_url_raw( trim( (string) $value ) );
		if ( $url === '' || ! UrlGuard::is_http_url( $url ) ) {
			return '';
		}

		return $url;
	}

	public static function sanitize_webhook_secret( mixed $value ): string {
		$secret = trim( (string) $value );
		$secret = str_replace( [ "\r", "\n", "\0" ], '', $secret );
		$clear  = isset( $_POST['vs_relink_clear_webhook_secret'] ) && (string) wp_unslash( $_POST['vs_relink_clear_webhook_secret'] ) === '1';
		if ( $secret === '' && $clear ) {
			return '';
		}
		if ( $secret === '' ) {
			return (string) get_option( 'vs_relink_webhook_secret', '' );
		}
		if ( strlen( $secret ) > 255 ) {
			$secret = substr( $secret, 0, 255 );
		}

		return $secret;
	}

	public static function sanitize_trusted_proxies( mixed $value ): string {
		return ClientIp::sanitize_list( (string) $value );
	}

	public static function sanitize_create_rate( mixed $value ): int {
		$n = (int) $value;
		if ( $n < 0 ) {
			return 0;
		}
		if ( $n > 10000 ) {
			return 10000;
		}

		return $n;
	}
}
