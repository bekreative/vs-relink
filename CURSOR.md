# LW ReLink - Development Rules & Guidelines

This document outlines the architecture and standards for the LW ReLink WordPress plugin.

## 1. Core Principles
- **Lightweight First**: Minimize database bloat. Use custom tables for high-frequency data (clicks).
- **No Dependencies**: Pure PHP 8.1+ and WordPress APIs. No heavy PHP libraries or frameworks.
- **Strict Typing**: All files must use `declare(strict_types=1);`.
- **PSR-4 Compliance**: Autoloading via `Vs\ReLink` namespace.

## 2. Directory Structure
- `src/Admin`: Admin UI controllers, views, and migration logic.
- `src/Core`: Core plugin functionality (Permalinks, Tracking, Redirection).
- `src/Database`: Migration engine and table schemas.
- `src/PostTypes`: Custom Post Type registrations.
- `src/Taxonomies`: Custom Taxonomy (Link Groups) registrations.
- `src/Stats`: Statistics repository and data handling.

## 3. Database Schema
- **Clicks Table**: `wp_lw_relink_clicks`
  - `id`: BIGINT PRIMARY KEY
  - `link_id`: BIGINT (References ReLink CPT ID)
  - `timestamp`: DATETIME
  - `ip_address`: VARCHAR(45)
  - `user_agent`: TEXT
  - `referrer`: TEXT

## 4. UI/UX Standards
- **Premium Aesthetics**: Use Chart.js for reports. Professional, clean admin cards.
- **AJAX for Long Tasks**: Migration and link scanning must use batch AJAX requests with progress bars.
- **Copy-to-Clipboard**: Short URLs should always be easily copyable from the list table.

## 5. WordPress Hooks
- Use `template_redirect` for handling redirections to ensure speed.
- Filter `post_type_link` to dynamically inject group slugs into permalinks.
- Register settings via `register_setting` and separate view files for admin pages.

## 6. Coding Style
- Follow Official WordPress Coding Standards (ignoring lint warnings for core functions in isolated environments).
- Max 200 lines per class file where possible.
- Dependency Injection for service initialization.
