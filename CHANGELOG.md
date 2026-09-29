# Changelog

All notable changes to **VS ReLink** are documented here.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
 versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.0] — 2026-09-29

### Security

- ReLinks use custom capabilities (`edit_relinks`, `publish_relinks`, and the related primitives). Author and Editor can no longer publish site redirects. Administrators keep full access.
- Partner domains and affiliate suffixes require `manage_options` or `manage_relink_partners`. The partner taxonomy is not exposed in REST.
- JSON import stores only `http`/`https` targets, caps the batch size, and creates rows through `LinkFactory`.
- Query forwarding copies allowlisted keys only (`utm_*`, `gclid`, `fbclid`, filterable) and does not replace parameters already on the target.
- Public clicks are throttled per IP and per IP+link. The visitor is still redirected. Client IP defaults to `REMOTE_ADDR`; `X-Forwarded-For` is used only for configured trusted proxies.
- Redirects refuse non-http(s) targets. Redirect type is limited to 301, 302, or 307.
- Settings values are sanitized on save. Optional webhook HMAC (`X-VS-Relink-Signature`). `.htaccess` download requires a nonce.
- REST abilities declare argument schemas. Health check is paged and rate-limited. Export and health check stay on `manage_options`.

### Added

- REST `relink/preview-link` and `relink/lookup-link` for Application Password bots (`publish_relinks` or `manage_relink`).
- Per-user create rate limit and a short audit log of REST creates (`vs_relink_create_audit`).
- Settings for trusted proxies, click throttle, create limit, and webhook secret.

### Changed

- `relink/create-link` and `relink/get-stats` accept `publish_relinks` or `manage_relink` instead of requiring `manage_options`.
- `wp relink create --dry-run` prints the redirect type from the same `LinkFactory` preview used by REST.
- Existing published short URLs and permalinks are unchanged. Click retention still defaults to keep forever and does not delete rows unless a retention period is saved.
- `composer phpcs` uses `phpcs.xml.dist` (WordPress-Extra and PHPCompatibilityWP for PHP 8.1+). PSR-4 class filenames stay as they are so Composer autoload keeps working.

## [2.0.1] — 2026-07-28

### Changed

- Hardened `LegacyMigrator` for upgrade-time VS 1.x `lw_*` → `vs_*` DB rename (detect remaining, repair if flag set with leftovers).
- `wp relink migrate [--dry-run]` reports before/after counts.
- Admin notice when legacy storage IDs remain.

## [2.0.0] — 2026-07-28

### Added

- Standalone bootstrap (no `verysimple/vs-core` / HubMenu / GitHub updater).
- Canonical `Storage\Ids` (`vs_relink`, `vs_relink_*` options/meta/table).
- Temporary `Database\LegacyMigrator` (lw → vs) + `wp relink migrate`.

### Fixed

- Add Link white screen: auto-draft redirect runs on `load-{hook}` before admin headers.

### Changed

- **Breaking:** storage CPT/taxonomy/meta/options/table IDs moved from `lw_*` to `vs_*` (migrator required for existing sites).
- Branding strings use VS ReLink.

## [1.3.0] — 2026-06-20

### Added

- **Very Simple** rebrand from VS ReLink (`vs-relink`, `Vs\ReLink\`, `verysimple/vs-core` hub/updater).
- `LegacyIds` storage contract — same CPT, meta, options, and click table as lw-relink (no DB migration).

### Fixed

- Bootstrap loads `vendor/autoload.php` before `AutoloadGuard` so WP-CLI activation works.

## [1.2.0] — 2026-06-08

### Added

- **Partner taxonomy** (`vs_relink_partner`) — configure affiliate domains and URL suffix per partner (e.g. Sonoff `?ref=66&utm_source=affiliate`).
- **Affiliate link workflow** — Original URL + Partner → computed Target URL + auto-suggested short slug on the link edit screen.
- Domain-based partner auto-suggestion in admin (`link-metabox.js`).
- `PartnerUrlBuilder`, `LinkFactory` for shared create/preview logic (admin, REST, CLI).
- REST ability **`relink/create-link`** — programmatic affiliate link creation.
- WP-CLI **`wp relink create`** with `--url`, `--partner`, `--slug`, `--dry-run`.
- **Partner** column in the ReLinks list table; export/import includes `original_url` and `partner`.
- **Reports dashboard** — summary cards, filters (date range, link, group), Chart.js click timeline, ranked tables (links, referrers, countries, user agents).
- **Clickable click counts** in the list table and reports — opens Reports with the relevant link filter applied.
- **Short URL field** on the link edit screen with one-click copy (`ShortUrlHelper`).
- `ReportsHelper` for shared report query/filter logic; extended `StatsRepository` aggregations.

### Changed

- **Native admin UI** — branded shell with sidebar navigation (Links, Add Link, Partners, Groups, Reports, Tools, Settings) across all ReLink screens.
- Custom **link editor page** replaces the Gutenberg CPT screen; block editor disabled for `vs_relink`.
- Unified `admin.css` replaces `admin-reports.css`; reports and settings pages use the new shell layout.

## [1.1.0] — 2026-06-04

First public release.

### Added

- Custom post type `vs_relink` with hierarchical slugs and configurable permalink base (default `re`).
- Redirect types **301**, **302**, **307**; optional query-parameter forwarding to target URL.
- Click tracking in `{prefix}vs_relink_clicks` (IP, referrer, user agent, bot flag).
- Per-link tracking toggle; global bot exclusion and log retention settings.
- **Auto-linker** — keyword-based first-match links in post content.
- Link options: nofollow, sponsored, forward parameters, redirect type.
- Outbound **webhook** (JSON POST on tracked clicks).
- Taxonomy **Link Groups** for grouped short URLs.
- **ReLinks → Settings**, **Reports**, **Tools** admin screens.
- Tools: JSON import/export, Pretty Links migration, batch link checker (AJAX).
- REST WordPress Abilities API (`health-check`, stats).
- WP-CLI commands for maintenance and migration.
- PSR-4 autoload under `Vs\ReLink`; PHP 8.1+ strict types.

### Documentation

- Comprehensive `README.md` (install, URLs, features, schema, architecture).

[Unreleased]: https://github.com/bekreative/vs-relink/compare/v2.1.0...HEAD
[2.1.0]: https://github.com/bekreative/vs-relink/compare/v2.0.1...v2.1.0
[1.3.0]: https://github.com/bekreative/vs-relink/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/bekreative/vs-relink/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/bekreative/vs-relink/releases/tag/v1.1.0
