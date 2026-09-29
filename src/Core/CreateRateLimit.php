<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

/**
 * Per-user cap on REST link creation. Zero disables the limit.
 */
final class CreateRateLimit {

	public static function allow( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return true;
		}

		$limit = (int) get_option( 'vs_relink_create_rate_limit', 120 );
		$limit = (int) apply_filters( 'vs_relink_create_rate_limit', $limit, $user_id );
		if ( $limit <= 0 ) {
			return true;
		}

		$key    = 'vsrl_mk_' . $user_id;
		$bucket = get_transient( $key );
		$count  = is_array( $bucket ) ? (int) ( $bucket['count'] ?? 0 ) : 0;
		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, [ 'count' => $count + 1 ], HOUR_IN_SECONDS );

		return true;
	}
}
