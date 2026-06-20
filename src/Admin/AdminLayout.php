<?php

declare(strict_types=1);

namespace Vs\ReLink\Admin;

use Vs\ReLink\PostTypes\ReLink;
use Vs\ReLink\Taxonomies\LinkGroup;
use Vs\ReLink\Taxonomies\Partner;

/**
 * Shared native admin shell (header + sidebar navigation).
 */
final class AdminLayout {

	/**
	 * Register hooks for list table and body class.
	 */
	public static function register(): void {
		add_action( 'admin_notices', [ self::class, 'render_list_header' ], 1 );
		add_filter( 'admin_body_class', [ self::class, 'admin_body_class' ] );
	}

	/**
	 * Append body class on ReLink admin screens.
	 *
	 * @param string $classes Existing classes.
	 */
	public static function admin_body_class( string $classes ): string {
		if ( self::is_relink_screen() ) {
			$classes .= ' lwr-admin-screen';
		}

		return $classes;
	}

	/**
	 * Whether the current screen belongs to LW ReLink.
	 */
	public static function is_relink_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}

		return ReLink::POST_TYPE === $screen->post_type
			|| str_starts_with( $screen->id, 'lw_relink' )
			|| str_starts_with( $screen->id, 'edit-lw_relink' )
			|| in_array( $screen->taxonomy, [ Partner::TAXONOMY, LinkGroup::TAXONOMY ], true );
	}

	/**
	 * Navigation items.
	 *
	 * @return array<string, array{label: string, url: string, icon: string}>
	 */
	public static function get_nav_items(): array {
		return [
			'links'    => [
				'label' => __( 'All Links', 'vs-relink' ),
				'url'   => admin_url( 'edit.php?post_type=' . ReLink::POST_TYPE ),
				'icon'  => 'dashicons-list-view',
			],
			'add'      => [
				'label' => __( 'Add Link', 'vs-relink' ),
				'url'   => admin_url( 'admin.php?page=vs-relink-edit' ),
				'icon'  => 'dashicons-plus-alt',
			],
			'partners' => [
				'label' => __( 'Partners', 'vs-relink' ),
				'url'   => admin_url( 'edit-tags.php?taxonomy=' . Partner::TAXONOMY . '&post_type=' . ReLink::POST_TYPE ),
				'icon'  => 'dashicons-groups',
			],
			'groups'   => [
				'label' => __( 'Groups', 'vs-relink' ),
				'url'   => admin_url( 'edit-tags.php?taxonomy=' . LinkGroup::TAXONOMY . '&post_type=' . ReLink::POST_TYPE ),
				'icon'  => 'dashicons-category',
			],
			'reports'  => [
				'label' => __( 'Reports', 'vs-relink' ),
				'url'   => admin_url( 'edit.php?post_type=' . ReLink::POST_TYPE . '&page=vs-relink-reports' ),
				'icon'  => 'dashicons-chart-bar',
			],
			'tools'    => [
				'label' => __( 'Tools', 'vs-relink' ),
				'url'   => admin_url( 'edit.php?post_type=' . ReLink::POST_TYPE . '&page=vs-relink-tools' ),
				'icon'  => 'dashicons-admin-tools',
			],
			'settings' => [
				'label' => __( 'Settings', 'vs-relink' ),
				'url'   => admin_url( 'edit.php?post_type=' . ReLink::POST_TYPE . '&page=vs-relink-settings' ),
				'icon'  => 'dashicons-admin-generic',
			],
		];
	}

	/**
	 * Detect active nav item from current screen.
	 */
	public static function detect_active_nav(): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return 'links';
		}

		if ( 'lw_relink_page_vs-relink-settings' === $screen->id ) {
			return 'settings';
		}
		if ( 'lw_relink_page_vs-relink-tools' === $screen->id ) {
			return 'tools';
		}
		if ( 'lw_relink_page_vs-relink-reports' === $screen->id ) {
			return 'reports';
		}
		if ( 'admin_page_vs-relink-edit' === $screen->id ) {
			return 'add';
		}
		if ( Partner::TAXONOMY === $screen->taxonomy ) {
			return 'partners';
		}
		if ( LinkGroup::TAXONOMY === $screen->taxonomy ) {
			return 'groups';
		}
		if ( 'edit-' . ReLink::POST_TYPE === $screen->id ) {
			return 'links';
		}

		return 'links';
	}

	/**
	 * Render branded header + sidebar navigation.
	 *
	 * @param string $active Active nav key.
	 */
	public static function render( string $active = '' ): void {
		if ( $active === '' ) {
			$active = self::detect_active_nav();
		}

		$items = self::get_nav_items();
		?>
		<div class="lwr-admin-app">
			<div class="lwr-admin-brand">
				<span class="lwr-admin-brand__icon dashicons dashicons-admin-links" aria-hidden="true"></span>
				<div class="lwr-admin-brand__text">
					<strong><?php esc_html_e( 'LW ReLink', 'vs-relink' ); ?></strong>
					<span class="lwr-admin-brand__version"><?php echo esc_html( VS_RELINK_VERSION ); ?></span>
				</div>
			</div>
			<div class="lwr-admin-layout">
				<nav class="lwr-admin-nav" aria-label="<?php esc_attr_e( 'ReLink navigation', 'vs-relink' ); ?>">
					<ul>
						<?php foreach ( $items as $key => $item ) : ?>
							<li class="<?php echo $active === $key ? 'is-active' : ''; ?>">
								<a href="<?php echo esc_url( $item['url'] ); ?>">
									<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
									<?php echo esc_html( $item['label'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
				<div class="lwr-admin-content">
		<?php
	}

	/**
	 * Close admin layout wrapper.
	 */
	public static function render_end(): void {
		?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Inject header on the links list table screen.
	 */
	public static function render_list_header(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		if ( 'edit-' . ReLink::POST_TYPE === $screen->id ) {
			echo '<div class="lwr-list-shell">';
			self::render( 'links' );
			echo '<div class="lwr-list-actions">';
			echo '<h2>' . esc_html__( 'All ReLinks', 'vs-relink' ) . '</h2>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=vs-relink-edit' ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'vs-relink' ) . '</a>';
			echo '</div>';
			echo '<div class="lwr-list-table-area">';
			return;
		}

		if ( in_array( $screen->taxonomy, [ Partner::TAXONOMY, LinkGroup::TAXONOMY ], true ) && 'edit-tags' === $screen->base ) {
			$active = Partner::TAXONOMY === $screen->taxonomy ? 'partners' : 'groups';
			echo '<div class="lwr-list-shell">';
			self::render( $active );
			echo '<div class="lwr-list-table-area">';
		}
	}

	/**
	 * Close list/taxonomy table shell in footer.
	 */
	public static function render_list_footer(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$is_list = 'edit-' . ReLink::POST_TYPE === $screen->id;
		$is_tax  = in_array( $screen->taxonomy, [ Partner::TAXONOMY, LinkGroup::TAXONOMY ], true ) && 'edit-tags' === $screen->base;

		if ( ! $is_list && ! $is_tax ) {
			return;
		}

		echo '</div>';
		self::render_end();
		echo '</div>';
	}

	/**
	 * Render a settings section header.
	 *
	 * @param string $title       Section title.
	 * @param string $description Optional description.
	 */
	public static function section_title( string $title, string $description = '' ): void {
		echo '<div class="lwr-section-head">';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( $description !== '' ) {
			echo '<p>' . esc_html( $description ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Open a settings panel.
	 */
	public static function panel_open(): void {
		echo '<div class="lwr-panel">';
	}

	/**
	 * Close a settings panel.
	 */
	public static function panel_close(): void {
		echo '</div>';
	}
}
