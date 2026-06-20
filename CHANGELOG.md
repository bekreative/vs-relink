# Changelog

All notable changes to **LW ReLink** are documented here.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
 versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] — 2026-06-20

### Added

- **Very Simple** rebrand from LW ReLink (`vs-relink`, `Vs\ReLink\`, `verysimple/vs-core` hub/updater).
- `LegacyIds` storage contract — same CPT, meta, options, and click table as lw-relink (no DB migration).

### Fixed

- Bootstrap loads `vendor/autoload.php` before `AutoloadGuard` so WP-CLI activation works.

## [1.2.0] — 2026-06-08

### Added

- **Partner taxonomy** (`lw_relink_partner`) — configure affiliate domains and URL suffix per partner (e.g. Sonoff `?ref=66&utm_source=affiliate`).
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
- Custom **link editor page** replaces the Gutenberg CPT screen; block editor disabled for `lw_relink`.
- Unified `admin.css` replaces `admin-reports.css`; reports and settings pages use the new shell layout.

## [1.1.0] — 2026-06-04

First public release.

### Added

- Custom post type `lw_relink` with hierarchical slugs and configurable permalink base (default `re`).
- Redirect types **301**, **302**, **307**; optional query-parameter forwarding to target URL.
- Click tracking in `{prefix}lw_relink_clicks` (IP, referrer, user agent, bot flag).
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

[Unreleased]: https://github.com/bekreative/vs-relink/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/bekreative/vs-relink/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/bekreative/vs-relink/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/bekreative/vs-relink/releases/tag/v1.1.0
