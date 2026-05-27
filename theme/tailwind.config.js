/**
 * Tailwind config for the DeloPay Shop theme.
 *
 * Design tokens are wired to CSS variables so the WordPress Customizer
 * (Appearance → Customize → DeloPay Shop) can override them at runtime
 * without a rebuild. Defaults live in src/input.css (`:root { … }`),
 * the Customizer overrides those defaults inline in <head>.
 */

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './inc/**/*.php',
    './src/**/*.{js,css}',
  ],
  // Block plugin classes from the auto-purge: the WP DeloPay plugin
  // injects markup with these classes server-side, so they never appear
  // in the theme's PHP files but still need styles.
  safelist: [
    'wp-delopay-grid',
    'wp-delopay-grid-single',
    'wp-delopay-product',
    'wp-delopay-product-image',
    'wp-delopay-product-body',
    'wp-delopay-product-name',
    'wp-delopay-product-excerpt',
    'wp-delopay-product-price',
    'wp-delopay-product-row',
    'wp-delopay-buy-button',
    'wp-delopay-add-to-cart',
    'wp-delopay-cart',
    'wp-delopay-cart-loading',
    'wp-delopay-cart-empty',
    'wp-delopay-cart-content',
    'wp-delopay-cart-items',
    'wp-delopay-cart-row',
    'wp-delopay-cart-thumb',
    'wp-delopay-cart-meta',
    'wp-delopay-cart-unit',
    'wp-delopay-cart-qty',
    'wp-delopay-cart-inc',
    'wp-delopay-cart-dec',
    'wp-delopay-cart-remove',
    'wp-delopay-cart-line-total',
    'wp-delopay-cart-summary',
    'wp-delopay-cart-checkout',
    'wp-delopay-cart-error',
    'wp-delopay-checkout',
    'wp-delopay-checkout-summary',
    'wp-delopay-checkout-status',
    'wp-delopay-checkout-iframe-wrap',
    'wp-delopay-checkout-iframe',
    'wp-delopay-checkout-error',
    'wp-delopay-checkout-lines',
    'wp-delopay-checkout-pay',
    'wp-delopay-checkout-total',
    'ds-page-head',
    'ds-sub',
    'wp-delopay-complete',
    'wp-delopay-complete-status',
    'wp-delopay-complete-details',
    'wp-delopay-empty',
    'is-ready',
    'is-pending',
    'is-added',
    'is-empty',
    'is-success',
    'is-failure',
  ],
  theme: {
    extend: {
      // Colors are wired through CSS variables stored as space-separated
      // RGB tuples (e.g. "250 250 247"). That lets Tailwind's alpha
      // modifier syntax keep working — `bg-bg/80`, `ring-accent/20`, etc.
      colors: {
        bg:             'rgb(var(--ds-bg) / <alpha-value>)',
        surface:        'rgb(var(--ds-surface) / <alpha-value>)',
        'surface-alt':  'rgb(var(--ds-surface-alt) / <alpha-value>)',
        fg:             'rgb(var(--ds-fg) / <alpha-value>)',
        muted:          'rgb(var(--ds-muted) / <alpha-value>)',
        line:           'rgb(var(--ds-line) / <alpha-value>)',
        'line-strong':  'rgb(var(--ds-line-strong) / <alpha-value>)',
        accent:         'rgb(var(--ds-accent) / <alpha-value>)',
        'accent-fg':    'rgb(var(--ds-accent-fg) / <alpha-value>)',
        success:        'rgb(var(--ds-success) / <alpha-value>)',
        danger:         'rgb(var(--ds-danger) / <alpha-value>)',
      },
      fontFamily: {
        sans:    ['var(--ds-font-body)', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'sans-serif'],
        display: ['var(--ds-font-display)', 'ui-serif', 'Georgia', 'Cambria', 'Times New Roman', 'serif'],
      },
      borderRadius: {
        token: 'var(--ds-radius)',
      },
      maxWidth: {
        shell: 'var(--ds-max-w)',
      },
      spacing: {
        gutter: 'var(--ds-pad)',
      },
      boxShadow: {
        card:        '0 1px 2px rgba(0,0,0,0.04), 0 6px 24px rgba(0,0,0,0.04)',
        'card-hover':'0 1px 2px rgba(0,0,0,0.05), 0 12px 36px rgba(0,0,0,0.07)',
      },
      typography: ({ theme }) => ({
        DEFAULT: {
          css: {
            '--tw-prose-body':     'rgb(var(--ds-fg))',
            '--tw-prose-headings': 'rgb(var(--ds-fg))',
            '--tw-prose-lead':     'rgb(var(--ds-muted))',
            '--tw-prose-links':    'rgb(var(--ds-accent))',
            '--tw-prose-bold':     'rgb(var(--ds-fg))',
            '--tw-prose-counters': 'rgb(var(--ds-muted))',
            '--tw-prose-bullets':  'rgb(var(--ds-line))',
            '--tw-prose-hr':       'rgb(var(--ds-line))',
            '--tw-prose-quotes':   'rgb(var(--ds-fg))',
            '--tw-prose-quote-borders': 'rgb(var(--ds-line))',
            '--tw-prose-captions': 'rgb(var(--ds-muted))',
            '--tw-prose-code':     'rgb(var(--ds-fg))',
            '--tw-prose-pre-code': 'rgb(var(--ds-fg))',
            '--tw-prose-pre-bg':   'rgb(var(--ds-surface))',
            'h1, h2, h3, h4': { fontFamily: 'var(--ds-font-display)' },
          },
        },
      }),
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
};
