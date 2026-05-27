=== DeloPay ===
Contributors: delopay
Tags: payments, ecommerce, checkout, hosted-payment
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Take online payments through DeloPay's hosted checkout. Manage products, orders and refunds from one admin panel without handling card data.

== Description ==

DeloPay turns your site into a merchant storefront. Shoppers complete payment inside an iframe served by DeloPay, so card data never reaches your server.

= Why DeloPay =

DeloPay is a composable payments orchestrator that connects to multiple payment providers — including Stripe, Klarna, PaySePro and NOWPayments — through a single API. Once your site is paired with your DeloPay merchant account, you can:

* Switch payment methods on and off from the DeloPay control center without redeploying.
* Keep card data off your site — payment details are entered on the hosted checkout and forwarded directly to your chosen connector. Your server and database never see a card number.
* Get unified reporting on costs, refunds and reconciliation across every connector.

= What this plugin gives you =

* **Product catalog** — manage products and categories from the admin. Each category gets its own page automatically with a configurable hero (eyebrow, title, subtitle).
* **Hosted checkout** — drop `[delopay_checkout]` on any page; the buyer pays inside a DeloPay-served iframe.
* **Server-rendered cart** — `[delopay_cart]` totals up against the trusted catalog so prices can't be tampered with on the client.
* **Storefront shortcodes** — `[delopay_products]`, `[delopay_product]`, `[delopay_categories]`, `[delopay_category_hero]`, `[delopay_complete]`.
* **Signed webhooks** — every DeloPay webhook is verified with HMAC-SHA512 in constant time before any state mutation.
* **Refunds in the admin** — full and partial refunds in `DeloPay → Orders`, pushed to the connector and reconciled by a 15-minute background cron.
* **One-click pairing** — `Connect to DeloPay` runs an OAuth-style handshake from the Settings screen; the API key is provisioned automatically and stored on the server (never exposed to the browser).
* **Multi-currency, minor units** — all prices stored as integer minor units, formatted server-side in the buyer's locale.
* **Standalone admin pages** — Dashboard, Products, Categories, Orders, Branding, Business profile, Settings. WP-CLI compatible (`wp option get wp_delopay_settings`).
* **Pairs with the DeloPay Shop theme** for a turn-key storefront, or use any theme via the shortcodes above.

= How the integration works =

1. The plugin holds your DeloPay API key on the server only — never echoed to the browser or stored unhashed where it could be exfiltrated.
2. On checkout, the plugin creates an order on the DeloPay backend and renders an iframe pointing at the hosted checkout (`checkout.delopay.net`). The shopper completes payment there.
3. DeloPay sends a signed webhook back to `/wp-json/delopay/v1/webhook`; the plugin verifies the HMAC and updates the order state in the database.
4. Refunds initiated from the admin are forwarded to DeloPay's `/refunds` API and reconciled by a recurring background job.

= Requirements =

* A DeloPay merchant account — sign up at [delopay.net](https://delopay.net).
* PHP 7.4+ (the version-floor headers above cover the rest).
* HTTPS on the front-end (required for the hosted checkout iframe to embed).

== External services ==

This plugin connects to the DeloPay payment platform (https://delopay.net) so the site can accept payments and stay in sync with the merchant catalog. Specifically:

* The plugin sends authenticated API requests to `https://api.delopay.net` (and the sandbox host `https://sandbox-api.delopay.net` while testing) for: creating and listing products and categories, creating orders, issuing refunds, and exchanging the connect handshake. Each request includes the merchant API key (server-side only) and the request payload (e.g. product fields, order line items, customer email at checkout time).
* The plugin embeds a hosted checkout iframe served from `https://checkout.delopay.net` on the order page. The shopper's browser loads that page directly from DeloPay; payment data is entered there and never touches this site.
* The plugin receives webhook callbacks from DeloPay at `/wp-json/delopay/v1/webhook` to keep order state in sync. Each delivery is verified with HMAC-SHA512 against the configured webhook secret.

DeloPay terms of service: https://delopay.net/terms
DeloPay privacy policy: https://delopay.net/privacy

By activating and connecting this plugin you acknowledge that order, product and customer data is transmitted to DeloPay under the terms above.

== Installation ==

1. Upload the `wp-delopay` folder to `/wp-content/plugins/` (or install via the Plugins screen).
2. Activate the plugin — a default **Home** category and matching page are created automatically.
3. Go to **DeloPay → Settings**, click **Connect to DeloPay**, and complete the handshake.
4. Add products under **DeloPay → Products**. New products land in the Home category by default.
5. Optional: add more categories under **DeloPay → Categories** — each one publishes its own page.
6. Add a webhook endpoint in your DeloPay dashboard pointing at `https://your-site.tld/wp-json/delopay/v1/webhook`.

== Shortcodes ==

* `[delopay_products limit="24" columns="3" category="home"]` — product grid (filter optional).
* `[delopay_product id="123"]` — single product card.
* `[delopay_categories]` — index of all active categories.
* `[delopay_category_hero category="<slug>"]` — eyebrow / title / subtitle hero for a category page (auto-injected into seeded category pages; falls back to a spacing-only block when the hero is empty).
* `[delopay_cart]` — shopper's cart with line items, subtotal and a checkout button.
* `[delopay_checkout]` — order creation + checkout iframe.
* `[delopay_complete]` — post-payment status page.

== Changelog ==

= 1.0.0 =
* Initial release.
