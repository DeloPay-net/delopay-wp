# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

A WordPress **plugin + theme** pair shipped together for selling via DeloPay's
hosted checkout. Both are bind-mounted into a local WP container under
`wp-test/` for development; `bin/build-zips.sh` produces the wordpress.org
submission zips.

## Common commands

```bash
# Lint + static analysis (run from repo root)
composer install                  # one-time, pulls phpcs/phpstan/WPCS
composer lint                     # phpcs (WPCS)
composer lint:fix                 # phpcbf — auto-fixes whitespace/style
composer analyze                  # phpstan level 6 with WP stubs
composer analyze:baseline         # regenerate phpstan-baseline.neon (only if you have a deliberate reason)
composer install-hooks            # one-time: enable .githooks/pre-commit (phpcs on staged .php)

# Theme CSS (Tailwind output is committed, must be rebuilt after template edits)
cd theme && npm install
npm run build                     # one-shot, minified
npm run watch                     # rebuild on change

# Local WordPress (Podman or Docker — compose file works with both)
cd wp-test
podman-compose up -d              # http://localhost:7200, admin/admin
podman-compose down               # stop; add -v to also wipe DB + uploads

# wp-cli inside the running stack (entrypoint is `wp --allow-root`)
podman-compose run --rm wpcli plugin activate wp-delopay
podman-compose run --rm wpcli theme activate delopay-shop
podman-compose run --rm wpcli post create --post_type=page --post_status=publish --post_title="Shop" --post_content="[delopay_products]"

# Distributable zips (requires theme/assets/css/tailwind.css to exist — npm run build first)
bin/build-zips.sh                 # → dist/wp-delopay.zip and dist/delopay-shop.zip
```

CI (`.github/workflows/lint.yml`) runs `composer lint` + `composer analyze` on push to main and on every PR.

## Architecture

### Plugin / theme split

- **`plugin/`** is the integration layer. Owns the data, the REST API, the webhook receiver, the iframe wiring, and the admin UI. Portable — drops into any WP install with any theme.
- **`theme/`** is opinionated chrome that pairs with the plugin. **Refuses to activate** if the plugin isn't active (`theme/inc/plugin-required.php`); on the frontend it renders a 503 wall rather than half-broken markup.

### Plugin data model — custom tables, not CPTs

Despite a stale README mention of "custom post type", the plugin uses **four custom DB tables**:
`delopay_products`, `delopay_categories`, `delopay_orders`, `delopay_refunds`.
Schema is created/upgraded via `dbDelta` from `WP_Delopay_Orders` / `WP_Delopay_Products`. **All other classes get table names via `WP_Delopay_Orders::table_orders() / table_refunds() / table_products() / table_categories()`** — never hardcode `$wpdb->prefix . 'delopay_*'` directly.

### Plugin bootstrap (read in order)

1. `plugin/wp-delopay.php` — defines `WP_DELOPAY_VERSION/FILE/DIR/URL` and `require_once`s every class in `includes/`.
2. `WP_Delopay_Plugin::instance()` (in `class-delopay-plugin.php`) is the root singleton. Its constructor instantiates the other singletons in dependency order: Settings → Categories → Products → Orders → REST → Webhook → Connect → Admin → Shortcodes → Plugin_Details.
3. Registers a 15-minute custom cron (`wp_delopay_fifteen_minutes`) that calls `WP_Delopay_Orders::reconcile_pending_refunds`.

### Trust model — webhook is the source of truth

- Order id is **minted server-side** and sent as `metadata.order_id`; DeloPay echoes it back on every webhook so the two sides can join cleanly.
- `POST /wp-json/delopay/v1/webhook` verifies HMAC-SHA512 (header `x-webhook-signature-512`) **before** any state change; the secret comes from `WP_Delopay_Settings`.
- `GET /wp-json/delopay/v1/orders/{id}` returns the webhook-confirmed status; the success-redirect query params are display-only.
- Refunds always go through `POST /wp-json/delopay/v1/admin/refund` behind `current_user_can( 'manage_options' )`. Never call DeloPay's refund endpoint from the browser.
- The first-party REST permission gate (`require_first_party` in `class-delopay-rest.php`) accepts either an `x-wp-nonce` header (or `_wpnonce` param) **or** a same-origin `Origin`/`Referer` match.

### Theme styling

- Tailwind v3 + `@tailwindcss/typography` + `@tailwindcss/forms`. **The compiled output `theme/assets/css/tailwind.css` is committed**; if you edit templates or `src/input.css` you must `npm run build` to regenerate it (CI does not).
- Colors use CSS variables in **space-separated RGB tuple form** (e.g. `--ds-bg: 250 250 247;`) so Tailwind alpha modifiers (`bg-bg/80`) keep working.
- The Customizer writes inline `:root { --ds-bg: …; … }` in `<head>` via `theme/inc/customizer-output.php`, beating the defaults from `src/input.css`.
- Plugin-emitted classnames (`wp-delopay-grid`, `wp-delopay-product`, …) are safelisted in `tailwind.config.js` because they live outside the theme's content-scan paths.

## Lint / static-analysis conventions

The repo is held to a **clean** `composer lint` and `composer analyze`. Things to know before adding sniff suppressions:

- **Boilerplate docblock sniffs are intentionally disabled** in `phpcs.xml.dist` (FunctionComment.Missing, FileComment.Missing, ClassComment.Missing, VariableComment.Missing). The codebase doesn't use per-symbol docblocks — don't add filler stubs to "satisfy" the linter. Quality sniffs (DocComment.MissingShort, ParamCommentFullStop) remain on so docblocks that *do* exist must still be well-formed.
- **Data-layer files** (`class-delopay-categories.php`, `class-delopay-orders.php`, `class-delopay-products.php`, `uninstall.php`) carry a file-level `phpcs:disable` block for `WordPress.DB.DirectDatabaseQuery.*` and `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` because (a) the plugin owns the schema so `wp_cache_*` would just shadow data we control, and (b) `$wpdb->prepare()` does not accept table names as placeholders. Match the pattern in new data-layer code.
- **`phpstan-baseline.neon` grandfathers ~690 missing-type warnings**. New code is checked at level 6 — meaning new functions/methods/properties need type annotations (native or PHPDoc). Don't regenerate the baseline to bury new errors; only regenerate after a deliberate typing pass that fixes a swath of existing entries.
- `phpstan-bootstrap.php` declares `WP_DELOPAY_*` constants for static analysis only — it's not loaded at runtime.

## Local dev gotchas (Podman / SELinux)

`wp-test/docker-compose.yml` is tuned for rootless Podman on Fedora. Two non-obvious choices:

- Image refs are **fully-qualified** (`docker.io/library/mariadb:11`) so Podman doesn't prompt for a registry. Docker still pulls these unchanged.
- Plugin/theme bind mounts use **`:z` (lowercase)**, not `:Z`. The `wordpress` and `wpcli` services share the same host paths; `:Z` would relabel per-container with unique MCS categories that fight each other and lock one container out. Don't change to `:Z`.

If the container can't read plugin/theme files (`search_theme_directories(): … is not readable` in `wp-content/debug.log`), the SELinux label is the cause — re-bring up the stack so Podman re-applies the `:z` label.

## Distribution

The plugin and theme are submitted to wordpress.org as separate slugs (`wp-delopay`, `delopay-shop`). `bin/build-zips.sh` rsyncs each into `dist/_stage/` excluding repo-only files (`wp-test/`, `bin/`, `node_modules/`, `src/`, `tailwind.config.js`, the top-level `README.md` — the per-package `readme.txt` ships instead) and zips them. The script aborts if `theme/assets/css/tailwind.css` is missing — always `npm run build` before packaging.
