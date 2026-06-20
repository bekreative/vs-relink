<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\Core\PartnerUrlBuilder;
use Vs\ReLink\Core\ShortUrlHelper;
use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Taxonomies\Partner;

/**
 * Save and validate ReLink editor form data.
 */
final class LinkEditorService {

	/**
	 * Save link from admin editor form.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Sanitized POST data.
	 * @return true|\WP_Error
	 */
	public static function save( int $post_id, array $data ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== ReLink::POST_TYPE ) {
			return new \WP_Error( 'invalid_post', __( 'Invalid link.', 'vs-relink' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot edit this link.', 'vs-relink' ) );
		}

		$title = isset( $data['post_title'] ) ? sanitize_text_field( (string) $data['post_title'] ) : '';
		if ( $title !== '' ) {
			wp_update_post(
				[
					'ID'         => $post_id,
					'post_title' => $title,
					'post_status' => 'publish',
				]
			);
		} elseif ( $post->post_status === 'auto-draft' ) {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => 'publish',
				]
			);
		}

		$partner_id   = isset( $data['lw_relink_partner'] ) ? (int) $data['lw_relink_partner'] : 0;
		$original_url = isset( $data['lw_relink_original_url'] ) ? esc_url_raw( (string) $data['lw_relink_original_url'] ) : '';
		$is_partner_mode = $partner_id > 0 && $original_url !== '';

		if ( $is_partner_mode ) {
			$normalized = PartnerUrlBuilder::normalize_original_url( $original_url );
			$suffix     = PartnerUrlBuilder::get_partner_suffix( $partner_id );
			$target_url = PartnerUrlBuilder::build_target_url( $normalized, $suffix );

			update_post_meta( $post_id, '_lw_relink_original_url', $normalized );
			update_post_meta( $post_id, '_lw_relink_target_url', $target_url );
			wp_set_object_terms( $post_id, [ $partner_id ], Partner::TAXONOMY, false );
		} else {
			if ( $original_url !== '' ) {
				update_post_meta( $post_id, '_lw_relink_original_url', PartnerUrlBuilder::normalize_original_url( $original_url ) );
			} else {
				delete_post_meta( $post_id, '_lw_relink_original_url' );
			}

			if ( isset( $data['lw_relink_target_url'] ) ) {
				update_post_meta( $post_id, '_lw_relink_target_url', esc_url_raw( (string) $data['lw_relink_target_url'] ) );
			}

			if ( $partner_id > 0 ) {
				wp_set_object_terms( $post_id, [ $partner_id ], Partner::TAXONOMY, false );
			} else {
				wp_set_object_terms( $post_id, [], Partner::TAXONOMY, false );
			}
		}

		$fields = [
			'lw_relink_type'           => '_lw_relink_type',
			'lw_relink_nofollow'       => '_lw_relink_nofollow',
			'lw_relink_sponsored'      => '_lw_relink_sponsored',
			'lw_relink_forward_params' => '_lw_relink_forward_params',
			'lw_relink_keywords'       => '_lw_relink_keywords',
		];

		foreach ( $fields as $field_id => $meta_key ) {
			if ( isset( $data[ $field_id ] ) && (string) $data[ $field_id ] !== '' ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $data[ $field_id ] ) );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}

		update_post_meta( $post_id, '_lw_relink_tracking', ! empty( $data['lw_relink_tracking'] ) ? 'yes' : 'no' );

		if ( isset( $data['lw_relink_short_path'] ) ) {
			$path = sanitize_text_field( (string) $data['lw_relink_short_path'] );
			if ( $path !== '' ) {
				$result = ShortUrlHelper::update_from_path_suffix( $post_id, $path );
				if ( is_wp_error( $result ) ) {
					$leaf = sanitize_title( basename( $path ) );
					if ( $leaf !== '' ) {
						wp_update_post(
							[
								'ID'        => $post_id,
								'post_name' => $leaf,
							]
						);
					}
				}
			} elseif ( $is_partner_mode ) {
				$term = get_term( $partner_id, Partner::TAXONOMY );
				$slug = PartnerUrlBuilder::extract_slug_from_url( $original_url );
				$slug = PartnerUrlBuilder::resolve_unique_slug( $slug, $term && ! is_wp_error( $term ) ? $term->slug : '', $post_id );
				wp_update_post(
					[
						'ID'        => $post_id,
						'post_name' => $slug,
					]
				);
			}
		}

		return true;
	}
}
