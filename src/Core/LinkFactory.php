<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Taxonomies\Partner;

/**
 * Shared link creation logic for admin, REST, and CLI.
 */
final class LinkFactory {

	/**
	 * Create or return existing ReLink for original URL + partner.
	 *
	 * @param array<string, mixed> $args Creation arguments.
	 * @return array{link_id: int, short_url: string, target_url: string, partner: string, existed: bool}|\WP_Error
	 */
	public static function create( array $args ) {
		$original_url = isset( $args['original_url'] ) ? esc_url_raw( (string) $args['original_url'] ) : '';
		if ( $original_url === '' ) {
			return new \WP_Error( 'missing_url', __( 'Original URL is required.', 'vs-relink' ) );
		}

		$normalized = PartnerUrlBuilder::normalize_original_url( $original_url );
		$partner    = $args['partner'] ?? '';
		$term       = null;

		if ( $partner !== '' && $partner !== null ) {
			$term = Partner::resolve_term( $partner );
			if ( ! $term ) {
				return new \WP_Error( 'invalid_partner', __( 'Partner not found.', 'vs-relink' ) );
			}
		} else {
			$detected_id = PartnerUrlBuilder::detect_partner_for_url( $normalized );
			if ( $detected_id ) {
				$term = get_term( $detected_id, Partner::TAXONOMY );
			}
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error( 'missing_partner', __( 'Partner is required for affiliate link creation.', 'vs-relink' ) );
		}

		$partner_term_id = (int) $term->term_id;
		$suffix            = PartnerUrlBuilder::get_partner_suffix( $partner_term_id );
		$target_url        = PartnerUrlBuilder::build_target_url( $normalized, $suffix );

		$existing_id = PartnerUrlBuilder::find_existing_link( $normalized, $partner_term_id );
		if ( $existing_id ) {
			return [
				'link_id'    => $existing_id,
				'short_url'  => ShortUrlHelper::get_full_url( $existing_id ),
				'target_url' => (string) get_post_meta( $existing_id, '_lw_relink_target_url', true ),
				'partner'    => $term->slug,
				'existed'    => true,
			];
		}

		$short_slug = isset( $args['short_slug'] ) ? sanitize_title( (string) $args['short_slug'] ) : '';
		if ( $short_slug === '' ) {
			$short_slug = PartnerUrlBuilder::extract_slug_from_url( $normalized );
		}
		$short_slug = PartnerUrlBuilder::resolve_unique_slug( $short_slug, $term->slug );

		$title = isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : '';
		if ( $title === '' ) {
			$title = ucwords( str_replace( '-', ' ', $short_slug ) );
		}

		$post_id = wp_insert_post(
			[
				'post_title'  => $title,
				'post_name'   => $short_slug,
				'post_type'   => ReLink::POST_TYPE,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, '_lw_relink_original_url', $normalized );
		update_post_meta( $post_id, '_lw_relink_target_url', $target_url );
		update_post_meta( $post_id, '_lw_relink_type', (string) ( $args['redirect_type'] ?? '301' ) );
		update_post_meta( $post_id, '_lw_relink_tracking', ( $args['tracking'] ?? true ) ? 'yes' : 'no' );

		if ( ! empty( $args['nofollow'] ) ) {
			update_post_meta( $post_id, '_lw_relink_nofollow', 'yes' );
		}
		if ( ! empty( $args['sponsored'] ) ) {
			update_post_meta( $post_id, '_lw_relink_sponsored', 'yes' );
		}
		if ( ! empty( $args['forward_params'] ) ) {
			update_post_meta( $post_id, '_lw_relink_forward_params', 'yes' );
		}
		if ( ! empty( $args['keywords'] ) ) {
			update_post_meta( $post_id, '_lw_relink_keywords', sanitize_textarea_field( (string) $args['keywords'] ) );
		}

		wp_set_object_terms( $post_id, [ $partner_term_id ], Partner::TAXONOMY, false );

		return [
			'link_id'    => $post_id,
			'short_url'  => ShortUrlHelper::get_full_url( $post_id ),
			'target_url' => $target_url,
			'partner'    => $term->slug,
			'existed'    => false,
		];
	}

	/**
	 * Preview link creation without saving (dry-run).
	 *
	 * @param array<string, mixed> $args Same as create().
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function preview( array $args ) {
		$original_url = isset( $args['original_url'] ) ? esc_url_raw( (string) $args['original_url'] ) : '';
		if ( $original_url === '' ) {
			return new \WP_Error( 'missing_url', __( 'Original URL is required.', 'vs-relink' ) );
		}

		$normalized = PartnerUrlBuilder::normalize_original_url( $original_url );
		$partner    = $args['partner'] ?? '';
		$term       = null;

		if ( $partner !== '' && $partner !== null ) {
			$term = Partner::resolve_term( $partner );
		} else {
			$detected_id = PartnerUrlBuilder::detect_partner_for_url( $normalized );
			if ( $detected_id ) {
				$term = get_term( $detected_id, Partner::TAXONOMY );
			}
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error( 'missing_partner', __( 'Partner not found or could not be detected.', 'vs-relink' ) );
		}

		$suffix     = PartnerUrlBuilder::get_partner_suffix( (int) $term->term_id );
		$target_url = PartnerUrlBuilder::build_target_url( $normalized, $suffix );

		$short_slug = isset( $args['short_slug'] ) ? sanitize_title( (string) $args['short_slug'] ) : '';
		if ( $short_slug === '' ) {
			$short_slug = PartnerUrlBuilder::extract_slug_from_url( $normalized );
		}
		$short_slug = PartnerUrlBuilder::resolve_unique_slug( $short_slug, $term->slug );

		$existing_id = PartnerUrlBuilder::find_existing_link( $normalized, (int) $term->term_id );

		return [
			'original_url' => $normalized,
			'target_url'   => $target_url,
			'partner'      => $term->slug,
			'partner_name' => $term->name,
			'short_slug'   => $short_slug,
			'existed'      => (bool) $existing_id,
			'link_id'      => $existing_id ?: null,
		];
	}
}
