<?php
/**
 * Custom link editor view.
 *
 * @var \WP_Post $post
 * @var bool     $saved
 */

declare(strict_types=1);

use Vs\ReLink\Admin\AdminLayout;
use Vs\ReLink\Admin\LinkEditorPage;
use Vs\ReLink\Core\ShortUrlHelper;
use Vs\ReLink\Taxonomies\Partner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$original_url    = get_post_meta( $post->ID, '_lw_relink_original_url', true );
$target_url      = get_post_meta( $post->ID, '_lw_relink_target_url', true );
$redirect_type   = get_post_meta( $post->ID, '_lw_relink_type', true ) ?: '301';
$is_nofollow     = get_post_meta( $post->ID, '_lw_relink_nofollow', true ) === 'yes';
$is_sponsored    = get_post_meta( $post->ID, '_lw_relink_sponsored', true ) === 'yes';
$forward_params  = get_post_meta( $post->ID, '_lw_relink_forward_params', true ) === 'yes';
$enable_tracking = get_post_meta( $post->ID, '_lw_relink_tracking', true ) !== 'no';
$keywords        = get_post_meta( $post->ID, '_lw_relink_keywords', true );
$short_path      = $post->post_status === 'auto-draft' ? '' : ShortUrlHelper::get_path_suffix( $post->ID );
$full_short_url  = $post->post_status === 'auto-draft' ? '' : ShortUrlHelper::get_full_url( $post->ID );

$partner_terms = get_terms(
	[
		'taxonomy'   => Partner::TAXONOMY,
		'hide_empty' => false,
	]
);
$assigned_partner = wp_get_object_terms( $post->ID, Partner::TAXONOMY, [ 'fields' => 'ids' ] );
$selected_partner = ( ! is_wp_error( $assigned_partner ) && ! empty( $assigned_partner ) ) ? (int) $assigned_partner[0] : 0;
$is_partner_mode  = $selected_partner > 0 || (string) $original_url !== '';

$is_new = $post->post_status === 'auto-draft' && $post->post_title === '';
?>
<div class="wrap lwr-admin-wrap">
	<?php AdminLayout::render( 'add' ); ?>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Link saved.', 'vs-relink' ); ?></p></div>
	<?php endif; ?>

	<div class="lwr-page-header">
		<h1><?php echo $is_new ? esc_html__( 'Add New ReLink', 'vs-relink' ) : esc_html__( 'Edit ReLink', 'vs-relink' ); ?></h1>
		<?php if ( ! $is_new ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( LinkEditorPage::editor_url() ); ?>"><?php esc_html_e( 'Add New', 'vs-relink' ); ?></a>
		<?php endif; ?>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lwr-editor-form">
		<input type="hidden" name="action" value="lw_relink_save_link" />
		<input type="hidden" name="link_id" value="<?php echo esc_attr( (string) $post->ID ); ?>" />
		<?php wp_nonce_field( 'lw_relink_save_link_' . $post->ID ); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Link Details', 'vs-relink' ), __( 'Name and short URL for this redirect.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lw_relink_post_title"><?php esc_html_e( 'Title', 'vs-relink' ); ?></label></th>
				<td>
					<input type="text" name="post_title" id="lw_relink_post_title" value="<?php echo esc_attr( $post->post_title ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Optional link name', 'vs-relink' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lw_relink_short_path"><?php esc_html_e( 'Short URL', 'vs-relink' ); ?></label></th>
				<td>
					<div class="lwr-short-url-field">
						<input type="text" name="lw_relink_short_path" id="lw_relink_short_path" value="<?php echo esc_attr( $short_path ); ?>" class="large-text" placeholder="re/my-link" />
						<button type="button" class="button vs-relink-copy" data-url="<?php echo esc_url( $full_short_url ); ?>">
							<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							<?php esc_html_e( 'Copy', 'vs-relink' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Edit the short path (slug). Auto-suggested from the product URL path.', 'vs-relink' ); ?></p>
					<p class="lwr-short-url-preview">
						<?php esc_html_e( 'Full URL:', 'vs-relink' ); ?>
						<code id="lw_relink_short_preview"><?php echo esc_html( $full_short_url ); ?></code>
					</p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Affiliate URLs', 'vs-relink' ), __( 'Paste a product URL, pick a partner, and the target redirect URL is built automatically.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lw_relink_original_url"><?php esc_html_e( 'Original URL', 'vs-relink' ); ?></label></th>
				<td>
					<input type="url" name="lw_relink_original_url" id="lw_relink_original_url" value="<?php echo esc_url( (string) $original_url ); ?>" class="large-text" placeholder="https://example.com/product" />
					<p class="description"><?php esc_html_e( 'Clean product URL without affiliate parameters.', 'vs-relink' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lw_relink_partner"><?php esc_html_e( 'Partner', 'vs-relink' ); ?></label></th>
				<td>
					<select name="lw_relink_partner" id="lw_relink_partner">
						<option value=""><?php esc_html_e( '— None (manual target) —', 'vs-relink' ); ?></option>
						<?php if ( ! is_wp_error( $partner_terms ) ) : ?>
							<?php foreach ( $partner_terms as $term ) : ?>
								<option
									value="<?php echo esc_attr( (string) $term->term_id ); ?>"
									data-suffix="<?php echo esc_attr( (string) get_term_meta( $term->term_id, Partner::META_URL_SUFFIX, true ) ); ?>"
									data-domains="<?php echo esc_attr( (string) get_term_meta( $term->term_id, Partner::META_DOMAINS, true ) ); ?>"
									<?php selected( $selected_partner, (int) $term->term_id ); ?>
								>
									<?php echo esc_html( $term->name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Partner affiliate suffix is appended automatically. Domain match is suggested.', 'vs-relink' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lw_relink_target_url"><?php esc_html_e( 'Target URL', 'vs-relink' ); ?></label></th>
				<td>
					<input type="url" name="lw_relink_target_url" id="lw_relink_target_url" value="<?php echo esc_url( (string) $target_url ); ?>" class="large-text" <?php echo $is_partner_mode ? 'readonly' : ''; ?> placeholder="https://example.com/product" />
					<p class="description" id="lw_relink_target_desc">
						<?php
						echo $is_partner_mode
							? esc_html__( 'Computed from Original URL + Partner suffix (redirect destination).', 'vs-relink' )
							: esc_html__( 'Where should this link redirect to?', 'vs-relink' );
						?>
					</p>
					<p class="description" id="lw_relink_target_preview_wrap" style="<?php echo $is_partner_mode ? '' : 'display:none;'; ?>">
						<?php esc_html_e( 'Preview:', 'vs-relink' ); ?>
						<code id="lw_relink_target_preview"><?php echo esc_html( (string) $target_url ); ?></code>
					</p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Redirect & Tracking', 'vs-relink' ), __( 'HTTP redirect behavior and click logging.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lw_relink_type"><?php esc_html_e( 'Redirect Type', 'vs-relink' ); ?></label></th>
				<td>
					<select name="lw_relink_type" id="lw_relink_type">
						<option value="301" <?php selected( $redirect_type, '301' ); ?>><?php esc_html_e( '301 - Permanent', 'vs-relink' ); ?></option>
						<option value="302" <?php selected( $redirect_type, '302' ); ?>><?php esc_html_e( '302 - Temporary', 'vs-relink' ); ?></option>
						<option value="307" <?php selected( $redirect_type, '307' ); ?>><?php esc_html_e( '307 - Temporary (Preserve Method)', 'vs-relink' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'vs-relink' ); ?></th>
				<td class="lwr-checkbox-group">
					<label><input type="checkbox" name="lw_relink_nofollow" value="yes" <?php checked( $is_nofollow ); ?> /> <?php esc_html_e( 'No Follow', 'vs-relink' ); ?></label>
					<label><input type="checkbox" name="lw_relink_sponsored" value="yes" <?php checked( $is_sponsored ); ?> /> <?php esc_html_e( 'Sponsored', 'vs-relink' ); ?></label>
					<label><input type="checkbox" name="lw_relink_forward_params" value="yes" <?php checked( $forward_params ); ?> /> <?php esc_html_e( 'Forward Parameters', 'vs-relink' ); ?></label>
					<label><input type="checkbox" name="lw_relink_tracking" value="yes" <?php checked( $enable_tracking ); ?> /> <?php esc_html_e( 'Enable Tracking', 'vs-relink' ); ?></label>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Auto-linker', 'vs-relink' ), __( 'Automatically link keywords in post content to this short URL.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lw_relink_keywords"><?php esc_html_e( 'Keywords', 'vs-relink' ); ?></label></th>
				<td>
					<textarea name="lw_relink_keywords" id="lw_relink_keywords" rows="3" class="large-text"><?php echo esc_textarea( (string) $keywords ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Comma-separated keywords. First match in content is linked.', 'vs-relink' ); ?></p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<p class="submit">
			<?php submit_button( __( 'Save Link', 'vs-relink' ), 'primary', 'submit', false ); ?>
			<?php if ( ! $is_new && current_user_can( 'delete_post', $post->ID ) ) : ?>
				<a class="button button-link-delete" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Move to Trash', 'vs-relink' ); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<?php AdminLayout::render_end(); ?>
</div>
