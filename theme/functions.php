<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DELOPAY_SHOP_VERSION', '0.2.0' );
define( 'DELOPAY_SHOP_DIR', get_stylesheet_directory() );
define( 'DELOPAY_SHOP_URL', get_stylesheet_directory_uri() );
define( 'DELOPAY_SHOP_PLUGIN_FILE', 'wp-delopay/wp-delopay.php' );

$delopay_shop_includes = array(
	'plugin-required.php',
	'customizer.php',
	'customizer-output.php',
	'template-helpers.php',
);
foreach ( $delopay_shop_includes as $delopay_shop_file ) {
	require_once DELOPAY_SHOP_DIR . '/inc/' . $delopay_shop_file;
}
unset( $delopay_shop_includes, $delopay_shop_file );

if ( ! function_exists( 'delopay_shop_setup' ) ) {
	function delopay_shop_setup() {
		load_theme_textdomain( 'delopay-shop', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 80,
				'width'       => 240,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );

		register_nav_menus(
			array(
				'primary' => __( 'Primary', 'delopay-shop' ),
				'footer'  => __( 'Footer', 'delopay-shop' ),
			)
		);
	}
	add_action( 'after_setup_theme', 'delopay_shop_setup' );
}

function delopay_shop_assets() {
	$css_path = DELOPAY_SHOP_DIR . '/assets/css/tailwind.css';
	$css_ver  = file_exists( $css_path )
		? DELOPAY_SHOP_VERSION . '.' . filemtime( $css_path )
		: DELOPAY_SHOP_VERSION;

	wp_enqueue_style(
		'delopay-shop-tailwind',
		DELOPAY_SHOP_URL . '/assets/css/tailwind.css',
		array(),
		$css_ver
	);

	$body_font    = delopay_shop_customizer_get( 'body_font_family' );
	$display_font = delopay_shop_customizer_get( 'display_font_family' );
	if ( delopay_shop_uses_bundled_font( $body_font ) || delopay_shop_uses_bundled_font( $display_font ) ) {
		$fonts_path = DELOPAY_SHOP_DIR . '/assets/fonts/fonts.css';
		$fonts_ver  = file_exists( $fonts_path )
			? DELOPAY_SHOP_VERSION . '.' . filemtime( $fonts_path )
			: DELOPAY_SHOP_VERSION;
		wp_enqueue_style(
			'delopay-shop-fonts',
			DELOPAY_SHOP_URL . '/assets/fonts/fonts.css',
			array(),
			$fonts_ver
		);
	}
}
add_action( 'wp_enqueue_scripts', 'delopay_shop_assets', 20 );

function delopay_shop_uses_bundled_font( $family ) {
	return $family && 'system-sans' !== $family && 'system-serif' !== $family;
}

function delopay_shop_drop_plugin_frontend_css() {
	wp_deregister_style( 'wp-delopay-frontend' );
}
add_action( 'wp_enqueue_scripts', 'delopay_shop_drop_plugin_frontend_css', 100 );
