<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Admin_Page_Dashboard extends Delopay_Admin_Page {

	public function slug() {
		return Delopay_Admin::MENU_SLUG;
	}

	public function label() {
		return __( 'Dashboard', 'delopay' );
	}

	public function render() {
		$settings       = Delopay_Settings::all();
		$count          = Delopay_Orders::count();
		$products_total = Delopay_Products::count_all( 'active' );
		$settings_url   = Delopay_Admin_UI::page_url( Delopay_Admin::SLUG_SETTINGS );
		$products_url   = Delopay_Admin_UI::page_url( Delopay_Admin::SLUG_PRODUCTS );
		$orders_url     = Delopay_Admin_UI::page_url( Delopay_Admin::SLUG_ORDERS );
		$branding_url   = Delopay_Settings::get_branding_url();
		?>
		<div class="wrap delopay-wrap">
			<h1><?php esc_html_e( 'DeloPay', 'delopay' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Sell products on this site with the DeloPay hosted checkout. Configure your API key, manage products, and process orders & refunds — all from this admin.', 'delopay' ); ?>
			</p>

			<div class="delopay-cards">
				<div class="delopay-card">
					<h2><?php esc_html_e( 'Setup', 'delopay' ); ?></h2>
					<ul>
						<li><?php echo Delopay_Settings::is_configured() ? '✅' : '⚠️'; ?>
							<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'API key & checkout URL', 'delopay' ); ?></a>
						</li>
						<li><?php echo esc_html( $settings['webhook_secret'] ? '✅' : '⚠️' ); ?>
							<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Webhook secret configured', 'delopay' ); ?></a>
						</li>
						<li><?php echo esc_html( $products_total > 0 ? '✅' : '⚠️' ); ?>
							<a href="<?php echo esc_url( $products_url ); ?>"><?php esc_html_e( 'Add at least one product', 'delopay' ); ?></a>
						</li>
					</ul>
				</div>

				<div class="delopay-card">
					<h2><?php esc_html_e( 'Activity', 'delopay' ); ?></h2>
					<p><strong><?php echo esc_html( $count ); ?></strong> <?php esc_html_e( 'orders processed', 'delopay' ); ?></p>
					<p><strong><?php echo esc_html( $products_total ); ?></strong> <?php esc_html_e( 'published products', 'delopay' ); ?></p>
					<p>
						<a class="button" href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'View orders →', 'delopay' ); ?></a>
						<a class="button" href="<?php echo esc_url( $products_url ); ?>"><?php esc_html_e( 'Manage products →', 'delopay' ); ?></a>
					</p>
				</div>

				<div class="delopay-card">
					<h2><?php esc_html_e( 'Shortcodes', 'delopay' ); ?></h2>
					<p><code>[delopay_products]</code> — <?php esc_html_e( 'product grid', 'delopay' ); ?></p>
					<p><code>[delopay_product id="123"]</code> — <?php esc_html_e( 'single product card', 'delopay' ); ?></p>
					<p><code>[delopay_categories]</code> — <?php esc_html_e( 'index of all active categories', 'delopay' ); ?></p>
					<p><code>[delopay_category_hero category="&lt;slug&gt;"]</code> — <?php esc_html_e( 'eyebrow / title / subtitle for a category page', 'delopay' ); ?></p>
					<p><code>[delopay_cart]</code> — <?php esc_html_e( 'shopper cart with subtotal and checkout button', 'delopay' ); ?></p>
					<p><code>[delopay_checkout]</code> — <?php esc_html_e( 'checkout iframe (reads ?product_id from URL)', 'delopay' ); ?></p>
					<p><code>[delopay_complete]</code> — <?php esc_html_e( 'order-complete page', 'delopay' ); ?></p>
				</div>

				<div class="delopay-card">
					<h2><?php esc_html_e( 'Checkout branding', 'delopay' ); ?></h2>
					<?php if ( $branding_url ) : ?>
						<p><?php esc_html_e( 'Customize the look & feel of the hosted checkout in the DeloPay control center:', 'delopay' ); ?></p>
						<p>
							<a class="button button-primary" href="<?php echo esc_url( $branding_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Open branding settings ↗', 'delopay' ); ?>
							</a>
						</p>
					<?php else : ?>
						<p><?php esc_html_e( 'Add a Project ID and Shop / Profile ID under Settings to enable a one-click link to your shop\'s branding page.', 'delopay' ); ?></p>
						<p>
							<a class="button" href="<?php echo esc_url( $settings_url ); ?>">
								<?php esc_html_e( 'Open Settings →', 'delopay' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
