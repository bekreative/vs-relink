<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

/**
 * Allowlisted query forwarding that does not replace affiliate params already on the target.
 */
final class ForwardParams {

	/**
	 * Keep only safe tracking params from a query-string bag.
	 *
	 * @param array<mixed> $query
	 * @return array<string, string>
	 */
	public static function select( array $query ): array {
		$extra = apply_filters( 'vs_relink_forward_param_keys', array( 'gclid', 'fbclid' ) );
		$exact = array();
		if ( is_array( $extra ) ) {
			foreach ( $extra as $key ) {
				$key = strtolower( (string) $key );
				if ( preg_match( '/^[a-z0-9_]{1,40}$/', $key ) ) {
					$exact[ $key ] = true;
				}
			}
		}

		$selected = array();
		foreach ( $query as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$name = strtolower( (string) $key );
			if ( ! self::is_allowed_name( $name, $exact ) ) {
				continue;
			}
			$clean = sanitize_text_field( (string) $value );
			if ( '' === $clean ) {
				continue;
			}
			$selected[ $name ] = substr( $clean, 0, 150 );
		}

		return $selected;
	}

	/**
	 * Append selected params. Keys already present on the target are left unchanged.
	 *
	 * @param array<mixed> $query
	 */
	public static function append( string $url, array $query ): string {
		$selected = self::select( $query );
		if ( array() === $selected || ! UrlGuard::is_http_url( $url ) ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $url;
		}

		$existing = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $existing );
			if ( ! is_array( $existing ) ) {
				$existing = array();
			}
		}

		$existing_keys = array();
		foreach ( array_keys( $existing ) as $key ) {
			$existing_keys[ strtolower( (string) $key ) ] = true;
		}

		$add = array();
		foreach ( $selected as $key => $value ) {
			if ( isset( $existing_keys[ $key ] ) ) {
				continue;
			}
			$add[ $key ] = $value;
		}

		if ( array() === $add ) {
			return $url;
		}

		$query_string = isset( $parts['query'] ) && '' !== $parts['query']
			? $parts['query'] . '&' . http_build_query( $add, '', '&', PHP_QUERY_RFC3986 )
			: http_build_query( $add, '', '&', PHP_QUERY_RFC3986 );

		$rebuilt = strtolower( (string) $parts['scheme'] ) . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$rebuilt .= ':' . (int) $parts['port'];
		}
		$rebuilt .= $parts['path'] ?? '/';
		$rebuilt .= '?' . $query_string;
		if ( isset( $parts['fragment'] ) && '' !== $parts['fragment'] ) {
			$rebuilt .= '#' . $parts['fragment'];
		}

		return UrlGuard::is_http_url( $rebuilt ) ? $rebuilt : $url;
	}

	/**
	 * @param array<string, bool> $exact
	 */
	private static function is_allowed_name( string $name, array $exact ): bool {
		if ( isset( $exact[ $name ] ) ) {
			return true;
		}

		return (bool) preg_match( '/^utm_[a-z0-9_]{1,32}$/', $name );
	}
}
