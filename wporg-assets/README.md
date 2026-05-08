# wordpress.org submission assets

These files are uploaded to the **WordPress.org SVN `/assets/` directory**, which is a separate path from the plugin/theme `/trunk/` and is **not** included in the user-facing zip. They drive the listing on wordpress.org/plugins/wp-delopay and wordpress.org/themes/delopay-shop.

## plugin/

| File | Purpose | Required size |
|---|---|---|
| `icon.svg` | Vector plugin icon shown on the directory listing and in the WP admin updates screen. Sourced from the DeloPay symbol mark (`delopay-control-center/public/logo-symbol.svg`). | viewBox 0 0 64 64; ships as scalable SVG |
| `icon-256x256.png` | Raster fallback for the icon, rendered from `icon.svg` via Inkscape | 256×256 |
| `icon-128x128.png` | Smaller raster fallback | 128×128 |
| *(todo)* `banner-772x250.png` | Banner shown on the plugin page (raster only — SVG not supported) | 772×250 |
| *(todo)* `banner-1544x500.png` | Retina banner | 1544×500 |
| *(todo)* `screenshot-1.png`, `screenshot-2.png`, … | Screenshots referenced from `plugin/readme.txt` | any reasonable resolution |

## theme/

| File | Purpose | Required size |
|---|---|---|
| *(todo)* (Theme directory takes screenshots from inside the theme zip — see `theme/screenshot.png` once added.) | | 1200×900 |

## How to upload (once the slug is approved)

```bash
# After WP.org approves the plugin and provides SVN access:
svn co https://plugins.svn.wordpress.org/wp-delopay/ wp-delopay-svn
cp wporg-assets/plugin/* wp-delopay-svn/assets/
cd wp-delopay-svn && svn add assets/* && svn ci -m "Add directory listing assets"
```

The build script (`bin/build-zips.sh`) does **not** include this folder in either zip.
