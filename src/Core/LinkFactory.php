<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Taxonomies\LinkGroup;
use Vs\ReLink\Taxonomies\Partner;

/**
 * Shared link creation logic for admin, REST, CLI, and import.
 */
final class LinkFactory {

	public const IMPORT_BATCH_LIMIT = 200;

	/**
	 * Create or return existing ReLink for original URL + partner.
	 *
	 * @param array<string, mixed> $args Creation arguments.
	 * @return array{link_id: int, short_url: string, target_url: string, partner: string, existed: bool}|\WP_Error
	 */
	public static function create( array $args ) {
		if ( ! Capabilities::current_user_may_publish() ) {
			return new \WP_Error( 'forbidden', __( 'You cannot publish ReLinks.', 'vs-relink' ) );
		}

		$prepared = self::prepare( $args, true );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$term = $prepared['term'];
		if ( $prepared['existing_id'] ) {
			$existing_id = (int) $prepared['existing_id'];

			return array(
				'link_id'    => $existing_id,
				'short_url'  => ShortUrlHelper::get_full_url( $existing_id ),
				'target_url' => (string) get_post_meta( $existing_id, '_vs_relink_target_url', true ),
				'partner'    => $term->slug,
				'existed'    => true,
			);
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $prepared['title'],
				'post_name'   => $prepared['short_slug'],
				'post_type'   => ReLink::POST_TYPE,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, '_vs_relink_original_url', $prepared['normalized'] );
		update_post_meta( $post_id, '_vs_relink_target_url', $prepared['target_url'] );
		update_post_meta( $post_id, '_vs_relink_type', $prepared['redirect_type'] );
		update_post_meta( $post_id, '_vs_relink_tracking', ( $args['tracking'] ?? true ) ? 'yes' : 'no' );

		if ( ! empty( $args['nofollow'] ) ) {
			update_post_meta( $post_id, '_vs_relink_nofollow', 'yes' );
		}
		if ( ! empty( $args['sponsored'] ) ) {
			update_post_meta( $post_id, '_vs_relink_sponsored', 'yes' );
		}
		if ( ! empty( $args['forward_params'] ) ) {
			update_post_meta( $post_id, '_vs_relink_forward_params', 'yes' );
		}
		if ( ! empty( $args['keywords'] ) ) {
			update_post_meta( $post_id, '_vs_relink_keywords', sanitize_textarea_field( (string) $args['keywords'] ) );
		}

		wp_set_object_terms( $post_id, array( (int) $prepared['partner_term_id'] ), Partner::TAXONOMY, false );

		return array(
			'link_id'    => $post_id,
			'short_url'  => ShortUrlHelper::get_full_url( $post_id ),
			'target_url' => $prepared['target_url'],
			'partner'    => $term->slug,
			'existed'    => false,
		);
	}

	/**
	 * Preview link creation without saving (dry-run).
	 *
	 * @param array<string, mixed> $args Same as create().
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function preview( array $args ) {
		$prepared = self::prepare( $args, false );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$term = $prepared['term'];

		return array(
			'original_url'  => $prepared['normalized'],
			'target_url'    => $prepared['target_url'],
			'partner'       => $term->slug,
			'partner_name'  => $term->name,
			'short_slug'    => $prepared['short_slug'],
			'redirect_type' => $prepared['redirect_type'],
			'existed'       => (bool) $prepared['existing_id'],
			'link_id'       => $prepared['existing_id'] ? $prepared['existing_id'] : null,
		);
	}

	/**
	 * Find an existing short link for original URL + partner. Does not create one.
	 *
	 * @param array<string, mixed> $args original_url and partner.
	 * @return array{link_id: int, short_url: string, target_url: string, partner: string, original_url: string, existed: bool}|\WP_Error
	 */
	public static function lookup( array $args ) {
		$prepared = self::prepare( $args, true );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		if ( ! $prepared['existing_id'] ) {
			return new \WP_Error(
				'not_found',
				__( 'No ReLink found for that URL and partner.', 'vs-relink' ),
				array( 'status' => 404 )
			);
		}

		$existing_id = (int) $prepared['existing_id'];
		$term        = $prepared['term'];

		return array(
			'link_id'      => $existing_id,
			'short_url'    => ShortUrlHelper::get_full_url( $existing_id ),
			'target_url'   => (string) get_post_meta( $existing_id, '_vs_relink_target_url', true ),
			'partner'      => $term->slug,
			'original_url' => $prepared['normalized'],
			'existed'      => true,
		);
	}

	/**
	 * Import one JSON record. Partner rows go through create(); manual rows are sanitized here.
	 *
	 * @param array<string, mixed> $link
	 * @return array{link_id: int, existed: bool}|\WP_Error
	 */
	public static function import_record( array $link, string $find_url = '', string $replace_url = '' ) {
		if ( ! Capabilities::current_user_may_publish() ) {
			return new \WP_Error( 'forbidden', __( 'You cannot publish ReLinks.', 'vs-relink' ) );
		}

		$partner_slug = '';
		if ( isset( $link['partner'] ) && is_array( $link['partner'] ) ) {
			$partner_slug = sanitize_title( (string) ( $link['partner']['slug'] ?? '' ) );
		} elseif ( isset( $link['partner'] ) && is_string( $link['partner'] ) ) {
			$partner_slug = sanitize_title( $link['partner'] );
		}

		$original_url = isset( $link['original_url'] ) ? (string) $link['original_url'] : '';
		if ( '' !== $original_url && '' !== $partner_slug ) {
			$result = self::create(
				array(
					'original_url'   => $original_url,
					'partner'        => $partner_slug,
					'short_slug'     => $link['slug'] ?? '',
					'title'          => $link['title'] ?? '',
					'redirect_type'  => $link['redirect_type'] ?? '301',
					'tracking'       => ( $link['tracking'] ?? 'yes' ) !== 'no',
					'nofollow'       => ( $link['is_nofollow'] ?? '' ) === 'yes',
					'sponsored'      => ( $link['is_sponsored'] ?? '' ) === 'yes',
					'forward_params' => ( $link['forward_params'] ?? '' ) === 'yes',
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'link_id' => (int) $result['link_id'],
				'existed' => (bool) $result['existed'],
			);
		}

		$slug = self::sanitize_path_slug( (string) ( $link['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return new \WP_Error( 'invalid_slug', __( 'Import row is missing a slug.', 'vs-relink' ) );
		}

		$existing = get_page_by_path( $slug, OBJECT, ReLink::POST_TYPE );
		if ( $existing ) {
			return array(
				'link_id' => (int) $existing->ID,
				'existed' => true,
			);
		}

		$target_url = isset( $link['target_url'] ) ? (string) $link['target_url'] : '';
		if ( '' !== $find_url && '' !== $replace_url ) {
			$target_url = str_replace( $find_url, $replace_url, $target_url );
		}
		$target_url = esc_url_raw( $target_url );
		if ( ! UrlGuard::is_http_url( $target_url ) ) {
			return new \WP_Error( 'invalid_url', __( 'Import target must be an http(s) URL.', 'vs-relink' ) );
		}

		$parts        = explode( '/', $slug );
		$parent_id    = 0;
		$current_path = '';
		$final_name   = $slug;

		foreach ( $parts as $index => $part ) {
			$current_path .= ( '' !== $current_path ? '/' : '' ) . $part;
			if ( count( $parts ) - 1 === $index ) {
				$final_name = $part;
				break;
			}

			$parent = get_page_by_path( $current_path, OBJECT, ReLink::POST_TYPE );
			if ( $parent ) {
				$parent_id = (int) $parent->ID;
				continue;
			}

			$created = wp_insert_post(
				array(
					'post_title'  => ucfirst( $part ),
					'post_name'   => $part,
					'post_parent' => $parent_id,
					'post_type'   => ReLink::POST_TYPE,
					'post_status' => 'publish',
				),
				true
			);
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$parent_id = (int) $created;
		}

		$title   = sanitize_text_field( (string) ( $link['title'] ?? '' ) );
		$content = sanitize_textarea_field( (string) ( $link['description'] ?? '' ) );
		if ( '' === $title ) {
			$title = ucwords( str_replace( '-', ' ', $final_name ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $final_name,
				'post_parent'  => $parent_id,
				'post_type'    => ReLink::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;
		update_post_meta( $post_id, '_vs_relink_target_url', $target_url );
		update_post_meta( $post_id, '_vs_relink_type', (string) UrlGuard::redirect_code( $link['redirect_type'] ?? '301' ) );
		update_post_meta( $post_id, '_vs_relink_nofollow', self::flag_yes( $link['is_nofollow'] ?? 'no' ) );
		update_post_meta( $post_id, '_vs_relink_sponsored', self::flag_yes( $link['is_sponsored'] ?? 'no' ) );
		update_post_meta( $post_id, '_vs_relink_forward_params', self::flag_yes( $link['forward_params'] ?? 'no' ) );
		update_post_meta( $post_id, '_vs_relink_tracking', self::flag_yes( $link['tracking'] ?? 'yes', 'yes' ) );

		if ( ! empty( $link['group'] ) && is_array( $link['group'] ) ) {
			self::assign_imported_group( $post_id, $link['group'] );
		}

		return array(
			'link_id' => $post_id,
			'existed' => false,
		);
	}

	/**
	 * Shared resolution for preview, create, and lookup.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function prepare( array $args, bool $strict_partner_errors ) {
		$original_url = isset( $args['original_url'] ) ? esc_url_raw( (string) $args['original_url'] ) : '';
		if ( '' === $original_url || ! UrlGuard::is_http_url( $original_url ) ) {
			return new \WP_Error( 'missing_url', __( 'A valid http(s) original URL is required.', 'vs-relink' ) );
		}

		$normalized = PartnerUrlBuilder::normalize_original_url( $original_url );
		if ( ! UrlGuard::is_http_url( $normalized ) ) {
			return new \WP_Error( 'invalid_url', __( 'Original URL must use http or https.', 'vs-relink' ) );
		}

		$partner = $args['partner'] ?? '';
		$term    = null;

		if ( '' !== $partner && null !== $partner ) {
			$lookup = ( is_int( $partner ) || ( is_string( $partner ) && is_numeric( $partner ) ) ) ? (int) $partner : (string) $partner;
			$term   = Partner::resolve_term( $lookup );
			if ( ! $term ) {
				if ( $strict_partner_errors ) {
					return new \WP_Error( 'invalid_partner', __( 'Partner not found.', 'vs-relink' ) );
				}

				return new \WP_Error( 'missing_partner', __( 'Partner not found or could not be detected.', 'vs-relink' ) );
			}
		} else {
			$detected_id = PartnerUrlBuilder::detect_partner_for_url( $normalized );
			if ( $detected_id ) {
				$detected = get_term( $detected_id, Partner::TAXONOMY );
				if ( $detected && ! is_wp_error( $detected ) ) {
					$term = $detected;
				}
			}
		}

		if ( ! $term || is_wp_error( $term ) ) {
			$message = $strict_partner_errors
				? __( 'Partner is required for affiliate link creation.', 'vs-relink' )
				: __( 'Partner not found or could not be detected.', 'vs-relink' );

			return new \WP_Error( 'missing_partner', $message );
		}

		$partner_term_id = (int) $term->term_id;
		$suffix          = PartnerUrlBuilder::get_partner_suffix( $partner_term_id );
		$target_url      = PartnerUrlBuilder::build_target_url( $normalized, $suffix );
		if ( ! UrlGuard::is_http_url( $target_url ) ) {
			return new \WP_Error( 'invalid_url', __( 'Computed target URL must use http or https.', 'vs-relink' ) );
		}

		$short_slug = isset( $args['short_slug'] ) ? sanitize_title( (string) $args['short_slug'] ) : '';
		if ( '' === $short_slug ) {
			$short_slug = PartnerUrlBuilder::extract_slug_from_url( $normalized );
		}
		$short_slug = PartnerUrlBuilder::resolve_unique_slug( $short_slug, $term->slug );

		$title = isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : '';
		if ( '' === $title ) {
			$title = ucwords( str_replace( '-', ' ', $short_slug ) );
		}

		return array(
			'normalized'      => $normalized,
			'term'            => $term,
			'partner_term_id' => $partner_term_id,
			'target_url'      => $target_url,
			'short_slug'      => $short_slug,
			'title'           => $title,
			'existing_id'     => PartnerUrlBuilder::find_existing_link( $normalized, $partner_term_id ),
			'redirect_type'   => (string) UrlGuard::redirect_code( $args['redirect_type'] ?? '301' ),
		);
	}

	private static function sanitize_path_slug( string $slug ): string {
		$parts = explode( '/', trim( $slug, '/' ) );
		$clean = array();
		foreach ( $parts as $part ) {
			$part = sanitize_title( $part );
			if ( '' === $part ) {
				continue;
			}
			$clean[] = $part;
		}

		return implode( '/', $clean );
	}

	private static function flag_yes( mixed $value, string $fallback = 'no' ): string {
		if ( true === $value || 'yes' === $value || '1' === $value || 1 === $value ) {
			return 'yes';
		}
		if ( false === $value || 'no' === $value || '0' === $value || 0 === $value || '' === $value ) {
			return 'no';
		}

		return 'yes' === $fallback ? 'yes' : 'no';
	}

	/**
	 * @param array<string, mixed> $group
	 */
	private static function assign_imported_group( int $post_id, array $group ): void {
		$name = sanitize_text_field( (string) ( $group['name'] ?? '' ) );
		$slug = sanitize_title( (string) ( $group['slug'] ?? '' ) );
		if ( '' === $name ) {
			return;
		}

		$args = array();
		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}

		$term = wp_insert_term( $name, LinkGroup::TAXONOMY, $args );
		if ( is_wp_error( $term ) ) {
			$existing_term = $term->get_error_data( 'term_exists' );
			$term_id       = $existing_term ? $existing_term : null;
		} else {
			$term_id = $term['term_id'] ?? null;
		}
		if ( $term_id ) {
			wp_set_object_terms( $post_id, (int) $term_id, LinkGroup::TAXONOMY );
		}
	}
}
