<?php
/**
 * Settings view for VS ReLink.
 */

declare(strict_types=1);

use Vs\ReLink\Admin\AdminLayout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base = get_option( 'vs_relink_base', 're' );
?>
<div class="wrap lwr-admin-wrap">
	<?php AdminLayout::render( 'settings' ); ?>

	<div class="lwr-page-header">
		<h1><?php esc_html_e( 'Settings', 'vs-relink' ); ?></h1>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'vs_relink_settings' ); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Permalinks', 'vs-relink' ), __( 'Configure how short URLs are structured on your site.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vs_relink_base"><?php esc_html_e( 'Permalink Base', 'vs-relink' ); ?></label></th>
				<td>
					<input name="vs_relink_base" type="text" id="vs_relink_base" value="<?php echo esc_attr( (string) $base ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'The prefix for short links (e.g. "re" becomes domain.com/re/link). Leave empty for root-level links.', 'vs-relink' ); ?></p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Tracking', 'vs-relink' ), __( 'Click logging and data retention.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Click throttle', 'vs-relink' ); ?></th>
				<td>
					<input type="hidden" name="vs_relink_click_throttle" value="0" />
					<label>
						<input name="vs_relink_click_throttle" type="checkbox" value="1" <?php checked( get_option( 'vs_relink_click_throttle', '1' ), '1' ); ?> />
						<?php esc_html_e( 'Limit stored clicks per visitor and link', 'vs-relink' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Repeated hits from the same IP on the same short link within a minute are still redirected, but are not written to the click log or sent to the webhook. Turn off only if you need every hit recorded.', 'vs-relink' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Bot Filtering', 'vs-relink' ); ?></th>
				<td>
					<input type="hidden" name="vs_relink_exclude_bots" value="0" />
					<label>
						<input name="vs_relink_exclude_bots" type="checkbox" value="1" <?php checked( get_option( 'vs_relink_exclude_bots', '1' ), '1' ); ?> />
						<?php esc_html_e( 'Exclude known bots from statistics', 'vs-relink' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Recommended to keep stats clean (Google, Bing, Facebook bots, etc.)', 'vs-relink' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="vs_relink_log_retention"><?php esc_html_e( 'Log Retention', 'vs-relink' ); ?></label></th>
				<td>
					<select name="vs_relink_log_retention" id="vs_relink_log_retention">
						<option value="0" <?php selected( get_option( 'vs_relink_log_retention' ), '0' ); ?>><?php esc_html_e( 'Keep Forever', 'vs-relink' ); ?></option>
						<option value="30" <?php selected( get_option( 'vs_relink_log_retention' ), '30' ); ?>><?php esc_html_e( '30 Days', 'vs-relink' ); ?></option>
						<option value="90" <?php selected( get_option( 'vs_relink_log_retention' ), '90' ); ?>><?php esc_html_e( '90 Days', 'vs-relink' ); ?></option>
						<option value="180" <?php selected( get_option( 'vs_relink_log_retention' ), '180' ); ?>><?php esc_html_e( '180 Days', 'vs-relink' ); ?></option>
						<option value="365" <?php selected( get_option( 'vs_relink_log_retention' ), '365' ); ?>><?php esc_html_e( '1 Year', 'vs-relink' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Click logs store IP address, referrer, and user agent. Rows are deleted only when you choose a period above. Keep Forever does not delete anything.', 'vs-relink' ); ?></p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Webhooks', 'vs-relink' ), __( 'Send click events to an external endpoint.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vs_relink_webhook_url"><?php esc_html_e( 'Outbound Webhook URL', 'vs-relink' ); ?></label></th>
				<td>
					<input name="vs_relink_webhook_url" type="url" id="vs_relink_webhook_url" value="<?php echo esc_attr( (string) get_option( 'vs_relink_webhook_url' ) ); ?>" class="large-text" placeholder="https://your-api.com/webhook" />
					<p class="description"><?php esc_html_e( 'Every stored click triggers a JSON POST to this http(s) URL. Only http and https are saved.', 'vs-relink' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="vs_relink_webhook_secret"><?php esc_html_e( 'Webhook secret', 'vs-relink' ); ?></label></th>
				<td>
					<input name="vs_relink_webhook_secret" type="password" id="vs_relink_webhook_secret" value="" class="regular-text" autocomplete="new-password" />
					<?php if ( (string) get_option( 'vs_relink_webhook_secret', '' ) !== '' ) : ?>
						<p class="description"><?php esc_html_e( 'A secret is saved. Leave the field blank to keep it.', 'vs-relink' ); ?></p>
						<label>
							<input name="vs_relink_clear_webhook_secret" type="checkbox" value="1" />
							<?php esc_html_e( 'Remove saved secret', 'vs-relink' ); ?>
						</label>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Optional. When set, each request includes X-VS-Relink-Signature: sha256=<HMAC of the raw JSON body>. The secret is stored in options and is not printed on the public site.', 'vs-relink' ); ?></p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Bot API and proxies', 'vs-relink' ), __( 'Controls for Application Password clients and visitor IP logging.', 'vs-relink' ) ); ?>
		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vs_relink_create_rate_limit"><?php esc_html_e( 'Create limit', 'vs-relink' ); ?></label></th>
				<td>
					<input name="vs_relink_create_rate_limit" type="number" min="0" max="10000" step="1" id="vs_relink_create_rate_limit" value="<?php echo esc_attr( (string) get_option( 'vs_relink_create_rate_limit', 120 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Maximum REST create-link calls per user per hour. Use 0 to disable.', 'vs-relink' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="vs_relink_trusted_proxies"><?php esc_html_e( 'Trusted proxies', 'vs-relink' ); ?></label></th>
				<td>
					<textarea name="vs_relink_trusted_proxies" id="vs_relink_trusted_proxies" rows="4" class="large-text code" placeholder="10.0.0.1/32"><?php echo esc_textarea( (string) get_option( 'vs_relink_trusted_proxies', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One IP or CIDR per line. X-Forwarded-For is read only when the direct connection (REMOTE_ADDR) is in this list. Leave empty to log REMOTE_ADDR and ignore forwarded headers.', 'vs-relink' ); ?></p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php submit_button(); ?>
	</form>

	<?php AdminLayout::render_end(); ?>
</div>
