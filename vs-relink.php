<?php
/**
 * Plugin Name:       VS ReLink
 * Plugin URI:        https://github.com/bekreative/vs-relink
 * Description:       Lightweight link redirection and deep tracking plugin.
 * Version:           2.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            WPSuli
 * Author URI:        https://wpsuli.hu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vs-relink
 * Domain Path:       /languages
 *
 * @package Vs\ReLink
 */

declare(strict_types=1);

namespace Vs\ReLink;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VS_RELINK_VERSION', '2.0.1' );
define( 'VS_RELINK_FILE', __FILE__ );
define( 'VS_RELINK_PATH', plugin_dir_path( __FILE__ ) );
define( 'VS_RELINK_URL', plugin_dir_url( __FILE__ ) );
define( 'VS_RELINK_BASENAME', plugin_basename( __FILE__ ) );

$vs_relink_autoload = VS_RELINK_PATH . 'vendor/autoload.php';

if ( ! is_readable( $vs_relink_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			esc_html_e(
				'VS ReLink: Autoloader not found. Run "composer install" in the plugin directory or install from a release ZIP.',
				'vs-relink'
			);
			echo '</p></div>';
		}
	);
	return;
}

require_once $vs_relink_autoload;

if ( ! class_exists( Plugin::class ) ) {
	return;
}

load_plugin_textdomain( 'vs-relink', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

/**
 * Main plugin instance.
 */
function vs_relink(): Plugin {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new Plugin();
	}

	return $instance;
}

register_activation_hook( __FILE__, [ Database\Schema::class, 'activate' ] );

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		Database\LegacyMigrator::maybe_migrate();
		vs_relink();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			CLI\CLI::register();
		}
	}
);

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'lw-relink/lw-relink.php' ) ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'VS ReLink: deactivate and remove the legacy lw-relink plugin to avoid conflicts.', 'vs-relink' );
			echo '</p></div>';
		}
		if ( class_exists( Database\LegacyMigrator::class ) && Database\LegacyMigrator::needs_admin_notice() ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e(
				'VS ReLink: legacy lw_* storage IDs were detected. They are renamed automatically on load; run `wp relink migrate --dry-run` to inspect, or `wp relink migrate` to repair.',
				'vs-relink'
			);
			echo '</p></div>';
		}
	}
);
