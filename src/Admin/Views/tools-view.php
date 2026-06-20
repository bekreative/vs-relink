<?php
/**
 * Tools view for LW ReLink.
 */

declare(strict_types=1);

use Vs\ReLink\Admin\AdminLayout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap lwr-admin-wrap">
	<?php AdminLayout::render( 'tools' ); ?>

	<div class="lwr-page-header">
		<h1><?php esc_html_e( 'Tools', 'vs-relink' ); ?></h1>
	</div>

	<div id="vs-relink-migration-status" class="notice" style="display:none; margin: 0 0 16px;"></div>

	<div class="lwr-tools-grid">
		<div class="card">
			<h2><?php esc_html_e( 'Pretty Link Migration', 'vs-relink' ); ?></h2>
			<p><?php esc_html_e( 'Import all data directly from Pretty Link Lite database tables.', 'vs-relink' ); ?></p>
			<button type="button" class="button button-secondary vs-relink-migrate-btn"><?php esc_html_e( 'Start Local Migration', 'vs-relink' ); ?></button>
		</div>

		<div class="card">
			<h2><?php esc_html_e( 'Export / Import (JSON)', 'vs-relink' ); ?></h2>
			<p><?php esc_html_e( 'Move links between different WordPress sites.', 'vs-relink' ); ?></p>
			<p><button type="button" class="button button-secondary vs-relink-export-json"><?php esc_html_e( 'Download Export File', 'vs-relink' ); ?></button></p>
			<hr />
			<p><strong><?php esc_html_e( 'Domain Search & Replace (Optional)', 'vs-relink' ); ?></strong></p>
			<p><input type="text" id="vs-relink-find" class="regular-text" placeholder="old-site.com" style="width:100%;" /></p>
			<p><input type="text" id="vs-relink-replace" class="regular-text" placeholder="new-site.hu" style="width:100%;" /></p>
			<p><button type="button" class="button button-primary vs-relink-import-btn"><?php esc_html_e( 'Upload and Import JSON', 'vs-relink' ); ?></button></p>
			<input type="file" id="vs-relink-import-file" style="display:none;" accept=".json" />
		</div>

		<div class="card">
			<h2><?php esc_html_e( 'Server Redirection (.htaccess)', 'vs-relink' ); ?></h2>
			<p><?php esc_html_e( 'Generate static redirection rules for your .htaccess file.', 'vs-relink' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=lw_relink&page=vs-relink-tools&lw_relink_download_htaccess=1' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Download .htaccess Rules', 'vs-relink' ); ?></a>
		</div>

		<div class="card" style="grid-column: 1 / -1;">
			<h2><?php esc_html_e( 'Link Health Scanner', 'vs-relink' ); ?></h2>
			<p><?php esc_html_e( 'Verify that all links are correctly redirecting to their targets.', 'vs-relink' ); ?></p>
			<button type="button" class="button button-secondary vs-relink-check-btn"><?php esc_html_e( 'Scan & Verify All Links Now', 'vs-relink' ); ?></button>
			<div id="vs-relink-scan-progress" style="display:none; margin:15px 0; background:#eee; height:12px; border-radius:6px; overflow:hidden;">
				<div class="bar" style="width:0%; height:100%; background:#46b450; transition:width 0.3s;"></div>
			</div>
			<div id="vs-relink-scan-results" style="display:none; max-height:300px; overflow-y:auto; background:#fafafa; padding:15px; border:1px solid #ccd0d4; margin-top:15px; border-radius:4px;"></div>
		</div>
	</div>

	<?php AdminLayout::render_end(); ?>
</div>

<script>
jQuery(document).ready(function($) {
	$('.vs-relink-migrate-btn').on('click', function(e) {
		e.preventDefault();
		if (!confirm('<?php echo esc_js( __( 'Start local migration from Pretty Link Lite?', 'vs-relink' ) ); ?>')) return;
		var btn = $(this);
		var status = $('#vs-relink-migration-status');
		btn.addClass('disabled').text('<?php echo esc_js( __( 'Migrating...', 'vs-relink' ) ); ?>');
		status.show().removeClass('notice-error notice-success').addClass('notice-info').html('<p><?php echo esc_js( __( 'Migration in progress...', 'vs-relink' ) ); ?></p>');

		$.post(ajaxurl, {
			action: 'lw_relink_migrate',
			security: '<?php echo esc_js( wp_create_nonce( 'lw_relink_migration_nonce' ) ); ?>'
		}, function(response) {
			if (response.success) {
				status.removeClass('notice-info').addClass('notice-success').html('<p>' + response.data.message + '</p>');
				btn.text('<?php echo esc_js( __( 'Migration Finished', 'vs-relink' ) ); ?>');
			} else {
				status.removeClass('notice-info').addClass('notice-error').html('<p>' + response.data.message + '</p>');
				btn.removeClass('disabled').text('<?php echo esc_js( __( 'Try Again', 'vs-relink' ) ); ?>');
			}
		});
	});

	$('.vs-relink-export-json').on('click', function(e) {
		e.preventDefault();
		$.post(ajaxurl, {
			action: 'lw_relink_export_json',
			security: '<?php echo esc_js( wp_create_nonce( 'lw_relink_data_nonce' ) ); ?>'
		}, function(response) {
			if (response.success) {
				var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
				var url = URL.createObjectURL(blob);
				var a = document.createElement('a');
				a.href = url;
				a.download = 'vs-relink-export.json';
				a.click();
			}
		});
	});

	$('.vs-relink-import-btn').on('click', function(e) {
		e.preventDefault();
		$('#vs-relink-import-file').click();
	});

	$('#vs-relink-import-file').on('change', function(e) {
		var file = e.target.files[0];
		if (!file) return;

		var reader = new FileReader();
		reader.onload = function(ev) {
			var links = JSON.parse(ev.target.result);
			var status = $('#vs-relink-migration-status');
			status.show().removeClass('notice-error notice-success').addClass('notice-info').html('<p><?php echo esc_js( __( 'Importing links...', 'vs-relink' ) ); ?></p>');

			$.post(ajaxurl, {
				action: 'lw_relink_import_json',
				security: '<?php echo esc_js( wp_create_nonce( 'lw_relink_data_nonce' ) ); ?>',
				links: links,
				find: $('#vs-relink-find').val(),
				replace: $('#vs-relink-replace').val()
			}, function(response) {
				status.removeClass('notice-info').addClass(response.success ? 'notice-success' : 'notice-error').html('<p>' + response.data.message + '</p>');
			});
		};
		reader.readAsText(file);
	});

	$('.vs-relink-check-btn').on('click', function(e) {
		e.preventDefault();
		var btn = $(this);
		var status = $('#vs-relink-migration-status');
		var progressArea = $('#vs-relink-scan-progress');
		var progressBar = progressArea.find('.bar');
		var resultsList = $('#vs-relink-scan-results');

		btn.addClass('disabled').text('<?php echo esc_js( __( 'Scanning...', 'vs-relink' ) ); ?>');
		status.show().removeClass('notice-error notice-success').addClass('notice-info').html('<p><?php echo esc_js( __( 'Starting health check...', 'vs-relink' ) ); ?></p>');
		progressArea.show();
		resultsList.empty().show();

		$.post(ajaxurl, {
			action: 'lw_relink_get_ids',
			security: '<?php echo esc_js( wp_create_nonce( 'lw_relink_data_nonce' ) ); ?>'
		}, function(response) {
			if (!response.success || !response.data.length) {
				status.removeClass('notice-info').addClass('notice-error').html('<p><?php echo esc_js( __( 'No links found to scan.', 'vs-relink' ) ); ?></p>');
				btn.removeClass('disabled').text('<?php echo esc_js( __( 'Scan & Verify All Links Now', 'vs-relink' ) ); ?>');
				return;
			}

			var ids = response.data;
			var total = ids.length;
			var current = 0;

			function checkNext() {
				if (current >= total) {
					status.removeClass('notice-info').addClass('notice-success').html('<p><?php echo esc_js( __( 'Scan completed.', 'vs-relink' ) ); ?></p>');
					btn.removeClass('disabled').text('<?php echo esc_js( __( 'Scan Completed', 'vs-relink' ) ); ?>');
					return;
				}
				$.post(ajaxurl, {
					action: 'lw_relink_check_single',
					security: '<?php echo esc_js( wp_create_nonce( 'lw_relink_data_nonce' ) ); ?>',
					post_id: ids[current]
				}, function(res) {
					current++;
					progressBar.css('width', ((current / total) * 100) + '%');
					resultsList.append('<div style="margin-bottom:5px;font-size:12px;color:' + (res.data.success ? '#46b450' : '#d63638') + ';">' + (res.data.success ? '✓' : '✗') + ' <strong>' + res.data.post_title + '</strong>: ' + res.data.message + '</div>');
					checkNext();
				});
			}
			checkNext();
		});
	});
});
</script>
