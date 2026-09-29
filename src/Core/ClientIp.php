<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

/**
 * Client IP resolution that ignores forwarded headers unless the peer is trusted.
 */
final class ClientIp {

	/**
	 * IP for the current request. X-Forwarded-For is used only when REMOTE_ADDR is a trusted proxy.
	 */
	public static function from_request(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$xff    = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '';

		return self::resolve_address( $remote, $xff, self::trusted_cidrs() );
	}

	/**
	 * Resolve a client IP from an immediate peer and an optional XFF chain.
	 *
	 * @param string[] $cidrs Trusted proxy IPs or CIDRs. Empty means ignore XFF.
	 */
	public static function resolve_address( string $remote_addr, string $forwarded_for, array $cidrs ): string {
		$remote = self::normalize( $remote_addr );
		$cidrs  = self::parse_list( implode( "\n", $cidrs ) );

		if ( $remote === '' || $cidrs === [] || ! self::matches_any( $remote, $cidrs ) ) {
			return $remote;
		}

		$forwarded_for = substr( $forwarded_for, 0, 512 );
		$hops          = array_map( 'trim', explode( ',', $forwarded_for ) );

		for ( $i = count( $hops ) - 1; $i >= 0; $i-- ) {
			$candidate = self::normalize( $hops[ $i ] );
			if ( $candidate === '' ) {
				continue;
			}
			if ( ! self::matches_any( $candidate, $cidrs ) ) {
				return $candidate;
			}
		}

		return $remote;
	}

	/**
	 * @return string[]
	 */
	public static function trusted_cidrs(): array {
		$stored   = (string) get_option( 'vs_relink_trusted_proxies', '' );
		$filtered = apply_filters( 'vs_relink_trusted_proxies', self::parse_list( $stored ) );
		if ( ! is_array( $filtered ) ) {
			return [];
		}

		return self::parse_list( implode( "\n", array_map( 'strval', $filtered ) ) );
	}

	/**
	 * Keep only valid IPs and CIDRs, one per line.
	 */
	public static function sanitize_list( string $raw ): string {
		return implode( "\n", self::parse_list( $raw ) );
	}

	/**
	 * @return string[]
	 */
	public static function parse_list( string $raw ): array {
		$tokens = preg_split( '/\s+/', strtolower( trim( $raw ) ) ) ?: [];
		$valid  = [];

		foreach ( $tokens as $token ) {
			$cidr = self::normalize_cidr( $token );
			if ( $cidr === '' ) {
				continue;
			}
			$valid[] = $cidr;
			if ( count( $valid ) >= 64 ) {
				break;
			}
		}

		return array_values( array_unique( $valid ) );
	}

	public static function matches_any( string $ip, array $cidrs ): bool {
		foreach ( $cidrs as $cidr ) {
			if ( self::ip_in_cidr( $ip, (string) $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	public static function ip_in_cidr( string $ip, string $cidr ): bool {
		$ip_bin = inet_pton( $ip );
		if ( $ip_bin === false ) {
			return false;
		}

		$bits   = null;
		$subnet = $cidr;
		if ( str_contains( $cidr, '/' ) ) {
			[ $subnet, $bits_raw ] = explode( '/', $cidr, 2 );
			if ( ! preg_match( '/^\d+$/', $bits_raw ) ) {
				return false;
			}
			$bits = (int) $bits_raw;
		}

		$net_bin = inet_pton( $subnet );
		if ( $net_bin === false || strlen( $net_bin ) !== strlen( $ip_bin ) ) {
			return false;
		}

		$max_bits = strlen( $ip_bin ) * 8;
		if ( $bits === null ) {
			return $net_bin === $ip_bin;
		}
		if ( $bits < 0 || $bits > $max_bits ) {
			return false;
		}

		$bytes = intdiv( $bits, 8 );
		$rem   = $bits % 8;
		if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $net_bin, 0, $bytes ) ) {
			return false;
		}
		if ( $rem === 0 ) {
			return true;
		}

		$mask = ( 0xFF << ( 8 - $rem ) ) & 0xFF;

		return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $net_bin[ $bytes ] ) & $mask );
	}

	public static function normalize( string $ip ): string {
		$ip = trim( $ip );
		if ( $ip === '' || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return $ip;
	}

	private static function normalize_cidr( string $token ): string {
		$token = trim( $token );
		if ( $token === '' ) {
			return '';
		}

		if ( ! str_contains( $token, '/' ) ) {
			return self::normalize( $token );
		}

		[ $subnet, $bits_raw ] = explode( '/', $token, 2 );
		$subnet                = self::normalize( $subnet );
		if ( $subnet === '' || ! preg_match( '/^\d+$/', $bits_raw ) ) {
			return '';
		}

		$bits = (int) $bits_raw;
		$max  = str_contains( $subnet, ':' ) ? 128 : 32;
		if ( $bits < 0 || $bits > $max ) {
			return '';
		}

		return $subnet . '/' . $bits;
	}
}
