<?php
/**
 * Pure checks for URL scheme, redirect codes, forwarded params, and client IP.
 * Does not boot WordPress.
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions -- stubs WordPress; no WP_Filesystem or wp_parse_url implementation here.
 */

declare(strict_types=1);

function wp_parse_url( $url, $component = -1 ) {
	$parsed = parse_url( (string) $url, $component );

	return false === $parsed ? false : $parsed;
}

function apply_filters( $tag, $value ) {
	unset( $tag );

	return $value;
}

function sanitize_text_field( $str ) {
	$str = strip_tags( (string) $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );

	return trim( (string) $str );
}

require dirname( __DIR__ ) . '/src/Core/UrlGuard.php';
require dirname( __DIR__ ) . '/src/Core/ForwardParams.php';
require dirname( __DIR__ ) . '/src/Core/ClientIp.php';

use Vs\ReLink\Core\ClientIp;
use Vs\ReLink\Core\ForwardParams;
use Vs\ReLink\Core\UrlGuard;

$failed = 0;

function check( bool $condition, string $message ): void {
	global $failed;
	if ( $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
		echo "ok  {$message}\n";
		return;
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
	echo "FAIL {$message}\n";
	++$failed;
}

check( UrlGuard::is_http_url( 'https://okosotthon.bolt.hu/termek/pelda' ), 'https product URL allowed' );
check( UrlGuard::is_http_url( 'http://example.com/a' ), 'http URL allowed' );
check( ! UrlGuard::is_http_url( 'javascript:bad' ), 'javascript scheme rejected' );
check( ! UrlGuard::is_http_url( 'data:text/html,hi' ), 'data scheme rejected' );
check( ! UrlGuard::is_http_url( 'ftp://example.com/file' ), 'ftp scheme rejected' );
check( ! UrlGuard::is_http_url( '//example.com/a' ), 'protocol-relative URL rejected' );
check( ! UrlGuard::is_http_url( "https://example.com/a\nb" ), 'newline in URL rejected' );
check( UrlGuard::redirect_code( '302' ) === 302, '302 kept' );
check( UrlGuard::redirect_code( 307 ) === 307, '307 kept' );
check( UrlGuard::redirect_code( '200' ) === 301, 'unknown code falls back to 301' );
check( UrlGuard::redirect_code( '301abc' ) === 301, 'non-exact code falls back to 301' );

$selected = ForwardParams::select(
	array(
		'utm_campaign' => 'spring',
		'UTM_Source'   => '<b>evil</b>',
		'gclid'        => 'abc',
		'fbclid'       => 'fb',
		'ref'          => 'stolen',
		'random'       => '1',
		'utm_bad-key'  => 'nope',
	)
);
check( isset( $selected['utm_campaign'] ) && 'spring' === $selected['utm_campaign'], 'utm_campaign kept' );
check( isset( $selected['utm_source'] ) && 'evil' === $selected['utm_source'], 'utm value stripped to text' );
check( isset( $selected['gclid'] ) && isset( $selected['fbclid'] ), 'gclid and fbclid kept' );
check( ! isset( $selected['ref'] ) && ! isset( $selected['random'] ), 'affiliate and unknown keys dropped' );
check( ! isset( $selected['utm_bad-key'] ), 'utm key with a dash dropped' );

$appended = ForwardParams::append(
	'https://shop.test/p?ref=66&utm_source=affiliate',
	array(
		'utm_source'   => 'evil',
		'utm_campaign' => 'spring',
		'ref'          => 'stolen',
		'gclid'        => 'click-1',
		'random'       => '1',
	)
);
$parts    = parse_url( $appended );
parse_str( (string) ( $parts['query'] ?? '' ), $query );
check( ( $query['ref'] ?? '' ) === '66', 'existing affiliate ref not replaced' );
check( ( $query['utm_source'] ?? '' ) === 'affiliate', 'existing utm_source not replaced' );
check( ( $query['utm_campaign'] ?? '' ) === 'spring', 'new utm_campaign appended' );
check( ( $query['gclid'] ?? '' ) === 'click-1', 'gclid appended' );
check( ! isset( $query['random'] ), 'random query key not appended' );
check( str_starts_with( $appended, 'https://shop.test/p?' ), 'target path unchanged' );

check(
	ClientIp::resolve_address( '203.0.113.10', '198.51.100.20', array() ) === '203.0.113.10',
	'empty trusted list ignores XFF'
);
check(
	ClientIp::resolve_address( '203.0.113.10', '198.51.100.20', array( '10.0.0.0/8' ) ) === '203.0.113.10',
	'untrusted peer ignores XFF'
);
check(
	ClientIp::resolve_address( '10.0.0.5', '198.51.100.20, 10.0.0.4', array( '10.0.0.0/8' ) ) === '198.51.100.20',
	'trusted peer uses rightmost untrusted XFF hop'
);
check(
	ClientIp::resolve_address( '10.0.0.5', '1.2.3.4', array( '192.168.0.0/16' ) ) === '10.0.0.5',
	'peer outside the CIDR list ignores a spoofed XFF'
);
check( ClientIp::ip_in_cidr( '10.1.2.3', '10.1.2.0/24' ), 'ipv4 CIDR match' );
check( ! ClientIp::ip_in_cidr( '10.1.3.3', '10.1.2.0/24' ), 'ipv4 CIDR miss' );
check( ClientIp::ip_in_cidr( '2001:db8::1', '2001:db8::/32' ), 'ipv6 CIDR match' );
check( ClientIp::sanitize_list( "10.0.0.1/32\nnot-an-ip\n2001:db8::/32" ) === "10.0.0.1/32\n2001:db8::/32", 'invalid proxy lines dropped' );

if ( $failed > 0 ) {
	fwrite( STDERR, "{$failed} check(s) failed\n" );
	exit( 1 );
}

echo "all checks passed\n";
