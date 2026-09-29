<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\Core\Capabilities;
use Vs\ReLink\Taxonomies\Partner;

/**
 * Admin fields for partner taxonomy term meta.
 */
final class PartnerTermMeta {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( Partner::TAXONOMY . '_add_form_fields', [ $this, 'render_add_fields' ] );
		add_action( Partner::TAXONOMY . '_edit_form_fields', [ $this, 'render_edit_fields' ] );
		add_action( 'created_' . Partner::TAXONOMY, [ $this, 'save_term_meta' ] );
		add_action( 'edited_' . Partner::TAXONOMY, [ $this, 'save_term_meta' ] );
	}

	/**
	 * Block partner domain/suffix writes unless the user may manage partners.
	 */
	public static function register_meta_guards(): void {
		add_filter( 'add_term_metadata', [ self::class, 'guard_term_meta' ], 10, 3 );
		add_filter( 'update_term_metadata', [ self::class, 'guard_term_meta' ], 10, 3 );
		add_filter( 'delete_term_metadata', [ self::class, 'guard_term_meta' ], 10, 3 );
	}

	/**
	 * @param mixed $check Short-circuit value.
	 * @return mixed
	 */
	public static function guard_term_meta( $check, $term_id, $meta_key ) {
		unset( $term_id );

		if ( ! in_array( (string) $meta_key, [ Partner::META_DOMAINS, Partner::META_URL_SUFFIX ], true ) ) {
			return $check;
		}

		if ( Capabilities::current_user_may_manage_partners() ) {
			return $check;
		}

		return false;
	}

	/**
	 * Render fields on add form.
	 */
	public function render_add_fields(): void {
		?>
		<div class="form-field">
			<label for="lw_partner_domains"><?php esc_html_e( 'Domains', 'vs-relink' ); ?></label>
			<textarea name="lw_partner_domains" id="lw_partner_domains" rows="3" class="large-text" placeholder="sonoff.tech&#10;www.sonoff.tech"></textarea>
			<p class="description"><?php esc_html_e( 'One domain per line or comma-separated. Used to auto-suggest this partner when creating links.', 'vs-relink' ); ?></p>
		</div>
		<div class="form-field">
			<label for="lw_partner_url_suffix"><?php esc_html_e( 'URL Suffix', 'vs-relink' ); ?></label>
			<input type="text" name="lw_partner_url_suffix" id="lw_partner_url_suffix" class="large-text" placeholder="?ref=66&utm_source=affiliate" />
			<p class="description"><?php esc_html_e( 'Affiliate query string appended to the original product URL.', 'vs-relink' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render fields on edit form.
	 *
	 * @param \WP_Term $term Current term.
	 */
	public function render_edit_fields( $term ): void {
		$domains    = (string) get_term_meta( $term->term_id, Partner::META_DOMAINS, true );
		$url_suffix = (string) get_term_meta( $term->term_id, Partner::META_URL_SUFFIX, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="lw_partner_domains"><?php esc_html_e( 'Domains', 'vs-relink' ); ?></label></th>
			<td>
				<textarea name="lw_partner_domains" id="lw_partner_domains" rows="3" class="large-text"><?php echo esc_textarea( $domains ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One domain per line or comma-separated. Used to auto-suggest this partner when creating links.', 'vs-relink' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="lw_partner_url_suffix"><?php esc_html_e( 'URL Suffix', 'vs-relink' ); ?></label></th>
			<td>
				<input type="text" name="lw_partner_url_suffix" id="lw_partner_url_suffix" class="large-text" value="<?php echo esc_attr( $url_suffix ); ?>" placeholder="?ref=66&utm_source=affiliate" />
				<p class="description"><?php esc_html_e( 'Affiliate query string appended to the original product URL.', 'vs-relink' ); ?></p>
				<?php if ( $url_suffix !== '' ) : ?>
					<p class="description">
						<?php esc_html_e( 'Example:', 'vs-relink' ); ?>
						<code>https://example.com/product<?php echo esc_html( $url_suffix ); ?></code>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_term_meta( int $term_id ): void {
		if ( ! Capabilities::current_user_may_manage_partners() ) {
			return;
		}

		if ( isset( $_POST['lw_partner_domains'] ) ) {
			update_term_meta(
				$term_id,
				Partner::META_DOMAINS,
				sanitize_textarea_field( wp_unslash( (string) $_POST['lw_partner_domains'] ) )
			);
		}

		if ( isset( $_POST['lw_partner_url_suffix'] ) ) {
			update_term_meta(
				$term_id,
				Partner::META_URL_SUFFIX,
				sanitize_text_field( wp_unslash( (string) $_POST['lw_partner_url_suffix'] ) )
			);
		}
	}
}
