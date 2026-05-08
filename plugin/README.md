# wp-delopay

WordPress plugin for the DeloPay hosted-checkout integration. Mirrors the
patterns from [`delopay-shop-demo`](../delopay-shop-demo): the merchant
API key stays on the server, prices are resolved from a trusted catalog,
the webhook is the source of truth, and refunds always go through the
backend.

## What you get

- **DeloPay → Settings** — API key, webhook secret, profile id, API base
  URL, default currency, checkout-iframe origin, complete-page picker.
- **DeloPay → Business Profile** — name, contact email, support phone/URL.
- **DeloPay → Products** — full WP CRUD for a `delopay_product` custom
  post type (title, description, image, price-in-decimal, currency, SKU).
- **DeloPay → Orders** — list of orders with status, refund total and
  webhook freshness; drill in for line items, refund history and a
  refund button.
- **DeloPay → Dashboard** — setup checklist, activity counts, the
  webhook URL to paste into your DeloPay dashboard, and the shortcode
  reference.
- **REST API** at `/wp-json/delopay/v1/`:
  - `POST /orders` — create a hosted-checkout payment
  - `GET  /orders/{id}` — read webhook-confirmed status (with reconcile
    fallback)
  - `POST /webhook` — DeloPay → here, HMAC-SHA512 verified
  - `POST /admin/refund` — admin-only refunds
- **Shortcodes**:
  - `[delopay_products columns="3" limit="24"]`
  - `[delopay_product id="123"]` or `sku="…"`
  - `[delopay_checkout]` — reads `?product_id=…&quantity=…`, creates the
    order, embeds the iframe at `${checkout_origin}/pay/{merchant}/{payment}`
  - `[delopay_complete]` — reads `?order_id=…`, polls the REST endpoint,
    re-fetches once after 2.5s for async APMs

## Install

```bash
# from this repo:
ln -s "$(pwd)/wp-delopay" /path/to/wp-content/plugins/wp-delopay
```

Then activate from **Plugins** in the WP admin.

## Set up the storefront

1. **DeloPay → Settings** — paste API key + webhook secret, set the
   checkout-iframe origin (e.g. `https://checkout.delopay.net`).
2. Create a page **"Checkout"** with `[delopay_checkout]`.
3. Create a page **"Order complete"** with `[delopay_complete]`, then
   pick it in Settings → Order-complete page.
4. Create a page **"Shop"** with `[delopay_products]`.
5. Add products under **DeloPay → Products**.
6. In your DeloPay business profile, set the webhook URL to:
   `https://your-site.tld/wp-json/delopay/v1/webhook` and copy the
   matching webhook secret into the plugin Settings.

## Why this layout

Same reasoning as the demo:

- API key on the server only.
- Prices resolved server-side from the products CPT, never from the client.
- Order id minted by us, sent as `metadata.order_id`, echoed back on every
  webhook so we can join sides cleanly.
- Webhook signature verified before any state changes.
- `/wp-json/delopay/v1/orders/{id}` reads the webhook-confirmed status,
  not the redirect URL params.
- Refunds always go through the SDK from the server, behind admin
  capability checks.
