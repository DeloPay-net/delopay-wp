=== DeloPay Shop ===
Contributors: delopay
Tags: e-commerce, custom-colors, custom-logo, custom-menu, featured-images, theme-options, threaded-comments, full-width-template
Requires at least: 6.0
Tested up to: 6.9.4
Requires PHP: 7.4
Stable tag: 0.2.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Tailwind-powered storefront theme for the DeloPay plugin. Every visual choice — colors, fonts, hero, product grid, footer — is editable in Appearance → Customize → DeloPay Shop.

== Description ==

DeloPay Shop is a lightweight storefront theme designed to pair with the DeloPay plugin. It uses a compiled Tailwind stylesheet (no runtime build) and exposes the full design system through the Customizer so non-developers can rebrand the entire shop without touching code.

The theme requires the DeloPay plugin for the storefront shortcodes (`[delopay_products]`, `[delopay_product]`, `[delopay_categories]`, `[delopay_cart]`, `[delopay_checkout]`, `[delopay_complete]`). Without the plugin the theme still works as a standard theme — only the shop functionality is unavailable.

== Installation ==

1. Upload the `delopay-shop` folder to `/wp-content/themes/` (or install via the Themes screen).
2. Activate the theme under **Appearance → Themes**.
3. Install and activate the DeloPay plugin to enable shop functionality.
4. Customize colors, typography and layout under **Appearance → Customize → DeloPay Shop**.

== Frequently Asked Questions ==

= Do I need the DeloPay plugin? =

The theme is fully functional as a standard theme without the plugin, but the storefront, product grid, cart, checkout and order pages depend on the plugin's shortcodes.

= Where do I configure colors and fonts? =

Appearance → Customize → DeloPay Shop. All design tokens are exposed there.

== Credits ==

* Tailwind CSS — MIT License — https://tailwindcss.com
* Bundled web fonts (`assets/fonts/`) are served locally from the theme. No external font CDN is contacted at runtime. Each font is licensed under the SIL Open Font License 1.1 or the Apache 2.0 License:
    * Inter, Manrope, DM Sans, Hanken Grotesk, IBM Plex Sans, Source Sans 3, Vollkorn, Lora, Playfair Display, Cormorant Garamond, Fraunces — copyright their respective authors, see https://fonts.google.com for upstream sources.

== Changelog ==

= 0.2.0 =
* Initial public release.
