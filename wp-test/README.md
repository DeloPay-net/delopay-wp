# wp-test — local WordPress for the DeloPay plugin & theme

Single docker-compose file that boots the **latest WordPress** + MariaDB
and bind-mounts both the plugin and the theme into the container so
edits show up live.

## Quickstart

```bash
cd wp-delopay/wp-test
docker compose up -d
# Wait ~10s for first boot, then open:
open http://localhost:7200
```

WordPress will walk you through the 5-step install. Pick anything for
title / admin user — these are throwaway. The DB is already wired up
(database `wordpress`, user `wordpress`, password `wordpress`).

After the install is done:

1. **Appearance → Themes** → activate **DeloPay Shop**.
2. **Plugins → Installed Plugins** → activate **WP DeloPay**.
3. **DeloPay → Settings** → paste your API key, webhook secret,
   Project ID (`prj_…`), Shop / Profile ID (`pro_…`).
   - **API base URL** stays at `https://dashboard.delopay.net/api`.
   - **Control center URL** for local dev: `http://localhost:4200`.
   - **Checkout iframe origin** for local dev: `http://localhost:5173`.
4. Settings → Permalinks → Save Changes (just to flush rewrite rules so
   `/wp-json/delopay/v1/*` is reachable).
5. Create three pages and add the shortcodes:
   - **Shop** → `[delopay_products]`
   - **Checkout** → `[delopay_checkout]`
   - **Order complete** → `[delopay_complete]`
6. Back in **DeloPay → Settings**, pick the "Order complete" page in the
   *Order-complete page* dropdown. Save.
7. **DeloPay → Products** → Add a product (title, image, price,
   currency).
8. Visit your **Shop** page → click Buy now → the Checkout page loads
   the hosted iframe.

## Webhook URL

In WP admin: **DeloPay → Settings → Webhook URL** has a Copy button.
The URL will be:

```
http://localhost:7200/wp-json/delopay/v1/webhook
```

Important: **DeloPay's webhook sender needs to reach that URL**. Three
options:

1. **DeloPay backend running on the host** (most common in this repo).
   The host can reach `http://localhost:7200` directly — paste the URL
   as-is.
2. **DeloPay backend running in another container.** Use
   `http://host.docker.internal:7200/wp-json/delopay/v1/webhook` so the
   sibling container can reach the WP container via the host.
3. **Public DeloPay instance.** Tunnel:
   ```bash
   ngrok http 7200
   # then paste https://<id>.ngrok.app/wp-json/delopay/v1/webhook
   ```

## Useful commands

```bash
# Tail WP logs (debug.log + Apache access/error)
docker compose logs -f wordpress

# Run wp-cli against the install
docker compose run --rm wpcli plugin list
docker compose run --rm wpcli theme list
docker compose run --rm wpcli option get wp_delopay_settings --format=json
docker compose run --rm wpcli rewrite flush

# Reset everything (DB + WP files + uploads, keeps your plugin/theme source)
docker compose down -v
```

## File layout

```
wp-delopay/                          ← project root
├── plugin/                          ← WP DeloPay plugin (source)
│   ├── wp-delopay.php
│   ├── includes/
│   ├── assets/
│   └── templates/
├── theme/                           ← DeloPay Shop theme (source)
│   ├── style.css
│   ├── functions.php
│   ├── header.php
│   ├── footer.php
│   ├── front-page.php
│   ├── page.php
│   └── single-delopay_product.php
└── wp-test/                         ← this folder
    ├── docker-compose.yml
    └── README.md
```

The bind mounts in `docker-compose.yml`:

```yaml
volumes:
  - ../plugin:/var/www/html/wp-content/plugins/wp-delopay
  - ../theme:/var/www/html/wp-content/themes/delopay-shop
```

So edits in `plugin/` or `theme/` are picked up on the next page load —
no rebuild, no copy.
