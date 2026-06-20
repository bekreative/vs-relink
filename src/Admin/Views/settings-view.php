<?php
/**
 * Settings view for LW ReLink.
 */

declare(strict_types=1);

use Vs\ReLink\Admin\AdminLayout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base = get_option( 'lw_relink_base', 're' );
?>
<div class="wrap lwr-admin-wrap">
	<?php AdminLayout::render( 'settings' ); ?>

	<div class="lwr-page-header">
		<h1><?php esc_html_e( 'Settings', 'vs-relink' ); ?></h1>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'lw_relink_settings' ); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Permalinks', 'vs-relink' ), __( 'Configure how short URLs are structured on your site.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lw_relink_base"><?php esc_html_e( 'Permalink Base', 'vs-relink' ); ?></label></th>
				<td>
					<input name="lw_relink_base" type="text" id="lw_relink_base" value="<?php echo esc_attr( (string) $base ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'The prefix for short links (e.g. "re" becomes domain.com/re/link). Leave empty for root-level links.', 'vs-relink' ); ?></p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Tracking', 'vs-relink' ), __( 'Click logging and data retention.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Bot Filtering', 'vs-relink' ); ?></th>
				<td>
					<label>
						<input name="lw_relink_exclude_bots" type="checkbox" value="1" <?php checked( get_option( 'lw_relink_exclude_bots', '1' ), '1' ); ?> />
						<?php esc_html_e( 'Exclude known bots from statistics', 'vs-relink' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Recommended to keep stats clean (Google, Bing, Facebook bots, etc.)', 'vs-relink' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lw_relink_log_retention"><?php esc_html_e( 'Log Retention', 'vs-relink' ); ?></label></th>
				<td>
					<select name="lw_relink_log_retention" id="lw_relink_log_retention">
						<option value="0" <?php selected( get_option( 'lw_relink_log_retention' ), '0' ); ?>><?php esc_html_e( 'Keep Forever', 'vs-relink' ); ?></option>
						<option value="30" <?php selected( get_option( 'lw_relink_log_retention' ), '30' ); ?>><?php esc_html_e( '30 Days', 'vs-relink' ); ?></option>
						<option value="90" <?php selected( get_option( 'lw_relink_log_retention' ), '90' ); ?>><?php esc_html_e( '90 Days', 'vs-relink' ); ?></option>
						<option value="180" <?php selected( get_option( 'lw_relink_log_retention' ), '180' ); ?>><?php esc_html_e( '180 Days', 'vs-relink' ); ?></option>
						<option value="365" <?php selected( get_option( 'lw_relink_log_retention' ), '365' ); ?>><?php esc_html_e( '1 Year', 'vs-relink' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Automatically delete click logs older than this period.', 'vs-relink' ); ?></p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php AdminLayout::panel_open(); ?>
		<?php AdminLayout::section_title( __( 'Webhooks', 'vs-relink' ), __( 'Send click events to an external endpoint.', 'vs-relink' ) ); ?>

		<table class="form-table lwr-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lw_relink_webhook_url"><?php esc_html_e( 'Outbound Webhook URL', 'vs-relink' ); ?></label></th>
				<td>
					<input name="lw_relink_webhook_url" type="url" id="lw_relink_webhook_url" value="<?php echo esc_attr( (string) get_option( 'lw_relink_webhook_url' ) ); ?>" class="large-text" placeholder="https://your-api.com/webhook" />
					<p class="description"><?php esc_html_e( 'Every tracked click triggers a JSON POST to this URL.', 'vs-relink' ); ?></p>
				</td>
			</tr>
		</table>
		<?php AdminLayout::panel_close(); ?>

		<?php submit_button(); ?>
	</form>

	<?php AdminLayout::render_end(); ?>
</div>
