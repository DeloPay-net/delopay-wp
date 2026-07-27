=== DeloPay ===
Contributors: delopay
Tags: payments, ecommerce, checkout, hosted-payment
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Take online payments through DeloPay's hosted checkout. Manage products, orders and refunds from one admin panel without handling card data.

== Description ==

DeloPay turns your site into a merchant storefront. Shoppers complete payment inside an iframe served by DeloPay, so card data never reaches your server.

**Licensing note:** only this plugin — the WordPress integration code — is open source (MIT), as required for everything hosted in the WordPress.org directory. The DeloPay platform itself (the payment service, hosted checkout and APIs it connects to) is a proprietary commercial service operated by DeloPay and is not open source. A DeloPay merchant account is required.

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
* **Standalone admin pages** — Dashboard, Products, Categories, Orders, Branding, Business profile, Settings. WP-CLI compatible (`wp option get delopay_settings`).
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

1. Upload the `delopay` folder to `/wp-content/plugins/` (or install via the Plugins screen).
2. Activate the plugin — a default **Home** category and matching page are created automatically.
3. Go to **DeloPay → Settings**, click **Connect to DeloPay**, and complete the handshake.
4. Add products under **DeloPay → Products**. New products land in the Home category by default.
5. Optional: add more categories under **DeloPay → Categories** — each one publishes its own page.
6. Add a webhook endpoint in your DeloPay dashboard pointing at `https://your-site.tld/wp-json/delopay/v1/webhook`.

== Shortcodes ==

* `[delopay_products limit="24" columns="3" category="home" excerpt_length="30"]` — product grid (filter optional; `excerpt_length` is in words, `0` shows the full description).
* `[delopay_product id="123" excerpt_length="30"]` — single product card.
* `[delopay_categories]` — index of all active categories.
* `[delopay_category_hero category="<slug>"]` — eyebrow / title / subtitle hero for a category page (auto-injected into seeded category pages; falls back to a spacing-only block when the hero is empty).
* `[delopay_cart]` — shopper's cart with line items, subtotal and a checkout button.
* `[delopay_checkout]` — order creation + checkout iframe.
* `[delopay_complete]` — post-payment status page.

== Changelog ==

= 1.3.0 =
* Orders now show which environment they ran in. A test order carries a **Test** badge in the Orders list and an Environment row on the order detail; a live order says so explicitly.
* The environment is recorded per order at the moment it is created, not read from the Test mode setting when the screen is drawn — so turning test mode off does not relabel the test orders you already have. That mix of test and live rows in one list, with no way to tell them apart, is a bookkeeping problem when you reconcile.
* DeloPay's own answer wins where they differ: a payment processor still set to test mode in the DeloPay dashboard makes an order a test even if this store never asked for one.
* Orders placed before this version show **Unknown** rather than claiming to be live. The plugin has no way to find out after the fact — check those in the DeloPay dashboard.

= 1.2.0 =
* New **Test mode** setting. Every order is sent to DeloPay as a test payment: it runs against your payment processors' sandbox and stays out of your live transactions and analytics. Previously the only way to test was to switch each processor into test mode in the DeloPay dashboard and remember to switch it back.
* A warning appears on every admin screen while test mode is on, because a store left in it takes orders that never get paid.
* Off by default, and updating the plugin never changes what your store does today.
* Note: a processor with no sandbox credentials stored in DeloPay is used with its live credentials, so it will charge for real even in test mode. Add sandbox credentials for the processors that offer them.

= 1.1.0 =
* Checkout metadata on products and categories — key/value pairs sent with the payment when that product is bought. A category's pairs apply to every product in it; a product's own keys win on collision.
* Use them with DeloPay's checkout custom-field rules to show a field only for certain products: give a product `product_type` = `spotify`, then set the account-login fields to appear only when that matches. Previously every buyer saw every custom field.
* Product SKUs, ids, category slugs and item count are now always sent as payment metadata, so rules work without configuring anything by hand.
* Reserved keys (`order_id`, `site_url`, and the automatic ones above) can no longer be overwritten by product or category metadata.
* Checkout metadata is included in product export/import, so a transferred catalog keeps working against the same rules.
* Fix: database schema upgrades now run on plugin update. Previously they only ran on activation, so a new column never reached an already-active install.

= 1.0.1 =
* New `excerpt_length` attribute on `[delopay_products]` and `[delopay_product]` — number of words to show in the product card description; `0` shows the full untruncated description.

= 1.0.0 =
* Initial release.
* Hosted checkout with products, orders and refunds managed from one admin panel — no card data handled on-site.
* Manual capture mode: authorize at checkout, then capture or cancel each order from the Orders screen.
* Disputes: read-only admin screen to list and inspect chargebacks pulled live from DeloPay.
* Webhooks: handle payment, dispute and subscription/invoice events (with `delopay_dispute_event` / `delopay_subscription_event` action hooks) across payment states (authorized, captured).
