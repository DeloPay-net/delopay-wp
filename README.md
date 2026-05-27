# wp-delopay

Everything you need to sell on WordPress with DeloPay:

- **`plugin/`** — the WP DeloPay plugin (custom post type for products,
  REST API, hosted-checkout iframe wiring, webhook receiver, refund
  flow, admin UI for orders + business profile).
- **`theme/`** — the DeloPay Shop theme: a clean, opinionated
  storefront that pairs with the plugin's shortcodes.
- **`wp-test/`** — Docker setup that boots the latest WordPress with
  both bind-mounted, so editing either folder shows up live in the
  browser.

## Quick start

```bash
cd wp-test
docker compose up -d
open http://localhost:7200
```

Then in the WP admin:

1. **Appearance → Themes** → activate **DeloPay Shop**.
2. **Plugins** → activate **WP DeloPay**.
3. **DeloPay → Settings** → paste API key, webhook secret, profile id.
4. Create three pages with the `[delopay_products]`,
   `[delopay_checkout]` and `[delopay_complete]` shortcodes.
5. Add products under **DeloPay → Products** and start selling.

Full walk-through: [`wp-test/README.md`](wp-test/README.md).

## Why a plugin *and* a theme

The plugin is the integration: products, orders, refunds, the iframe
checkout, the webhook receiver. It's portable — drop it into any
WordPress install with any theme.

The theme is opinionated chrome around it: type, layout, hero, single-
product page. It reads the plugin's business-profile fields when the
plugin is active, and degrades gracefully when it isn't. They're
shipped together but you can swap either one.
