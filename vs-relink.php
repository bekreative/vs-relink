<?php
/**
 * Plugin Name:       VS ReLink
 * Plugin URI:        https://github.com/bekreative/vs-relink
 * Description:       Lightweight link redirection and deep tracking plugin.
 * Version:           1.3.0
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

use Vs\Core\Admin\HubMenu;
use Vs\Core\Bootstrap\AutoloadGuard;
use Vs\Core\I18n\TextDomain;
use Vs\Core\Updater\GitHubReleaseUpdater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VS_RELINK_VERSION', '1.3.0' );
define( 'VS_RELINK_FILE', __FILE__ );
define( 'VS_RELINK_PATH', plugin_dir_path( __FILE__ ) );
define( 'VS_RELINK_URL', plugin_dir_url( __FILE__ ) );
define( 'VS_RELINK_BASENAME', plugin_basename( __FILE__ ) );

$vs_relink_autoload = VS_RELINK_PATH . 'vendor/autoload.php';
if ( is_readable( $vs_relink_autoload ) ) {
	require_once $vs_relink_autoload;
}

AutoloadGuard::require_vendor( VS_RELINK_PATH, Plugin::class, 'VS ReLink' );

if ( ! class_exists( Plugin::class ) ) {
	return;
}

TextDomain::load( 'vs-relink', VS_RELINK_PATH );
HubMenu::boot();
GitHubReleaseUpdater::register( 'vs-relink', VS_RELINK_FILE, VS_RELINK_VERSION );

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
		if ( is_plugin_active( 'lw-relink/lw-relink.php' ) ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'VS ReLink: deactivate and remove the legacy lw-relink plugin to avoid conflicts.', 'vs-relink' );
			echo '</p></div>';
		}
	}
);
