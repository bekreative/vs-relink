<?php

declare(strict_types=1);

namespace Vs\ReLink;

use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Taxonomies\LinkGroup;
use Vs\ReLink\Taxonomies\Partner;
use Vs\ReLink\Admin\PartnerTermMeta;
use Vs\ReLink\Core\Capabilities;
use Vs\ReLink\Core\RedirectHandler;
use Vs\ReLink\Core\Permalinks;
use Vs\ReLink\Admin\AdminController;
use Vs\ReLink\Core\AutoLinker;
use Vs\ReLink\Core\LogRotation;
use Vs\ReLink\Api\AbilitiesController;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'user_has_cap', [ Capabilities::class, 'grant_admin_caps' ], 10, 4 );
		add_action( 'init', [ Capabilities::class, 'persist_admin_caps' ], 1 );
		PartnerTermMeta::register_meta_guards();
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'init', [ $this, 'register_taxonomies' ] );
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	private function init_components(): void {
		// core components
		new RedirectHandler();
		new Permalinks();
		new AutoLinker();
		new LogRotation();
		new AbilitiesController();

		// admin components
		if ( is_admin() ) {
			new AdminController();
			new PartnerTermMeta();
		}
	}

	/**
	 * Register custom post types.
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		ReLink::register();
	}

	/**
	 * Register taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		LinkGroup::register();
		Partner::register();
	}
}
