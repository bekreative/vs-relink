<?php
/**
 * Canonical storage identifiers.
 *
 * @package Vs\ReLink\Storage
 */

declare(strict_types=1);

namespace Vs\ReLink\Storage;

/**
 * Canonical storage identifiers for VS ReLink.
 */
final class Ids {

	public const POST_TYPE = 'vs_relink';

	public const TAXONOMY_LINK_GROUP = 'vs_link_group';

	public const TAXONOMY_PARTNER = 'vs_relink_partner';

	public const TABLE_CLICKS = 'vs_relink_clicks';

	public const OPTION_DB_VERSION = 'vs_relink_db_version';

	public const OPTION_BASE = 'vs_relink_base';

	public const META_PREFIX = '_vs_relink_';

	public const PARTNER_META_DOMAINS = '_vs_partner_domains';

	public const PARTNER_META_URL_SUFFIX = '_vs_partner_url_suffix';
}
