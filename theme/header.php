<?php
$delopay_shop_tagline   = delopay_shop_customizer_get( 'tagline' );
$delopay_shop_show_text = delopay_shop_customizer_get( 'show_brand_text' );

$delopay_shop_home_url = home_url( '/' );
if ( function_exists( 'delopay_shop_find_page_with_shortcode' ) ) {
	$delopay_shop_cart_page = delopay_shop_find_page_with_shortcode( 'delopay_cart' );
	$delopay_shop_cart_url  = $delopay_shop_cart_page ? $delopay_shop_cart_page : home_url( '/cart/' );
} else {
	$delopay_shop_cart_url = '#';
}

$delopay_shop_request_uri = isset( $_SERVER['REQUEST_URI'] )
	? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
	: '/';
$delopay_shop_current_url = home_url( $delopay_shop_request_uri );
$delopay_shop_is_home     = trailingslashit( $delopay_shop_home_url ) === trailingslashit( $delopay_shop_current_url );
$delopay_shop_is_cart     = trailingslashit( $delopay_shop_cart_url ) === trailingslashit( $delopay_shop_current_url );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'ds-shell' ); ?>>
<?php wp_body_open(); ?>

<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 ds-btn">
	<?php esc_html_e( 'Skip to content', 'delopay-shop' ); ?>
</a>

<?php if ( current_user_can( 'manage_options' ) && ! delopay_shop_plugin_configured() ) : ?>
	<aside class="ds-admin-banner" role="status">
		<div class="ds-admin-banner-inner">
			<span class="ds-admin-banner-mark" aria-hidden="true">
				<svg viewBox="0 0 64 64" focusable="false" aria-hidden="true">
					<path d="M10 12 L28 12 L46 32 L28 52 L10 52 L28 32 Z" fill="currentColor"/>
					<path d="M30 12 L42 12 L54 24 L54 40 L42 52 L30 52 L48 32 Z" fill="currentColor" fill-opacity="0.55"/>
				</svg>
			</span>
			<div class="ds-admin-banner-text">
				<strong><?php esc_html_e( 'DeloPay isn\'t connected yet', 'delopay-shop' ); ?></strong>
				<span><?php esc_html_e( 'Buyers can browse, but checkout will fail until you link this site to your DeloPay account.', 'delopay-shop' ); ?></span>
			</div>
			<a class="ds-admin-banner-cta" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-delopay-settings' ) ); ?>">
				<?php esc_html_e( 'Connect to DeloPay', 'delopay-shop' ); ?>
				<span class="ds-admin-banner-cta-arrow" aria-hidden="true">→</span>
			</a>
		</div>
	</aside>
<?php endif; ?>

<header class="ds-topbar">
	<div class="ds-topbar-inner">
		<a class="ds-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php endif; ?>
			<?php if ( $delopay_shop_show_text || ! has_custom_logo() ) : ?>
				<span><?php echo esc_html( delopay_shop_brand_name() ); ?></span>
			<?php endif; ?>
			<span class="ds-brand-tag">
				<?php echo esc_html( '' !== $delopay_shop_tagline ? $delopay_shop_tagline : __( 'powered by DeloPay', 'delopay-shop' ) ); ?>
			</span>
		</a>

		<nav class="ds-nav" aria-label="<?php esc_attr_e( 'Primary', 'delopay-shop' ); ?>">
			<?php if ( has_nav_menu( 'primary' ) ) : ?>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => '',
						'fallback_cb'    => false,
						'menu_class'     => 'ds-menu',
						'depth'          => 2,
					)
				);
				?>
			<?php else : ?>
				<ul class="ds-menu">
					<li class="<?php echo esc_attr( $delopay_shop_is_home ? 'current-menu-item' : '' ); ?>">
						<a href="<?php echo esc_url( $delopay_shop_home_url ); ?>"><?php esc_html_e( 'Home', 'delopay-shop' ); ?></a>
					</li>
					<li class="<?php echo esc_attr( $delopay_shop_is_cart ? 'current-menu-item' : '' ); ?>">
						<a href="<?php echo esc_url( $delopay_shop_cart_url ); ?>" class="ds-cart-link">
							<?php esc_html_e( 'Cart', 'delopay-shop' ); ?>
							<span class="ds-cart-badge" data-delopay-cart-count hidden></span>
						</a>
					</li>
				</ul>
			<?php endif; ?>
		</nav>
	</div>
</header>

<main id="main" class="flex-1">
	<div class="ds-container">
