# VS ReLink

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
3. Activate **VS ReLink** in WordPress admin.
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

- Custom post type `vs_relink` with hierarchical slugs (folder-like structure)
- Redirect types: **301**, **302**, **307**
- Optional **forward query parameters** from the short URL to the target. Only `utm_*`, `gclid`, and `fbclid` are copied (filter `vs_relink_forward_param_keys` adds exact keys). Parameters already on the affiliate target are not replaced.
- **404 fallback matching** — resolves deep paths even when rewrite rules miss a match

### Click tracking

- Dedicated table: `{prefix}vs_relink_clicks`
- Per click: link ID, timestamp, IP, referrer, user agent, bot flag
- Per-link toggle: enable/disable tracking
- Global **bot exclusion** (Settings) — skips crawlers in statistics
- **Click throttle** (on by default) — repeated hits from the same IP on the same short link are still redirected, but extra rows and webhooks are skipped for about a minute. There is also a per-IP ceiling. Turn it off under Settings if you need every hit.
- **Client IP** is `REMOTE_ADDR` unless you list trusted proxy CIDRs. `X-Forwarded-For` and `Client-IP` are ignored when that list is empty.
- **Log retention** — auto-delete old clicks only when you choose 30 / 90 / 180 / 365 days. The default is keep forever; nothing is deleted until that setting is changed.

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

Partner terms (domains and the affiliate suffix) can be edited only by Administrators, or by a role you explicitly grant `manage_relink_partners`. The bot user below must not have that capability. Do not recreate partners that are already configured on the site.

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
| Forward parameters | Append allowlisted tracking params (`utm_*`, `gclid`, `fbclid`) without replacing affiliate params already on the target |
| Enable tracking | Toggle click logging |

### Webhooks

- Global **Outbound Webhook URL** (Settings). Only `http` and `https` are stored.
- Non-blocking JSON `POST` on each **stored** click (`event: link_click`). Throttled hits do not send a webhook.
- Optional **webhook secret**. When set, the request includes `X-VS-Relink-Signature: sha256=<hex>` where the hex is HMAC-SHA256 of the raw JSON body using that secret. Compare with a constant-time check on the raw body you received. An empty secret sends unsigned JSON, same as earlier versions. The secret is not printed on the public site.

### Who can publish ReLinks

ReLinks use their own capabilities (`edit_relinks`, `publish_relinks`, and the related delete/read variants), not the core `post` capabilities. **Author and Editor cannot create or publish ReLinks** and cannot change partner domains or affiliate suffixes.

Administrators receive every ReLink capability, including `manage_relink` and `manage_relink_partners`. Reports, Tools, Settings, export, and the health check stay on `manage_options`.

To give a custom role access later (this does not change existing partner terms):

```bash
wp role create relink_bot "ReLink Bot"
wp cap add relink_bot read edit_relinks publish_relinks manage_relink
```

Add `manage_relink_partners` only if that role should edit affiliate domains and URL suffixes. Do not add `manage_options` if the role should not export every link or change settings.

A human editor who should publish short links, but not administer the site, gets the same four capabilities as the bot role (`read`, `edit_relinks`, `publish_relinks`, `manage_relink`).

### Privacy

Click logs can contain IP addresses, referrers, and user agents. The plugin does not hash those values. Retention defaults to **keep forever**. Daily cleanup deletes rows only after an administrator sets Log Retention to 30, 90, 180, or 365 days. Document that storage in your own privacy notice if you enable tracking.

### Taxonomies

- **Link Groups** (`vs_link_group`) — hierarchical folders in admin
- **Partners** (`vs_relink_partner`) — affiliate domain + URL suffix configuration

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

Imports links from the `wp_prli_links` table (Pretty Link Lite) into `vs_relink` posts, preserving slugs where possible.

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

## REST API for Domomod Tube

Application Passwords call the same routes as before. WordPress only accepts Application Passwords over HTTPS.

Create a bot user with the ReLink caps above (not Administrator). On that user's profile, create an Application Password (or `wp user application-password create relink-bot "Domomod Tube" --porcelain`). Send it as HTTP Basic auth. Remove the spaces WordPress prints in the password.

Partner domains and affiliate suffixes on the live site are already configured. **Do not create or edit partners from the bot.** Pass the existing partner slug (ReLinks → Partners) on every call. For okosotthon.bolt.hu, use the partner whose domain list already includes that host.

| Ability | Who | Body |
|---------|-----|------|
| `relink/preview-link` | `publish_relinks` or `manage_relink` | Dry-run. Same URL, partner, slug, and target as create. Nothing is saved. |
| `relink/lookup-link` | `publish_relinks` or `manage_relink` | `original_url` + `partner`. Returns `short_url` or HTTP 404. |
| `relink/create-link` | `publish_relinks` or `manage_relink` | Creates the short link, or returns the existing one (`existed: true`). |
| `relink/get-stats` | `publish_relinks` or `manage_relink` | Aggregates. `days` is clamped to 1–366. |
| `relink/export` | `manage_options` only | Full JSON export. |
| `relink/health-check` | `manage_options` only | At most 10 links per call (`limit`, `offset`). One user cannot start another check for 30 seconds. |

`wp relink create` and `wp relink create --dry-run` use the same `LinkFactory` path as `create-link` and `preview-link`.

Create requests are limited per user (Settings → Create limit, default 120 per hour, `0` disables). Successful REST creates are appended to the option `vs_relink_create_audit` (latest 100: user, link, partner, URL, time). That log is not exposed on a public route.

```bash
# Preview (no write). Replace PARTNER_SLUG with the existing okosotthon partner slug.
curl -sS -u 'relink-bot:APPLICATION_PASSWORD' \
  -H 'Content-Type: application/json' \
  -d '{"original_url":"https://okosotthon.bolt.hu/termek/example-product","partner":"PARTNER_SLUG"}' \
  "https://example.com/wp-json/wp-abilities/v1/abilities/relink/preview-link/run"

# Lookup. HTTP 404 when this product+partner pair has no short link yet.
curl -sS -u 'relink-bot:APPLICATION_PASSWORD' \
  -H 'Content-Type: application/json' \
  -d '{"original_url":"https://okosotthon.bolt.hu/termek/example-product","partner":"PARTNER_SLUG"}' \
  "https://example.com/wp-json/wp-abilities/v1/abilities/relink/lookup-link/run"

# Create. Repeat calls return the same short_url with existed: true.
curl -sS -u 'relink-bot:APPLICATION_PASSWORD' \
  -H 'Content-Type: application/json' \
  -d '{"original_url":"https://okosotthon.bolt.hu/termek/example-product","partner":"PARTNER_SLUG","redirect_type":"301"}' \
  "https://example.com/wp-json/wp-abilities/v1/abilities/relink/create-link/run"
```

`original_url` must be `http` or `https`. `redirect_type` is `301`, `302`, or `307` (anything else is stored as `301`). Optional: `short_slug`, `title`, `tracking`, `nofollow`, `sponsored`, `forward_params`, `keywords`.

Export and health-check with the same bot user return HTTP 403.

## Database

### Clicks table: `wp_vs_relink_clicks`

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT | Primary key |
| `link_id` | BIGINT | `vs_relink` post ID |
| `timestamp` | DATETIME | Click time |
| `ip_address` | VARCHAR(45) | Visitor IP |
| `referer` | TEXT | HTTP Referer |
| `user_agent` | TEXT | Browser / bot UA |
| `is_bot` | TINYINT | Bot flag |

### Post meta (per ReLink)

| Meta key | Purpose |
|----------|---------|
| `_vs_relink_original_url` | Clean product URL (affiliate workflow) |
| `_vs_relink_target_url` | Destination URL |
| `_vs_relink_type` | Redirect code (301, 302, 307) |
| `_vs_relink_keywords` | Auto-linker keywords |
| `_vs_relink_nofollow` | `yes` / empty |
| `_vs_relink_sponsored` | `yes` / empty |
| `_vs_relink_forward_params` | `yes` / empty |
| `_vs_relink_tracking` | `no` disables tracking |

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `vs_relink_base` | `re` | Permalink prefix (empty = root). Sanitized to a slug. |
| `vs_relink_exclude_bots` | `1` | Exclude bots from stats |
| `vs_relink_log_retention` | `0` | Days to keep clicks (`0` = forever; no automatic deletion) |
| `vs_relink_click_throttle` | `1` | `0` records every eligible click |
| `vs_relink_trusted_proxies` | empty | CIDRs allowed to supply `X-Forwarded-For` |
| `vs_relink_webhook_url` | — | Outbound webhook endpoint (`http`/`https` only) |
| `vs_relink_webhook_secret` | — | HMAC key for `X-VS-Relink-Signature` |
| `vs_relink_create_rate_limit` | `120` | REST creates per user per hour (`0` = off) |
| `vs_relink_create_audit` | — | Latest bot create events (not autoloaded) |
| `vs_relink_db_version` | `1.1.0` | Schema version |

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
│   │   ├── LinkFactory.php       # create, preview, lookup, import
│   │   ├── Capabilities.php
│   │   ├── UrlGuard.php
│   │   ├── ForwardParams.php
│   │   ├── ClientIp.php
│   │   ├── ClickThrottle.php
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

Use **VS ReLink** for marketing/affiliate short links and **LW Download** for file delivery and download statistics. They are independent plugins and can run on the same site.

| Plugin | Repo |
|--------|------|
| LW Download | [bekreative/lw-download](https://github.com/bekreative/lw-download) |
| VS ReLink | [bekreative/vs-relink](https://github.com/bekreative/vs-relink) |

## License

GPL-2.0-or-later

## Author

[WPSuli](https://WPSuli.hu)
