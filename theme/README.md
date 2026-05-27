# DeloPay Shop — WordPress theme

Tailwind-powered storefront theme for the WP DeloPay plugin. Every
visual choice — brand, colors, typography, hero, product grid, footer —
is editable in **Appearance → Customize → DeloPay Shop**, no theme
files to edit.

> The theme **requires** the WP DeloPay plugin. If the plugin isn't
> active, the theme refuses to activate (and switches back), shows an
> admin notice, and renders a 503 wall on the frontend rather than a
> broken layout.

## What you can configure

In **Appearance → Customize → DeloPay Shop**:

| Section       | What you control                                                     |
|---------------|----------------------------------------------------------------------|
| Brand         | Tagline, whether to show the brand name as text                      |
| Colors        | Background, surface, text, muted, border, accent, accent text & hover|
| Typography    | Display + body font family (Google Fonts auto-loaded)                |
| Layout        | Max content width, corner radius, sticky header                      |
| Hero          | Eyebrow, title, subtitle, CTA label, hero image, setup helper card   |
| Product grid  | Column count, items per page                                         |
| Footer        | Copyright copy, "Payments by DeloPay" badge                          |

Business info (name, email, support contact) lives in the plugin —
**DeloPay → Business Profile** — and the theme reads from there.

## How the styling works

- **Tailwind v3** + `@tailwindcss/typography` + `@tailwindcss/forms`.
- Colors are CSS variables in **space-separated RGB tuple** form
  (e.g. `--ds-bg: 250 250 247;`) so Tailwind's alpha-modifier syntax
  keeps working: `bg-bg/80`, `ring-accent/20`, etc.
- The Customizer writes `:root { --ds-bg: …; … }` in `<head>` so any
  saved value beats the defaults in `src/input.css`.
- **No CDNs.** A pre-compiled `assets/css/tailwind.css` ships with the
  theme. Run `npm run build` after editing templates to regenerate it.

## Build the CSS

```bash
cd theme
npm install
npm run build       # one-shot, minified
npm run watch       # rebuild on file changes
```

The build scans `*.php` in this folder, `inc/**/*.php`,
`template-parts/**/*.php` and `src/**`. The plugin's classnames
(`delopay-grid`, `delopay-product`, etc.) are safelisted in
`tailwind.config.js` because they're injected by PHP that lives outside
the theme.

## File map

```
theme/
├── style.css                       ← WP theme header (no CSS)
├── functions.php                   ← bootstrap, asset enqueue
├── tailwind.config.js              ← design tokens → CSS vars
├── package.json                    ← Tailwind build scripts
├── src/
│   └── input.css                   ← Tailwind directives + base + components
├── assets/
│   └── css/tailwind.css            ← compiled output (committed)
├── inc/
│   ├── plugin-required.php         ← refuses to run without WP DeloPay
│   ├── customizer.php              ← Customizer panel + sections + controls
│   ├── customizer-output.php       ← inline CSS-var emitter
│   └── template-helpers.php        ← brand name, nav, footer helpers
├── header.php
├── footer.php
├── index.php
├── page.php
├── front-page.php
└── single-delopay_product.php
```
