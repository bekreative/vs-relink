# LW ReLink

Lightweight WordPress link shortener, redirection, and click tracking — no bloat, PHP 8.1+, strict types.

Create short URLs, redirect visitors to any destination, and measure clicks with IP, referrer, and user-agent logging. Built for performance: high-frequency click data lives in a dedicated table, not post meta.

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.0+ (recommended) |
| PHP | 8.1+ |

## Install

1. Clone or copy into `wp-content/plugins/vs-relink`.
2. Optional: run `composer install` in the plugin directory (PSR-4 autoload; a built-in fallback autoloader works without Composer).
3. Activate **LW ReLink** in WordPress admin.
4. Go to **ReLinks → Settings**, set your **Permalink Base** (default: `re`), then save.
5. Visit **Settings → Permalinks** and click **Save Changes** once (flushes rewrite rules).

## Quick start

1. **ReLinks → Add New**
2. Set the **slug** (post name) — this becomes the short path, e.g. `summer-sale`.
3. Enter the **Target URL** (where visitors should land).
4. Copy the short URL from the list table (clipboard icon) or use the permalink shown when editing.

### Example URLs

With permalink base `re` (default):

```
https://example.com/re/summer-sale/
https://example.com/re/campaigns/black-friday/
```

Hierarchical slugs (parent/child ReLinks):

```
https://example.com/re/parent/child/
```

With an **empty** permalink base (root-level — use with care, may conflict with pages):

```
https://example.com/summer-sale/
```

## Features

### Short links & redirects

- Custom post type `lw_relink` with hierarchical slugs (folder-like structure)
- Redirect types: **301**, **302**, **307**
- Optional **forward query parameters** from the short URL to the target
- **404 fallback matching** — resolves deep paths even when rewrite rules miss a match

### Click tracking

- Dedicated table: `{prefix}lw_relink_clicks`
- Per click: link ID, timestamp, IP, referrer, user agent, bot flag
- Per-link toggle: enable/disable tracking
- Global **bot exclusion** (Settings) — skips crawlers in statistics
- **Log retention** — auto-delete old clicks (30 / 90 / 180 / 365 days, or keep forever)

### Auto-linker

- Comma-separated **keywords** on each ReLink
- Automatically links the first occurrence in post content (`the_content`)
- Useful for affiliate or internal campaign keywords

### Partner affiliate links

Configure partners under **ReLinks → Partners**. Each partner stores:

- **Domains** — e.g. `sonoff.tech` (used to auto-suggest the partner when pasting a product URL)
- **URL suffix** — e.g. `?ref=66&utm_source=affiliate`

**Affiliate workflow** (ReLinks → Add New):

1. Paste **Original URL** (clean product URL, no affiliate params)
2. Select **Partner** (auto-suggested from domain)
3. **Target URL** is computed automatically (redirect destination)
4. **Short URL** slug is suggested from the product path (editable)

Legacy links without a partner still use a manual Target URL only — no behavior change.

One product URL can have **separate ReLinks per partner** (duplicate detection is per original URL + partner).

### Link options (per ReLink)

| Option | Description |
|--------|-------------|
| Original URL | Clean product URL (affiliate workflow) |
| Partner | Affiliate partner; appends configured suffix |
| Target URL | Redirect destination (manual or computed) |
| Redirect type | 301 / 302 / 307 |
| No Follow | SEO attribute on auto-generated links |
| Sponsored | Mark sponsored auto-links |
| Forward parameters | Append `?utm_*` etc. from short URL to target |
| Enable tracking | Toggle click logging |

### Webhooks

- Global **Outbound Webhook URL** (Settings)
- Non-blocking JSON `POST` on each tracked click (`event: link_click`)

### Taxonomies

- **Link Groups** (`lw_link_group`) — hierarchical folders in admin
- **Partners** (`lw_relink_partner`) — affiliate domain + URL suffix configuration

### Admin

Native WordPress admin shell with sidebar navigation (no Gutenberg editor for links):

| Menu | Purpose |
|------|---------|
| **All Links** | List with short URL, partner, clicks, copy-to-clipboard |
| **Add Link** | Custom link editor (affiliate URLs, redirect, auto-linker) |
| **Partners** | Affiliate domain + URL suffix configuration |
| **Groups** | Folder taxonomy for organizing links |
| **Reports** | Charts and click analytics (Chart.js) |
| **Tools** | Migration, import/export, health scan, `.htaccess` export |
| **Settings** | Permalink base, bots, retention, webhook |

## Tools

**ReLinks → Tools**

### Pretty Link Lite migration

Imports links from the `wp_prli_links` table (Pretty Link Lite) into `lw_relink` posts, preserving slugs where possible.

### JSON export / import

- Export all ReLinks to JSON (download)
- Import on another site with optional **domain search & replace** on target URLs

### Server redirection (`.htaccess`)

Download Apache rewrite rules for static redirects — useful when decommissioning WordPress on a legacy host.

### Link health scanner

Batch AJAX scan: verifies each ReLink returns HTTP 200 from its short URL.

## WP-CLI

```bash
# Check all links (HTTP status)
wp relink check

# Overview: total links and clicks
wp relink stats

# Create affiliate link (preview)
wp relink create --url="https://sonoff.tech/en-hu/products/sonoff-basic-din-rail-matter-over-wifi-smart-switch-basic-1gsp" --partner=sonoff-official --dry-run

# Create affiliate link
wp relink create --url="https://sonoff.tech/..." --partner=sonoff-official
```

## REST / Abilities API

Admin-only routes under `wp-abilities/v1` (when supported):

| Ability | Description |
|---------|-------------|
| `relink/health-check` | Run link health check |
| `relink/get-stats` | Aggregate statistics |
| `relink/export` | Export links as JSON |
| `relink/create-link` | Create affiliate link from original URL + partner |

Requires `manage_options`.

## Database

### Clicks table: `wp_lw_relink_clicks`

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT | Primary key |
| `link_id` | BIGINT | `lw_relink` post ID |
| `timestamp` | DATETIME | Click time |
| `ip_address` | VARCHAR(45) | Visitor IP |
| `referer` | TEXT | HTTP Referer |
| `user_agent` | TEXT | Browser / bot UA |
| `is_bot` | TINYINT | Bot flag |

### Post meta (per ReLink)

| Meta key | Purpose |
|----------|---------|
| `_lw_relink_original_url` | Clean product URL (affiliate workflow) |
| `_lw_relink_target_url` | Destination URL |
| `_lw_relink_type` | Redirect code (301, 302, 307) |
| `_lw_relink_keywords` | Auto-linker keywords |
| `_lw_relink_nofollow` | `yes` / empty |
| `_lw_relink_sponsored` | `yes` / empty |
| `_lw_relink_forward_params` | `yes` / empty |
| `_lw_relink_tracking` | `no` disables tracking |

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `lw_relink_base` | `re` | Permalink prefix (empty = root) |
| `lw_relink_exclude_bots` | `1` | Exclude bots from stats |
| `lw_relink_log_retention` | `0` | Days to keep clicks (`0` = forever) |
| `lw_relink_webhook_url` | — | Outbound webhook endpoint |
| `lw_relink_db_version` | `1.1.0` | Schema version |

## Architecture

```
vs-relink/
├── vs-relink.php          # Bootstrap, activation
├── composer.json          # PSR-4: Vs\ReLink\
├── src/
│   ├── Plugin.php
│   ├── PostTypes/ReLink.php
│   ├── Taxonomies/LinkGroup.php
│   ├── Taxonomies/Partner.php
│   ├── Core/
│   │   ├── RedirectHandler.php   # template_redirect
│   │   ├── Permalinks.php        # Rewrite rules
│   │   ├── AutoLinker.php
│   │   ├── PartnerUrlBuilder.php
│   │   ├── LinkFactory.php
│   │   ├── WebhookService.php
│   │   └── LogRotation.php
│   ├── Database/Schema.php
│   ├── Stats/StatsRepository.php
│   ├── Admin/                    # UI, migration, tools
│   ├── Api/AbilitiesController.php
│   └── CLI/CLI.php
└── CURSOR.md              # Agent / dev conventions
```

## Development

```bash
composer install   # optional; fallback autoloader included
```

### Conventions

- `declare(strict_types=1);` in all PHP files
- Namespace: `Vs\ReLink`
- Prefer small, focused classes (~200 lines)
- See [CURSOR.md](CURSOR.md) for architecture rules

### Useful commands

```bash
wp relink stats
wp relink check
```

After changing **Permalink Base**, save plugin settings and flush permalinks.

## Pairing with LW Download

Use **LW ReLink** for marketing/affiliate short links and **LW Download** for file delivery and download statistics. They are independent plugins and can run on the same site.

| Plugin | Repo |
|--------|------|
| LW Download | [bekreative/lw-download](https://github.com/bekreative/lw-download) |
| LW ReLink | [bekreative/vs-relink](https://github.com/bekreative/vs-relink) |

## License

GPL-2.0-or-later

## Author

[WPSuli](https://WPSuli.hu)
