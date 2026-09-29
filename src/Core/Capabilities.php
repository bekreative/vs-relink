<?php

declare(strict_types=1);

namespace Vs\ReLink\Core;

/**
 * Custom capabilities for ReLinks and partner configuration.
 *
 * Administrators receive these capabilities. Author and Editor do not.
 */
final class Capabilities {

	public const MANAGE = 'manage_relink';

	public const MANAGE_PARTNERS = 'manage_relink_partners';

	public const CAPS_VERSION = '2.1.0';

	private const VERSION_OPTION = 'vs_relink_caps_version';

	/**
	 * Primitive caps granted to Administrator and available for custom roles.
	 *
	 * @return string[]
	 */
	public static function administrator_caps(): array {
		return array(
			'edit_relinks',
			'edit_others_relinks',
			'edit_published_relinks',
			'edit_private_relinks',
			'publish_relinks',
			'delete_relinks',
			'delete_others_relinks',
			'delete_published_relinks',
			'delete_private_relinks',
			'read_private_relinks',
			self::MANAGE,
			self::MANAGE_PARTNERS,
		);
	}

	/**
	 * Caps a bot or limited editor needs to create short links (not partners, not export).
	 *
	 * @return string[]
	 */
	public static function bot_caps(): array {
		return array(
			'read',
			'edit_relinks',
			'publish_relinks',
			self::MANAGE,
		);
	}

	/**
	 * Give every manage_options user the ReLink primitives without a role edit.
	 *
	 * @param array<string, bool> $allcaps
	 * @param string[]            $caps
	 * @param mixed[]             $args
	 * @return array<string, bool>
	 */
	public static function grant_admin_caps( array $allcaps, array $caps, array $args, \WP_User $user ): array {
		unset( $caps, $args, $user );

		if ( empty( $allcaps['manage_options'] ) ) {
			return $allcaps;
		}

		foreach ( self::administrator_caps() as $cap ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	/**
	 * Persist Administrator caps so role editors can see them.
	 */
	public static function persist_admin_caps( bool $force = false ): void {
		if ( ! $force && get_option( self::VERSION_OPTION ) === self::CAPS_VERSION ) {
			return;
		}

		$role = get_role( 'administrator' );
		if ( $role ) {
			foreach ( self::administrator_caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}

		update_option( self::VERSION_OPTION, self::CAPS_VERSION, false );
	}

	/**
	 * Whether the current user may create or publish a ReLink.
	 *
	 * Logged-in Author and Editor are not allowed. WP-CLI without --user is allowed
	 * because that process is the server operator, not an HTTP visitor.
	 */
	public static function current_user_may_publish(): bool {
		if ( current_user_can( 'publish_relinks' ) || current_user_can( self::MANAGE ) ) {
			return true;
		}

		return defined( 'WP_CLI' ) && WP_CLI && ! is_user_logged_in();
	}

	/**
	 * Whether the current user may read or create links through the bot API.
	 */
	public static function current_user_may_use_bot_api(): bool {
		return current_user_can( 'publish_relinks' ) || current_user_can( self::MANAGE );
	}

	/**
	 * Whether the current user may change partner domains or affiliate suffixes.
	 */
	public static function current_user_may_manage_partners(): bool {
		if ( current_user_can( 'manage_options' ) || current_user_can( self::MANAGE_PARTNERS ) ) {
			return true;
		}

		return defined( 'WP_CLI' ) && WP_CLI && ! is_user_logged_in();
	}
}
