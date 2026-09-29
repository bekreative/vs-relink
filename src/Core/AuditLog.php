<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

/**
 * Small rolling log of bot/API link creates. Not a click log.
 */
final class AuditLog {

	public const OPTION = 'vs_relink_create_audit';

	private const MAX_ENTRIES = 100;

	/**
	 * @param array<string, mixed> $result LinkFactory::create() success payload.
	 */
	public static function record_create( array $result, string $original_url, string $source ): void {
		$entry = array(
			'time'         => gmdate( 'c' ),
			'user_id'      => get_current_user_id(),
			'link_id'      => (int) ( $result['link_id'] ?? 0 ),
			'partner'      => sanitize_key( (string) ( $result['partner'] ?? '' ) ),
			'short_url'    => esc_url_raw( (string) ( $result['short_url'] ?? '' ) ),
			'original_url' => esc_url_raw( $original_url ),
			'existed'      => ! empty( $result['existed'] ),
			'source'       => sanitize_key( $source ),
		);

		$log = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION, $log, false );
	}
}
