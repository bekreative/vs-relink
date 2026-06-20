<?php
/**
 * Stable storage identifiers (unchanged from lw-relink for live data).
 *
 * @package Vs\ReLink\Storage
 */

declare(strict_types=1);

namespace Vs\ReLink\Storage;

/**
 * WordPress storage keys that must not change across lw → vs upgrade.
 */
final class LegacyIds {

	public const POST_TYPE = 'lw_relink';

	public const TAXONOMY_LINK_GROUP = 'lw_link_group';

	public const TAXONOMY_PARTNER = 'lw_relink_partner';

	public const TABLE_CLICKS = 'lw_relink_clicks';

	public const OPTION_DB_VERSION = 'lw_relink_db_version';

	public const OPTION_BASE = 'lw_relink_base';

	public const META_PREFIX = '_lw_relink_';

	public const PARTNER_META_DOMAINS = '_lw_partner_domains';

	public const PARTNER_META_URL_SUFFIX = '_lw_partner_url_suffix';
}
