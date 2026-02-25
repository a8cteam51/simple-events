# Simple Events – Agent Instructions

This document provides project-specific context for AI agents working on this codebase. Read it before making changes.

---

## Project Overview

**Simple Events** is a Gutenberg-first WordPress plugin for event management that integrates with WooCommerce Box Office. Users create events as custom posts, add event info and tickets via blocks, and can display events in a calendar or archive.

**Repository**: https://github.com/a8cteam51/simple-events

---

## Tech Stack

| Layer    | Technology |
|----------|------------|
| Platform | WordPress 6.5+ |
| PHP      | 8.0+ |
| Blocks   | Gutenberg (`@wordpress/scripts` v29) |
| PHP deps | Composer (`eluceo/ical` for calendar export) |
| JS deps  | Node 20.10+, npm 10.2+ |

---

## Directory Structure

```
simple-events/
├── plugin.php              # Plugin bootstrap, constants, includes
├── src/
│   ├── blocks/             # Gutenberg block sources (built to build/blocks)
│   │   ├── calendar/       # Calendar block
│   │   ├── event-info/     # Event info block (date, location, etc.)
│   │   ├── event-tickets/  # Ticket products block
│   │   ├── countdown/      # Countdown block
│   │   ├── upcoming-events/
│   │   ├── inner-blocks/    # Container for event content
│   │   ├── loop-event-info/
│   │   ├── external-link/
│   │   └── past-events-notice/
│   ├── variations/         # Block variations (built to build/variations)
│   ├── classes/            # PHP classes
│   ├── templates/          # PHP templates (calendar, archive, single)
│   ├── assets/js/          # Admin JS (built to build/js)
│   ├── event-functions.php
│   ├── template-functions.php
│   ├── template-hooks.php
│   ├── woocommerce-hooks.php
│   ├── rest-api.php
│   └── ...
├── build/                  # Compiled output (blocks, variations, js)
├── vendor/                 # Composer dependencies
└── .github/workflows/      # CI (build-release, phpcs, php-syntax)
```

---

## Commands

### Install dependencies (MUST run before build)

```bash
composer install
npm install
```

### Build

```bash
npm run build
```

Builds production output:
- `build/blocks` – blocks from `src/blocks`
- `build/variations` – block variations from `src/variations`
- `build/js` – admin JS from `src/assets/js`

For development with watch mode:

```bash
npm start
```

Runs all `start:*` scripts in parallel (blocks, variations, admin).

### Lint

```bash
# PHP (WordPress coding standards)
composer run-script lint:php

# JavaScript
npm run lint:js
npm run lint:js:fix

# CSS/Styles
npm run lint:css
npm run lint:css:fix

# Format JS
npm run format:js
```

### Tests

- `npm run test:unit` – unit tests (no test files present in repo as of this writing)
- `npm run test:e2e` – E2E tests (requires WordPress environment)

There are no unit or E2E tests checked in. If adding tests, follow `@wordpress/scripts` patterns.

### Release build (CI)

The `build-release` workflow runs on release and:
1. `composer run-script packages-install -- --no-dev`
2. `npm ci --force`
3. `npm run build`
4. `npm run build:variations`
5. Zips the plugin (excluding dev files) for GitHub release

---

## Conventions

### PHP

- **Prefixes**: Use `se_` or `simple_events_` for functions, hooks, globals. Defined in `.phpcs.xml`.
- **Text domain**: Always `simple-events` for i18n.
- **Namespaces**: None; prefixes are used instead.
- **PHPCS**: Extends `vendor/a8cteam51/team51-configs/quality-tools/phpcs.xml.dist`. Run `composer run-script lint:php` before committing.
- **Auto-format**: `composer run-script format:php` (phpcbf).

### JavaScript / Blocks

- **Block registration**: Each block has `block.json` plus `index.js`. Use `wp-scripts` build.
- **Block namespace**: `simple-events/` (e.g. `simple-events/calendar`).
- **Lint**: Follow `@wordpress/scripts` ESLint config.

### Git

- **Branches**: `trunk` and `develop` are primary. CI runs on push/PR to both.
- **PR template**: `.github/PULL_REQUEST_TEMPLATE.md` – fill in changes and testing instructions.
- **Commits**: Prefer descriptive messages. No enforced format.

---

## Architectural Decisions

1. **No WordPress core edits**  
   All logic lives in the plugin. Do not modify core or suggest core edits.

2. **Gutenberg-first**  
   Event content is composed with blocks. Block structure and attributes drive templates and logic.

3. **Dual post types**  
   - `se-event` – main event post type  
   - `se-event-date` – generated instances for recurring/flexible dates; `class-se-event-query-dates.php` manages creation/updates.

4. **WooCommerce Box Office integration**  
   Ticket products come from WooCommerce. The `se_event_updated_query_dates` cron updates event dates. See `woocommerce-hooks.php`.

5. **Template loading**  
   Uses `class-se-template-loader.php` for archive/single/calendar templates. Templates live in `src/templates/`.

6. **Build output layout**  
   Built files go to `build/`. The plugin expects `build/blocks`, `build/variations`, `build/js`. Do not change output paths without checking all enqueues.

7. **Composer autoloader**  
   Required for plugin bootstrap. `vendor/autoload.php` must exist; otherwise an admin notice is shown.

---

## Critical Rules

- **MUST NOT** edit WordPress core files.
- **MUST** run `composer install` and `npm install` before any build.
- **MUST** run `npm run build` before testing the plugin; blocks require compilation.
- **MUST** use prefixes `se_` or `simple_events_` for PHP globals and text domain `simple-events`.
- **MUST** ensure `build/` and `vendor/` are present for the plugin to work; they are in `.gitignore` and built/installed locally or in CI.

---

## Common Pitfalls

1. **Editing core**  
   Do not modify WordPress core. Implement behavior via hooks, filters, and plugin code.

2. **Forgetting to build**  
   Block changes require `npm run build`. Unbuilt changes will not appear in the editor or frontend.

3. **Missing Composer deps**  
   If `vendor/autoload.php` is missing, the plugin shows an error and does not load. Run `composer install`.

4. **Wrong block output paths**  
   `package.json` `files` and plugin loading expect `build/blocks`, `build/variations`, `build/js`. Do not change webpack `output-path` without updating all consumers.

5. **Event date logic**  
   Event dates are managed by `class-se-event-query-dates.php` and `class-se-event-dates.php`. Changes to how dates are stored or queried can break calendar and archive views; review migrations in `class-se-migrate-events.php`.

6. **WooCommerce Box Office**  
   The plugin depends on WooCommerce and WooCommerce Box Office. Ticket blocks and order auto-completion assume these are active.

7. **PHP version**  
   Plugin requires PHP 8.0+. Avoid PHP 7.x-only syntax.

---

## Hooks & Extension Points

See `README.md` for filter and action documentation, including:

- `se_event_previous_link_text`, `se_event_next_link_text`, `se_event_calendar_link_text`
- `se_event_update_query_dates_interval`, `se_event_update_dates_search_range`, `se_event_update_query_dates_skip`
- `se_event_updated_query_dates`
- `se_calendar_export_query_args`, `se_calendar_export_event_location`, `se_calendar_export_event`, `se_calendar_export_calendar`, `se_calendar_export_rendered`

When adding hooks, use the existing naming pattern and document them in the README.

---

## More Information

- **README**: Hooks, extensions, focal-point plugin link
- **Plugin header**: `plugin.php` – version, PHP/WordPress requirements
- **Block schemas**: `src/blocks/*/block.json` – attributes and supports
