<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

/**
 * HTTP(S) URL and redirect-code checks shared by create, import, and redirect.
 */
final class UrlGuard {

	/**
	 * @var int[]
	 */
	public const REDIRECT_CODES = array( 301, 302, 307 );

	/**
	 * True when the URL is an absolute http or https URL with a host.
	 */
	public static function is_http_url( string $url ): bool {
		$url = trim( $url );
		if ( '' === $url || preg_match( '/\s/', $url ) ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}

		$scheme = strtolower( (string) $parts['scheme'] );

		return 'http' === $scheme || 'https' === $scheme;
	}

	/**
	 * Allowlisted redirect status. Anything else becomes 301.
	 */
	public static function redirect_code( mixed $value ): int {
		$raw = trim( (string) $value );
		if ( ! preg_match( '/^(301|302|307)$/', $raw ) ) {
			return 301;
		}

		return (int) $raw;
	}
}
