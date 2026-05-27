<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces a "View details" modal on the Plugins screen with the long
 * description, installation steps and changelog — so users can read the
 * full readme without WordPress.org acting as the data source.
 *
 * Once the plugin is published on WordPress.org, this filter still wins
 * locally (which keeps the description in sync with the installed
 * version), but the WordPress.org listing page is unaffected.
 */
class WP_Delopay_Plugin_Details {

	const SLUG = 'wp-delopay';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'plugin_row_meta', array( $this, 'inject_details_link' ), 10, 2 );
		add_filter( 'plugins_api', array( $this, 'provide_details' ), 10, 3 );
	}

	public function inject_details_link( $meta, $plugin_file ) {
		if ( plugin_basename( WP_DELOPAY_FILE ) !== $plugin_file ) {
			return $meta;
		}

		$url = self_admin_url(
			'plugin-install.php?tab=plugin-information&plugin=' . self::SLUG
			. '&TB_iframe=true&width=772&height=550'
		);

		$meta[] = sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
			esc_url( $url ),
			/* translators: %s = plugin name. The wrapped string is reused from WP core so it gets translated automatically. */
			esc_attr( sprintf( __( 'More information about %s', 'default' ), 'DeloPay' ) ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- intentional reuse of WP core string.
			esc_html__( 'View details', 'default' ) // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- intentional reuse of WP core string.
		);
		return $meta;
	}

	public function provide_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		return (object) array(
			'name'              => 'DeloPay',
			'slug'              => self::SLUG,
			'version'           => WP_DELOPAY_VERSION,
			'author'            => '<a href="https://delopay.net" target="_blank" rel="noopener">DeloPay</a>',
			'author_profile'    => 'https://delopay.net',
			'homepage'          => 'https://delopay.net',
			'requires'          => '6.0',
			'tested'            => '6.9',
			'requires_php'      => '7.4',
			'last_updated'      => gmdate( 'Y-m-d' ),
			'short_description' => __( "Take online payments through DeloPay's hosted checkout. Manage products, orders and refunds from one admin panel without handling card data.", 'wp-delopay' ),
			'sections'          => array(
				'description'       => $this->section_description(),
				'installation'      => $this->section_installation(),
				'shortcodes'        => $this->section_shortcodes(),
				'external_services' => $this->section_external_services(),
				'changelog'         => $this->section_changelog(),
			),
			'banners'           => array(),
		);
	}

	private function section_description() {
		ob_start();
		?>
		<p><?php esc_html_e( 'DeloPay turns your site into a merchant storefront. Shoppers complete payment inside an iframe served by DeloPay, so card data never reaches your server.', 'wp-delopay' ); ?></p>

		<h3><?php esc_html_e( 'Why DeloPay', 'wp-delopay' ); ?></h3>
		<p><?php esc_html_e( 'DeloPay is a composable payments orchestrator that connects to multiple payment providers — including Stripe, Klarna, PaySePro and NOWPayments — through a single API. Once your site is paired with your DeloPay merchant account, you can:', 'wp-delopay' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'Switch payment methods on and off from the DeloPay control center without redeploying.', 'wp-delopay' ); ?></li>
			<li><?php esc_html_e( 'Keep card data off your site — payment details are entered on the hosted checkout and forwarded directly to your chosen connector. Your server and database never see a card number.', 'wp-delopay' ); ?></li>
			<li><?php esc_html_e( 'Get unified reporting on costs, refunds and reconciliation across every connector.', 'wp-delopay' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'What this plugin gives you', 'wp-delopay' ); ?></h3>
		<ul>
			<li><strong><?php esc_html_e( 'Product catalog', 'wp-delopay' ); ?></strong> — <?php esc_html_e( 'manage products and categories from the admin. Each category gets its own page automatically with a configurable hero (eyebrow, title, subtitle).', 'wp-delopay' ); ?></li>
			<li><strong><?php esc_html_e( 'Hosted checkout', 'wp-delopay' ); ?></strong> — <?php echo wp_kses_post( __( 'drop <code>[delopay_checkout]</code> on any page; the buyer pays inside a DeloPay-served iframe.', 'wp-delopay' ) ); ?></li>
			<li><strong><?php esc_html_e( 'Server-rendered cart', 'wp-delopay' ); ?></strong> — <?php echo wp_kses_post( __( "<code>[delopay_cart]</code> totals up against the trusted catalog so prices can't be tampered with on the client.", 'wp-delopay' ) ); ?></li>
			<li><strong><?php esc_html_e( 'Storefront shortcodes', 'wp-delopay' ); ?></strong> — <?php echo wp_kses_post( __( '<code>[delopay_products]</code>, <code>[delopay_product]</code>, <code>[delopay_categories]</code>, <code>[delopay_category_hero]</code>, <code>[delopay_complete]</code>.', 'wp-delopay' ) ); ?></li>
			<li><strong><?php esc_html_e( 'Signed webhooks', 'wp-delopay' ); ?></strong> — <?php esc_html_e( 'every DeloPay webhook is verified with HMAC-SHA512 in constant time before any state mutation.', 'wp-delopay' ); ?></li>
			<li><strong><?php esc_html_e( 'Refunds in the admin', 'wp-delopay' ); ?></strong> — <?php echo wp_kses_post( __( 'full and partial refunds in <code>DeloPay → Orders</code>, pushed to the connector and reconciled by a 15-minute background cron.', 'wp-delopay' ) ); ?></li>
			<li><strong><?php esc_html_e( 'One-click pairing', 'wp-delopay' ); ?></strong> — <?php echo wp_kses_post( __( '<code>Connect to DeloPay</code> runs an OAuth-style handshake from the Settings screen; the API key is provisioned automatically and stored on the server (never exposed to the browser).', 'wp-delopay' ) ); ?></li>
			<li><strong><?php esc_html_e( 'Multi-currency, minor units', 'wp-delopay' ); ?></strong> — <?php esc_html_e( "all prices stored as integer minor units, formatted server-side in the buyer's locale.", 'wp-delopay' ); ?></li>
			<li><strong><?php esc_html_e( 'Standalone admin pages', 'wp-delopay' ); ?></strong> — <?php echo wp_kses_post( __( 'Dashboard, Products, Categories, Orders, Branding, Business profile, Settings. WP-CLI compatible (<code>wp option get wp_delopay_settings</code>).', 'wp-delopay' ) ); ?></li>
			<li><strong><?php esc_html_e( 'Pairs with the DeloPay Shop theme', 'wp-delopay' ); ?></strong> <?php esc_html_e( 'for a turn-key storefront, or use any theme via the shortcodes above.', 'wp-delopay' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'How the integration works', 'wp-delopay' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'The plugin holds your DeloPay API key on the server only — never echoed to the browser or stored unhashed where it could be exfiltrated.', 'wp-delopay' ); ?></li>
			<li><?php echo wp_kses_post( __( 'On checkout, the plugin creates an order on the DeloPay backend and renders an iframe pointing at the hosted checkout (<code>checkout.delopay.net</code>). The shopper completes payment there.', 'wp-delopay' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'DeloPay sends a signed webhook back to <code>/wp-json/delopay/v1/webhook</code>; the plugin verifies the HMAC and updates the order state in the database.', 'wp-delopay' ) ); ?></li>
			<li><?php echo wp_kses_post( __( "Refunds initiated from the admin are forwarded to DeloPay's <code>/refunds</code> API and reconciled by a recurring background job.", 'wp-delopay' ) ); ?></li>
		</ol>

		<h3><?php esc_html_e( 'Requirements', 'wp-delopay' ); ?></h3>
		<ul>
			<li><?php echo wp_kses_post( __( 'A DeloPay merchant account — sign up at <a href="https://delopay.net" target="_blank" rel="noopener">delopay.net</a>.', 'wp-delopay' ) ); ?></li>
			<li><?php esc_html_e( 'PHP 7.4+ (the host version floor is set in the plugin header).', 'wp-delopay' ); ?></li>
			<li><?php esc_html_e( 'HTTPS on the front-end so the hosted checkout iframe can embed.', 'wp-delopay' ); ?></li>
		</ul>
		<?php
		return ob_get_clean();
	}

	private function section_installation() {
		ob_start();
		?>
		<ol>
			<li><?php echo wp_kses_post( __( 'Upload the <code>wp-delopay</code> folder to <code>/wp-content/plugins/</code> (or install via the Plugins screen).', 'wp-delopay' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Activate the plugin — a default <strong>Home</strong> category and matching page are created automatically.', 'wp-delopay' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Go to <strong>DeloPay → Settings</strong>, click <strong>Connect to DeloPay</strong>, and complete the handshake.', 'wp-delopay' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Add products under <strong>DeloPay → Products</strong>. New products land in the Home category by default.', 'wp-delopay' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Optional: add more categories under <strong>DeloPay → Categories</strong> — each one publishes its own page.', 'wp-delopay' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Add a webhook endpoint in your DeloPay dashboard pointing at <code>https://your-site.tld/wp-json/delopay/v1/webhook</code>.', 'wp-delopay' ) ); ?></li>
		</ol>
		<?php
		return ob_get_clean();
	}

	private function section_shortcodes() {
		ob_start();
		?>
		<ul>
			<li><code>[delopay_products limit="24" columns="3" category="home"]</code> — <?php esc_html_e( 'product grid (filter optional).', 'wp-delopay' ); ?></li>
			<li><code>[delopay_product id="123"]</code> — <?php esc_html_e( 'single product card.', 'wp-delopay' ); ?></li>
			<li><code>[delopay_categories]</code> — <?php esc_html_e( 'index of all active categories.', 'wp-delopay' ); ?></li>
			<li><code>[delopay_category_hero category="&lt;slug&gt;"]</code> — <?php esc_html_e( 'eyebrow / title / subtitle hero for a category page.', 'wp-delopay' ); ?></li>
			<li><code>[delopay_cart]</code> — <?php esc_html_e( "shopper's cart with line items, subtotal and a checkout button.", 'wp-delopay' ); ?></li>
			<li><code>[delopay_checkout]</code> — <?php esc_html_e( 'order creation + checkout iframe.', 'wp-delopay' ); ?></li>
			<li><code>[delopay_complete]</code> — <?php esc_html_e( 'post-payment status page.', 'wp-delopay' ); ?></li>
		</ul>
		<?php
		return ob_get_clean();
	}

	private function section_external_services() {
		ob_start();
		?>
		<p><?php echo wp_kses_post( __( 'This plugin connects to the DeloPay payment platform (<a href="https://delopay.net" target="_blank" rel="noopener">delopay.net</a>) so the site can accept payments and stay in sync with the merchant catalog. Specifically:', 'wp-delopay' ) ); ?></p>
		<ul>
			<li><?php echo wp_kses_post( __( 'Authenticated API requests to <code>https://api.delopay.net</code> (and <code>https://sandbox-api.delopay.net</code> while testing) for products, categories, orders, refunds and the connect handshake. Each request includes the merchant API key (server-side only) and the request payload.', 'wp-delopay' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'A hosted checkout iframe served from <code>https://checkout.delopay.net</code>. The shopper enters payment details on DeloPay; nothing sensitive touches this site.', 'wp-delopay' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Webhook callbacks delivered to <code>/wp-json/delopay/v1/webhook</code>, verified with HMAC-SHA512 against the configured webhook secret.', 'wp-delopay' ) ); ?></li>
		</ul>
		<p><?php echo wp_kses_post( __( 'DeloPay <a href="https://delopay.net/terms" target="_blank" rel="noopener">terms of service</a> and <a href="https://delopay.net/privacy" target="_blank" rel="noopener">privacy policy</a>.', 'wp-delopay' ) ); ?></p>
		<?php
		return ob_get_clean();
	}

	private function section_changelog() {
		ob_start();
		?>
		<h4>1.0.0</h4>
		<ul>
			<li><?php esc_html_e( 'Initial release.', 'wp-delopay' ); ?></li>
		</ul>
		<?php
		return ob_get_clean();
	}
}
