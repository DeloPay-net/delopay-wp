<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Shortcodes {

	const PAGE_CACHE_GROUP = 'wp_delopay_pages';

	private static $shortcodes = array(
		'delopay_products'      => 'products',
		'delopay_product'       => 'product',
		'delopay_categories'    => 'categories',
		'delopay_category_hero' => 'category_hero',
		'delopay_cart'          => 'cart',
		'delopay_checkout'      => 'checkout',
		'delopay_complete'      => 'complete',
	);

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		foreach ( self::$shortcodes as $tag => $method ) {
			add_shortcode( $tag, array( $this, $method ) );
		}
		add_action( 'save_post', array( __CLASS__, 'invalidate_page_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'invalidate_page_cache' ) );
	}

	public static function invalidate_page_cache() {
		foreach ( self::$shortcodes as $tag => $_ ) {
			wp_cache_delete( $tag, self::PAGE_CACHE_GROUP );
		}
	}

	private function ensure_assets() {
		wp_enqueue_style( 'wp-delopay-frontend' );
		wp_enqueue_script( 'wp-delopay-frontend' );
	}

	private static function format_money( $minor, $currency ) {
		return number_format( ( (int) $minor ) / 100, 2 ) . ' ' . $currency;
	}

	private static function empty_state( $message ) {
		return '<p class="wp-delopay-empty">' . esc_html( $message ) . '</p>';
	}

	private function render( callable $body ) {
		ob_start();
		$body();
		return ob_get_clean();
	}

	public function products( $atts ) {
		$this->ensure_assets();
		$atts = shortcode_atts(
			array(
				'limit'    => 24,
				'columns'  => 3,
				'category' => '',
			),
			$atts,
			'delopay_products'
		);

		$category_trim   = trim( (string) $atts['category'] );
		$category_filter = '' !== $category_trim ? $category_trim : null;
		$products        = WP_Delopay_Products::list_published( (int) $atts['limit'], $category_filter );

		if ( empty( $products ) ) {
			return self::empty_state( __( 'No products yet.', 'wp-delopay' ) );
		}

		return $this->render(
			function () use ( $products, $atts, $category_filter ) {
				$this->render_product_grid( $products, (int) $atts['columns'], $category_filter );
			}
		);
	}

	private function render_product_grid( array $products, $columns, $category_filter ) {
		$grid_class = 'wp-delopay-grid' . ( null !== $category_filter ? ' wp-delopay-grid-category' : '' );
		?>
		<div class="<?php echo esc_attr( $grid_class ); ?>"
			style="--cols: <?php echo (int) $columns; ?>;"
			<?php
			if ( null !== $category_filter ) :
				?>
				data-category="<?php echo esc_attr( $category_filter ); ?>"<?php endif; ?>>
			<?php foreach ( $products as $product ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_card emits pre-escaped HTML.
				echo $this->render_card( $product );
				?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function categories( $atts ) {
		$this->ensure_assets();
		$atts = shortcode_atts(
			array(
				'columns' => 3,
				'limit'   => 24,
			),
			$atts,
			'delopay_categories'
		);

		$categories = WP_Delopay_Categories::list_published();
		if ( empty( $categories ) ) {
			return self::empty_state( __( 'No categories yet.', 'wp-delopay' ) );
		}

		$counts     = WP_Delopay_Categories::product_counts();
		$categories = array_slice( $categories, 0, max( 1, (int) $atts['limit'] ) );

		return $this->render(
			function () use ( $categories, $counts, $atts ) {
				$this->render_categories_grid( $categories, $counts, (int) $atts['columns'] );
			}
		);
	}

	private function render_categories_grid( array $categories, array $counts, $columns ) {
		?>
		<div class="wp-delopay-grid wp-delopay-grid-categories" style="--cols: <?php echo (int) $columns; ?>;">
			<?php
			foreach ( $categories as $cat ) :
				$count   = isset( $counts[ (int) $cat['id'] ] ) ? (int) $counts[ (int) $cat['id'] ] : 0;
				$cat_url = WP_Delopay_Categories::page_url_for_slug( $cat['slug'] );
				$url     = $cat_url ? $cat_url : home_url( '/' );
				?>
				<article class="wp-delopay-category" data-category-slug="<?php echo esc_attr( $cat['slug'] ); ?>">
					<a class="wp-delopay-category-link" href="<?php echo esc_url( $url ); ?>">
						<div class="wp-delopay-category-body">
							<h2 class="wp-delopay-category-name"><?php echo esc_html( $cat['name'] ); ?></h2>
							<?php if ( $cat['excerpt'] ) : ?>
								<p class="wp-delopay-category-excerpt"><?php echo esc_html( $cat['excerpt'] ); ?></p>
							<?php endif; ?>
							<p class="wp-delopay-category-count">
								<?php
								/* translators: %d: number of products in the category */
								echo esc_html( sprintf( _n( '%d product', '%d products', $count, 'wp-delopay' ), $count ) );
								?>
							</p>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function category_hero( $atts ) {
		$this->ensure_assets();
		$atts = shortcode_atts( array( 'category' => '' ), $atts, 'delopay_category_hero' );

		$slug = trim( (string) $atts['category'] );
		if ( '' === $slug && function_exists( 'get_post_field' ) && get_the_ID() ) {
			$slug = (string) get_post_field( 'post_name', get_the_ID() );
		}

		$category = '' !== $slug ? WP_Delopay_Categories::find( $slug, false ) : null;
		$active   = $category && ! empty( $category['hero_active'] );

		return $this->render(
			function () use ( $category, $active ) {
				$this->render_hero( $category, $active );
			}
		);
	}

	private function render_hero( $category, $active ) {
		?>
		<section class="ds-hero <?php echo esc_attr( $active ? 'wp-delopay-hero' : 'ds-hero-empty wp-delopay-hero-empty' ); ?>"
			<?php
			if ( ! $active ) :
				?>
				aria-hidden="true"<?php endif; ?>>
			<?php if ( $active ) : ?>
				<?php if ( '' !== trim( (string) $category['hero_eyebrow'] ) ) : ?>
					<p class="ds-eyebrow"><?php echo esc_html( $category['hero_eyebrow'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $category['hero_title'] ) ) : ?>
					<h1><?php echo esc_html( $category['hero_title'] ); ?></h1>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $category['hero_subtitle'] ) ) : ?>
					<p class="ds-lede"><?php echo wp_kses_post( $category['hero_subtitle'] ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	public function product( $atts ) {
		$this->ensure_assets();
		$atts = shortcode_atts(
			array(
				'id'  => 0,
				'sku' => '',
			),
			$atts,
			'delopay_product'
		);

		$ref = $atts['id'] ? $atts['id'] : $atts['sku'];
		if ( ! $ref ) {
			return self::empty_state( __( 'Missing product id or sku.', 'wp-delopay' ) );
		}

		$product = WP_Delopay_Products::find( $ref );
		if ( ! $product ) {
			return self::empty_state( __( 'Product not found.', 'wp-delopay' ) );
		}

		return '<div class="wp-delopay-grid wp-delopay-grid-single">' . $this->render_card( $product ) . '</div>';
	}

	public function cart( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WP shortcode callback signature.
		unset( $atts );
		$this->ensure_assets();
		$shop_page    = $this->page_with_shortcode( 'delopay_products' );
		$shop_url     = $shop_page ? $shop_page : home_url( '/' );
		$checkout_url = $this->page_with_shortcode( 'delopay_checkout' );

		return $this->render(
			function () use ( $shop_url, $checkout_url ) {
				$this->render_cart_widget( $shop_url, $checkout_url );
			}
		);
	}

	private function render_cart_widget( $shop_url, $checkout_url ) {
		?>
		<header class="ds-page-head">
			<h1><?php esc_html_e( 'Your cart', 'wp-delopay' ); ?></h1>
		</header>
		<div class="wp-delopay-cart"
			data-checkout-url="<?php echo esc_attr( $checkout_url ? $checkout_url : '' ); ?>"
			data-shop-url="<?php echo esc_attr( $shop_url ); ?>">
			<div class="wp-delopay-cart-loading"><?php esc_html_e( 'Loading your cart…', 'wp-delopay' ); ?></div>

			<div class="wp-delopay-cart-empty" hidden>
				<p><?php esc_html_e( 'Your cart is empty.', 'wp-delopay' ); ?></p>
				<a class="wp-delopay-buy-button" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Browse products', 'wp-delopay' ); ?></a>
			</div>

			<div class="wp-delopay-cart-content" hidden>
				<ul class="wp-delopay-cart-items"></ul>

				<div class="wp-delopay-cart-summary">
					<dl>
						<dt><?php esc_html_e( 'Subtotal', 'wp-delopay' ); ?></dt>
						<dd data-field="subtotal">—</dd>
					</dl>
					<?php
					$cart_mode      = WP_Delopay_Settings::cart_checkout_mode();
					$show_embedded  = in_array( $cart_mode, array( 'both', 'embedded' ), true );
					$show_external  = in_array( $cart_mode, array( 'both', 'external' ), true );
					$external_class = 'wp-delopay-buy-button wp-delopay-cart-checkout-external';
					if ( $show_embedded && $show_external ) {
						$external_class .= ' wp-delopay-buy-button-secondary';
					}
					?>
					<div class="wp-delopay-cart-checkout-row">
						<?php if ( $show_embedded ) : ?>
							<button type="button" class="wp-delopay-buy-button wp-delopay-cart-checkout" disabled>
								<?php esc_html_e( 'Proceed to checkout', 'wp-delopay' ); ?>
							</button>
						<?php endif; ?>
						<?php if ( $show_external ) : ?>
							<button type="button" class="<?php echo esc_attr( $external_class ); ?>" disabled>
								<?php echo esc_html( $show_embedded ? __( 'Proceed to external checkout', 'wp-delopay' ) : __( 'Proceed to checkout', 'wp-delopay' ) ); ?>
							</button>
						<?php endif; ?>
					</div>
					<p class="wp-delopay-cart-error" hidden></p>
				</div>
			</div>

			<?php $this->render_cart_row_template(); ?>
		</div>
		<?php
	}

	private function render_cart_row_template() {
		?>
		<template class="wp-delopay-cart-row-template">
			<li class="wp-delopay-cart-row" data-product-id="">
				<div class="wp-delopay-cart-thumb"></div>
				<div class="wp-delopay-cart-meta">
					<h3 data-field="name"></h3>
					<p data-field="unit_price" class="wp-delopay-cart-unit"></p>
				</div>
				<div class="wp-delopay-cart-qty">
					<button type="button" class="wp-delopay-cart-dec" aria-label="<?php esc_attr_e( 'Decrease', 'wp-delopay' ); ?>">−</button>
					<span data-field="quantity">1</span>
					<button type="button" class="wp-delopay-cart-inc" aria-label="<?php esc_attr_e( 'Increase', 'wp-delopay' ); ?>">+</button>
				</div>
				<div class="wp-delopay-cart-line-total" data-field="line_total"></div>
				<button type="button" class="wp-delopay-cart-remove" aria-label="<?php esc_attr_e( 'Remove', 'wp-delopay' ); ?>">×</button>
			</li>
		</template>
		<?php
	}

	public function checkout( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WP shortcode callback signature.
		unset( $atts );
		$this->ensure_assets();

		// Public shortcode entry: $_GET params identify the product to display, no state mutation here.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$product_id = isset( $_GET['product_id'] ) ? sanitize_text_field( wp_unslash( $_GET['product_id'] ) ) : '';
		$quantity   = isset( $_GET['quantity'] ) ? max( 1, (int) $_GET['quantity'] ) : 1;
		$sku        = isset( $_GET['sku'] ) ? sanitize_text_field( wp_unslash( $_GET['sku'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$ref     = $product_id ? $product_id : $sku;
		$product = $ref ? WP_Delopay_Products::find( $ref ) : null;

		return $this->render(
			function () use ( $product, $quantity ) {
				$this->render_checkout_widget( $product, $quantity );
			}
		);
	}

	private function render_checkout_widget( $product, $quantity ) {
		?>
		<header class="ds-page-head">
			<h1><?php esc_html_e( 'Checkout', 'wp-delopay' ); ?></h1>
			<p class="ds-sub"><?php esc_html_e( 'Review your order and pay with DeloPay.', 'wp-delopay' ); ?></p>
		</header>
		<div class="wp-delopay-checkout"
			<?php if ( $product ) : ?>
				data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
				data-quantity="<?php echo esc_attr( $quantity ); ?>"
			<?php endif; ?>>
			<?php
			$product
				? $this->render_checkout_product_summary( $product, $quantity )
				: $this->render_checkout_cart_summary();
			?>

			<aside class="wp-delopay-checkout-pay">
				<div class="wp-delopay-checkout-status">
					<?php esc_html_e( 'Preparing secure payment…', 'wp-delopay' ); ?>
				</div>
				<div class="wp-delopay-checkout-iframe-wrap" hidden>
					<iframe
						class="wp-delopay-checkout-iframe"
						allow="payment *"
						referrerpolicy="origin"
					></iframe>
				</div>
				<div class="wp-delopay-checkout-error" hidden></div>
			</aside>
		</div>
		<?php
	}

	private function render_checkout_product_summary( $product, $quantity ) {
		$total = $product['price_minor'] * $quantity;
		?>
		<section class="wp-delopay-checkout-summary">
			<h2><?php esc_html_e( 'Order summary', 'wp-delopay' ); ?></h2>
			<ul class="wp-delopay-checkout-lines">
				<li>
					<span><?php echo esc_html( $quantity ); ?> × <?php echo esc_html( $product['name'] ); ?></span>
					<strong style="float:right;"><?php echo esc_html( self::format_money( $total, $product['currency'] ) ); ?></strong>
				</li>
			</ul>
			<div class="wp-delopay-checkout-total">
				<span><?php esc_html_e( 'Total', 'wp-delopay' ); ?></span>
				<strong><?php echo esc_html( self::format_money( $total, $product['currency'] ) ); ?></strong>
			</div>
		</section>
		<?php
	}

	private function render_checkout_cart_summary() {
		?>
		<section class="wp-delopay-checkout-summary" data-cart-summary hidden>
			<h2><?php esc_html_e( 'Order summary', 'wp-delopay' ); ?></h2>
			<ul class="wp-delopay-checkout-lines"></ul>
			<div class="wp-delopay-checkout-total">
				<span><?php esc_html_e( 'Total', 'wp-delopay' ); ?></span>
				<strong data-field="total">—</strong>
			</div>
		</section>
		<?php
	}

	public function complete( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WP shortcode callback signature.
		unset( $atts );
		$this->ensure_assets();

		// Public shortcode entry: $_GET params identify the order to display, no state mutation here.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['order_id'] ) ? sanitize_text_field( wp_unslash( $_GET['order_id'] ) ) : '';
		if ( '' === $order_id && isset( $_GET['payment_id'] ) ) {
			$order_id = sanitize_text_field( wp_unslash( $_GET['payment_id'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $this->render(
			function () use ( $order_id ) {
				$this->render_complete_widget( $order_id );
			}
		);
	}

	private function render_complete_widget( $order_id ) {
		?>
		<div class="wp-delopay-complete" data-order-id="<?php echo esc_attr( $order_id ); ?>">
			<?php if ( '' === $order_id ) : ?>
				<div class="wp-delopay-complete-status is-failure">
					<?php esc_html_e( 'No order id in URL.', 'wp-delopay' ); ?>
				</div>
			<?php else : ?>
				<div class="wp-delopay-complete-icon" aria-hidden="true"></div>
				<div class="wp-delopay-complete-status">
					<?php esc_html_e( 'Checking payment status…', 'wp-delopay' ); ?>
				</div>
				<p class="wp-delopay-complete-amount" data-field="amount" hidden></p>
				<p class="wp-delopay-complete-message" data-field="message" hidden></p>
				<dl class="wp-delopay-complete-details" hidden>
					<dt><?php esc_html_e( 'Order', 'wp-delopay' ); ?></dt>
					<dd><code data-field="order_id"></code></dd>
					<dt><?php esc_html_e( 'Payment', 'wp-delopay' ); ?></dt>
					<dd><code data-field="payment_id"></code></dd>
					<dt><?php esc_html_e( 'Status', 'wp-delopay' ); ?></dt>
					<dd data-field="status"></dd>
				</dl>
				<div class="wp-delopay-complete-actions">
					<a class="wp-delopay-complete-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php esc_html_e( 'Back to shop', 'wp-delopay' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_card( $product ) {
		return $this->render(
			function () use ( $product ) {
				?>
			<article class="wp-delopay-product" data-product-id="<?php echo esc_attr( $product['id'] ); ?>">
				<div class="wp-delopay-product-image">
					<?php if ( $product['image_url'] ) : ?>
						<img src="<?php echo esc_url( $product['image_url'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" loading="lazy">
					<?php endif; ?>
				</div>
				<div class="wp-delopay-product-body">
					<h2 class="wp-delopay-product-name"><?php echo esc_html( $product['name'] ); ?></h2>
					<?php if ( $product['excerpt'] ) : ?>
						<p class="wp-delopay-product-excerpt"><?php echo esc_html( $product['excerpt'] ); ?></p>
					<?php endif; ?>
					<div class="wp-delopay-product-row">
						<span class="wp-delopay-product-price">
							<?php echo esc_html( self::format_money( $product['price_minor'], $product['currency'] ) ); ?>
						</span>
						<button
							type="button"
							class="wp-delopay-buy-button wp-delopay-add-to-cart"
							data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
							data-product-name="<?php echo esc_attr( $product['name'] ); ?>">
							<?php esc_html_e( 'Add to cart', 'wp-delopay' ); ?>
						</button>
					</div>
				</div>
			</article>
					<?php
			}
		);
	}

	private function page_with_shortcode( $shortcode ) {
		$cached = wp_cache_get( $shortcode, self::PAGE_CACHE_GROUP );
		if ( false !== $cached ) {
			return '' === $cached ? null : $cached;
		}

		$url = null;
		foreach ( get_pages() as $page ) {
			if ( has_shortcode( $page->post_content, $shortcode ) ) {
				$url = get_permalink( $page );
				break;
			}
		}

		wp_cache_set( $shortcode, null === $url ? '' : $url, self::PAGE_CACHE_GROUP, HOUR_IN_SECONDS );
		return $url;
	}
}
