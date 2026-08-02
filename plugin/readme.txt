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
* **Standalone admin pages** — Dashboard, Products, Categories, Orders, Branding, Business profile, Settings, Logs. WP-CLI compatible (`wp option get delopay_settings`).
* **Connection health & logs** — the plugin checks that DeloPay still accepts your API key and warns you on every admin screen if it does not. `DeloPay → Logs` shows what happened, with secrets redacted, so you never need server access to diagnose a failing checkout.
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

= Replacing an existing WooCommerce store =

If this site already runs WooCommerce, activating DeloPay does not switch WooCommerce off — and usually it cannot be switched off, because many block themes hard-depend on it and the front end breaks without it. Until you replace it, both storefronts serve at once: `/shop/` keeps listing WooCommerce products with "Add to cart" buttons that dead-end in a cart nobody uses, and `/cart/`, `/checkout/` and `/my-account/` stay publicly reachable and indexable.

**DeloPay → Settings → WooCommerce** replaces it in one click. It moves WooCommerce products and its shop/cart/checkout/my-account pages to draft and permanently redirects their URLs to your DeloPay pages. Nothing is deleted, WooCommerce keeps running so your theme does not break, and an **Undo** button republishes exactly what DeloPay hid. (Browsers cache permanent redirects, so a visitor who already followed one may need a hard reload after an undo.)

**Do not put the DeloPay shortcodes on WooCommerce's own cart and checkout pages.** WooCommerce redirects its checkout page to its cart page whenever *its* cart is empty, and its cart is always empty — the DeloPay cart lives in the browser and WooCommerce cannot see it. The result is a checkout that redirects away forever with no error message anywhere. Give `[delopay_cart]` and `[delopay_checkout]` pages of their own with any slugs you like; the plugin finds them by shortcode. If it happens anyway, the plugin detects it and offers to split them apart for you.

= Wallets in the embedded checkout =

Stripe only offers Apple Pay, Google Pay and Link when the *top-level* page's domain is registered as a payment method domain on your Stripe account. The plugin renders the checkout in an iframe, so that top-level page is your WordPress site — and Stripe hides those wallets without an error.

Turn the affected methods into **native panes** on your Stripe connector in the DeloPay dashboard (Connectors → Stripe → Native payment panes). The checkout then draws its own tile for each; clicking a tile opens the DeloPay checkout in a new tab, at the top level, showing only that method. The same mechanism works for non-wallet methods such as Klarna and iDEAL.

The iframe this plugin renders already supports it: it carries `allow="payment *"` and no `sandbox` attribute, so the new tab is permitted. If your theme or a page builder wraps `[delopay_checkout]` in its own sandboxed frame, that frame must include `allow-popups`, `allow-popups-to-escape-sandbox` and `allow-top-navigation-by-user-activation` — the first two so the tab opens at all, the last one because redirect methods such as PayPal and Klarna hand off with a top-level navigation. An incomplete sandbox blocks the tab and shoppers get a fallback link instead of a working tile.

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

= Unreleased =
* **A broken connection now says so.** The Dashboard setup checklist used to show a green tick as soon as *some* API key was saved — a revoked or mistyped key kept the tick while every payment failed. It now reports whether DeloPay still **accepts** the key: connected, never connected, or key rejected.
* If DeloPay rejects the key, a notice appears on every WordPress admin screen — "your DeloPay connection is not working, payments are failing" — with a **Reconnect** button that takes you straight to the connect flow. That is the whole fix: reconnect and save.
* The check runs hourly in the background, and any real API call that comes back rejected marks the connection bad immediately. A successful call clears it just as fast, so the notice disappears on its own once you have reconnected.
* **New `DeloPay → Logs` screen.** What the plugin recorded while talking to DeloPay — timestamp, level, message, expandable details — filterable by level, with a **Copy for support** button that puts the visible page on your clipboard for a support ticket. Previously these entries only went to the server's PHP error log, which most merchants cannot read, and info/warning entries were hidden entirely unless `WP_DEBUG_LOG` was on.
* Rejected webhooks are logged too, so a webhook secret that does not match the one DeloPay signs with is visible instead of silently leaving orders stuck as pending.
* Uninstalling DeloPay republishes any WooCommerce products and pages it had hidden, so removing the plugin never leaves your storefront drafted. WooCommerce's category counts are not rebuilt — if your category menu looks empty afterwards, run WooCommerce → Status → Tools → **Recount terms**.
* API keys, webhook secrets and signatures are redacted before anything is stored. Entries are kept for 7 days, up to 500, pruned by a daily job, and everything is removed when you uninstall the plugin.
* **Replace an existing WooCommerce storefront.** New **WooCommerce** section in DeloPay → Settings (shown while WooCommerce is active, and afterwards for as long as DeloPay is still hiding its storefront — so Undo outlives deactivating Woo): one click moves Woo's products and its shop/cart/checkout/my-account pages to draft and permanently redirects `/shop`, `/cart`, `/checkout` and `/my-account` — plus single products and product category/tag archives — to your DeloPay pages. WooCommerce keeps running, so themes that depend on it do not break, and nothing is deleted. **Undo** republishes exactly what DeloPay hid and leaves anything you drafted yourself alone.
* **Detects DeloPay shortcodes sitting on WooCommerce's pages**, which silently breaks checkout: WooCommerce redirects its checkout page to its cart page whenever its own (always empty) cart is consulted, so `[delopay_checkout]` never renders. An admin notice now says so and offers to give each shortcode its own page.
* Fix: on a site that already had a page at `/cart/` or `/checkout/` — which every WooCommerce site does — activation created no DeloPay cart or checkout page at all. It now looks for a page carrying the shortcode rather than a page occupying the slug, and falls back to `delopay-cart` / `delopay-checkout` when the slug is taken. This is what pushed replace-installs into pasting the shortcodes onto Woo's pages in the first place.
* Stripe wallets in the embedded checkout: methods marked as **native panes** on your Stripe connector (Apple Pay, Google Pay, Klarna, …) now render as tiles that open a focused DeloPay checkout in a new tab — see *Wallets in the embedded checkout* above. No plugin change was needed; the checkout iframe already permits the pop-up. The readme now documents the sandbox attributes required if your theme wraps the checkout in its own frame.

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
