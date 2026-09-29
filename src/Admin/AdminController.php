<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\Admin\LinkEditorPage;
use Vs\ReLink\Admin\ListTableHandler;
use Vs\ReLink\Admin\MigrationService;
use Vs\ReLink\Admin\DataService;
use Vs\ReLink\Admin\LinkChecker;
use Vs\ReLink\PostTypes\ReLink;

/**
 * Controller for admin-related functionality.
 */
final class AdminController {

	/**
	 * Constructor.
	 */
	public function __construct() {
		AdminLayout::register();
		LinkEditorPage::register();

		new ListTableHandler();

		add_action( 'admin_menu', array( $this, 'add_reports_page' ) );
		add_action( 'admin_menu', array( $this, 'fix_add_new_submenu' ), 99 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_vs_relink_migrate', array( $this, 'ajax_migrate' ) );
		add_action( 'wp_ajax_vs_relink_export_json', array( $this, 'ajax_export_json' ) );
		add_action( 'wp_ajax_vs_relink_import_json', array( $this, 'ajax_import_json' ) );
		add_action( 'wp_ajax_vs_relink_get_ids', array( $this, 'ajax_get_ids' ) );
		add_action( 'wp_ajax_vs_relink_check_single', array( $this, 'ajax_check_single' ) );
		add_action( 'admin_init', array( $this, 'handle_htaccess_download' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_admin_scripts' ) );
		add_action( 'admin_footer', array( AdminLayout::class, 'render_list_footer' ), 99 );
	}

	/**
	 * Point "Add New" submenu to the custom editor.
	 */
	public function fix_add_new_submenu(): void {
		global $submenu;

		$parent = 'edit.php?post_type=' . ReLink::POST_TYPE;
		if ( empty( $submenu[ $parent ] ) ) {
			return;
		}

		foreach ( $submenu[ $parent ] as $index => $item ) {
			if ( str_contains( $item[2], 'post-new.php' ) ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress builds this menu global; Add New has to be retargeted in place.
				$submenu[ $parent ][ $index ][2] = 'admin.php?page=' . LinkEditorPage::PAGE_SLUG;
			}
		}
	}

	/**
	 * Enqueue admin styles and scripts on ReLink screens.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( ! AdminLayout::is_relink_screen() && 'admin_page_' . LinkEditorPage::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'vs-relink-admin',
			VS_RELINK_URL . 'assets/css/admin.css',
			array(),
			VS_RELINK_VERSION
		);

		$screen           = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$needs_metabox_js = 'admin_page_' . LinkEditorPage::PAGE_SLUG === $hook
			|| ( $screen && ReLink::POST_TYPE === $screen->post_type );

		if ( $needs_metabox_js ) {
			wp_enqueue_script(
				'vs-relink-link-metabox',
				VS_RELINK_URL . 'assets/js/link-metabox.js',
				array( 'jquery' ),
				VS_RELINK_VERSION,
				true
			);
			wp_localize_script(
				'vs-relink-link-metabox',
				'lwRelinkMetabox',
				array(
					'i18n' => array(
						'targetComputed' => __( 'Computed from Original URL + Partner suffix (redirect destination).', 'vs-relink' ),
						'targetManual'   => __( 'Where should this link redirect to?', 'vs-relink' ),
					),
				)
			);
		}
	}

	/**
	 * Chart.js for the reports screen. Called while rendering the page so the
	 * dataset is the same one the view just queried. Footer printing still picks
	 * up scripts enqueued before admin_print_footer_scripts.
	 *
	 * @param string[] $labels
	 * @param int[]    $data_points
	 */
	public static function enqueue_reports_chart( array $labels, array $data_points ): void {
		wp_enqueue_script(
			'vs-relink-chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js',
			array(),
			VS_RELINK_VERSION,
			true
		);

		wp_add_inline_script(
			'vs-relink-chartjs',
			'var vsRelinkChart = ' . wp_json_encode(
				array(
					'labels'      => array_values( $labels ),
					'data'        => array_values( $data_points ),
					'seriesLabel' => __( 'Clicks', 'vs-relink' ),
				)
			) . ';',
			'before'
		);

		wp_add_inline_script(
			'vs-relink-chartjs',
			"(function () {\n"
			. "\tvar ctx = document.getElementById('relinkTrendsChart');\n"
			. "\tif (!ctx || typeof Chart === 'undefined' || typeof vsRelinkChart === 'undefined') {\n"
			. "\t\treturn;\n"
			. "\t}\n"
			. "\tnew Chart(ctx.getContext('2d'), {\n"
			. "\t\ttype: 'bar',\n"
			. "\t\tdata: {\n"
			. "\t\t\tlabels: vsRelinkChart.labels,\n"
			. "\t\t\tdatasets: [{\n"
			. "\t\t\t\tlabel: vsRelinkChart.seriesLabel,\n"
			. "\t\t\t\tdata: vsRelinkChart.data,\n"
			. "\t\t\t\tbackgroundColor: 'rgba(79, 140, 255, 0.85)',\n"
			. "\t\t\t\tborderRadius: 6,\n"
			. "\t\t\t\tborderSkipped: false\n"
			. "\t\t\t}]\n"
			. "\t\t},\n"
			. "\t\toptions: {\n"
			. "\t\t\tresponsive: true,\n"
			. "\t\t\tmaintainAspectRatio: false,\n"
			. "\t\t\tscales: {\n"
			. "\t\t\t\ty: { beginAtZero: true, grid: { color: '#f0f0f1' } },\n"
			. "\t\t\t\tx: { grid: { display: false } }\n"
			. "\t\t\t},\n"
			. "\t\t\tplugins: { legend: { display: false } }\n"
			. "\t\t}\n"
			. "\t});\n"
			. "})();\n",
			'after'
		);
	}

	/**
	 * Render admin-wide scripts (e.g., Clipboard copy).
	 */
	public function render_admin_scripts(): void {
		if ( ! AdminLayout::is_relink_screen() && ! $this->is_link_editor_screen() ) {
			return;
		}

		$home = trailingslashit( home_url() );
		?>
		<script>
		jQuery(function($) {
			var homeUrl = <?php echo wp_json_encode( $home ); ?>;

			$('.vs-relink-copy').on('click', function(e) {
				e.preventDefault();
				var url = $(this).data('url') || (homeUrl + $('#vs_relink_short_path').val()).replace(/\/+/g, '/');
				var btn = $(this);

				navigator.clipboard.writeText(url).then(function() {
					btn.find('.dashicons').removeClass('dashicons-admin-page').addClass('dashicons-yes');
					setTimeout(function() {
						btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-admin-page');
					}, 2000);
				});
			});

			$('#vs_relink_short_path').on('input', function() {
				var preview = homeUrl + $(this).val().replace(/^\/+/, '');
				$('#vs_relink_short_preview').text(preview);
				$('.vs-relink-copy').data('url', preview);
			});
		});
		</script>
		<?php
	}

	/**
	 * Whether current screen is the custom link editor.
	 */
	private function is_link_editor_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && 'admin_page_' . LinkEditorPage::PAGE_SLUG === $screen->id;
	}

	/**
	 * AJAX Get all ReLink IDs for batch check.
	 */
	public function ajax_get_ids(): void {
		check_ajax_referer( 'vs_relink_data_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$ids = LinkChecker::get_all_relink_ids();
		wp_send_json_success( $ids );
	}

	/**
	 * AJAX Check a single link.
	 */
	public function ajax_check_single(): void {
		check_ajax_referer( 'vs_relink_data_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error();
		}

		$result               = LinkChecker::check_link( $post_id );
		$result['post_title'] = get_the_title( $post_id );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX JSON Export handler.
	 */
	public function ajax_export_json(): void {
		check_ajax_referer( 'vs_relink_data_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$data = DataService::export_to_json();
		wp_send_json_success( $data );
	}

	/**
	 * AJAX JSON Import handler.
	 */
	public function ajax_import_json(): void {
		check_ajax_referer( 'vs_relink_data_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$links = isset( $_POST['links'] ) ? wp_unslash( $_POST['links'] ) : array();
		if ( ! is_array( $links ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import payload.', 'vs-relink' ) ) );
		}
		$find    = sanitize_text_field( wp_unslash( (string) ( $_POST['find'] ?? '' ) ) );
		$replace = sanitize_text_field( wp_unslash( (string) ( $_POST['replace'] ?? '' ) ) );

		$result = DataService::import_from_json( $links, $find, $replace );
		flush_rewrite_rules();
		wp_send_json_success( $result );
	}

	/**
	 * Handle htaccess download.
	 */
	public function handle_htaccess_download(): void {
		if ( ! isset( $_GET['vs_relink_download_htaccess'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot download these rules.', 'vs-relink' ), 403 );
		}

		check_admin_referer( 'vs_relink_download_htaccess' );

		$htaccess = DataService::generate_htaccess();

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=".htaccess-relink-export"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plaintext rewrite-rule download; escaping would corrupt the file.
		echo $htaccess;
		exit;
	}

	/**
	 * AJAX Migration handler.
	 */
	public function ajax_migrate(): void {
		check_ajax_referer( 'vs_relink_migration_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$result = MigrationService::run_pretty_link_migration();
		flush_rewrite_rules();
		wp_send_json_success( $result );
	}

	/**
	 * Add the menu pages.
	 */
	public function add_reports_page(): void {
		add_submenu_page(
			'edit.php?post_type=vs_relink',
			__( 'Reports', 'vs-relink' ),
			__( 'Reports', 'vs-relink' ),
			'manage_options',
			'vs-relink-reports',
			array( $this, 'render_reports_page' )
		);

		add_submenu_page(
			'edit.php?post_type=vs_relink',
			__( 'Tools', 'vs-relink' ),
			__( 'Tools', 'vs-relink' ),
			'manage_options',
			'vs-relink-tools',
			array( $this, 'render_tools_page' )
		);

		add_submenu_page(
			'edit.php?post_type=vs_relink',
			__( 'Settings', 'vs-relink' ),
			__( 'Settings', 'vs-relink' ),
			'manage_options',
			'vs-relink-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {
		register_setting(
			'vs_relink_settings',
			'vs_relink_base',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_base' ),
				'default'           => 're',
			)
		);
		register_setting(
			'vs_relink_settings',
			'vs_relink_exclude_bots',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_flag' ),
				'default'           => '1',
			)
		);
		register_setting(
			'vs_relink_settings',
			'vs_relink_log_retention',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_retention' ),
				'default'           => '0',
			)
		);
		register_setting(
			'vs_relink_settings',
			'vs_relink_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_webhook_url' ),
				'default'           => '',
			)
		);
		register_setting(
			'vs_relink_settings',
			'vs_relink_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_webhook_secret' ),
				'default'           => '',
			)
		);
		register_setting(
			'vs_relink_settings',
			'vs_relink_click_throttle',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_flag' ),
				'default'           => '1',
			)
		);
		register_setting(
			'vs_relink_settings',
			'vs_relink_trusted_proxies',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_trusted_proxies' ),
				'default'           => '',
			)
		);
		register_setting(
			'vs_relink_settings',
			'vs_relink_create_rate_limit',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_create_rate' ),
				'default'           => 120,
			)
		);
	}

	/**
	 * Render the reports page.
	 */
	public function render_reports_page(): void {
		require_once VS_RELINK_PATH . 'src/Admin/Views/reports-view.php';
	}

	/**
	 * Render the tools page.
	 */
	public function render_tools_page(): void {
		require_once VS_RELINK_PATH . 'src/Admin/Views/tools-view.php';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		require_once VS_RELINK_PATH . 'src/Admin/Views/settings-view.php';
	}
}
