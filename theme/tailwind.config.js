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
    'delopay-grid',
    'delopay-grid-single',
    'delopay-product',
    'delopay-product-image',
    'delopay-product-body',
    'delopay-product-name',
    'delopay-product-excerpt',
    'delopay-product-price',
    'delopay-product-row',
    'delopay-buy-button',
    'delopay-add-to-cart',
    'delopay-cart',
    'delopay-cart-loading',
    'delopay-cart-empty',
    'delopay-cart-content',
    'delopay-cart-items',
    'delopay-cart-row',
    'delopay-cart-thumb',
    'delopay-cart-meta',
    'delopay-cart-unit',
    'delopay-cart-qty',
    'delopay-cart-inc',
    'delopay-cart-dec',
    'delopay-cart-remove',
    'delopay-cart-line-total',
    'delopay-cart-summary',
    'delopay-cart-checkout',
    'delopay-cart-error',
    'delopay-checkout',
    'delopay-checkout-summary',
    'delopay-checkout-status',
    'delopay-checkout-iframe-wrap',
    'delopay-checkout-iframe',
    'delopay-checkout-error',
    'delopay-checkout-lines',
    'delopay-checkout-pay',
    'delopay-checkout-total',
    'ds-page-head',
    'ds-sub',
    'delopay-complete',
    'delopay-complete-status',
    'delopay-complete-details',
    'delopay-empty',
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
