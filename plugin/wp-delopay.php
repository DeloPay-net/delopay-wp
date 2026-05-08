<?php
/**
 * Plugin Name:       DeloPay
 * Plugin URI:        https://delopay.net
 * Description:       Take online payments through DeloPay's hosted checkout. Manage products, orders and refunds from one admin panel without handling card data.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DeloPay
 * Author URI:        https://delopay.net
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wp-delopay
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_DELOPAY_VERSION', '1.0.0' );
define( 'WP_DELOPAY_FILE', __FILE__ );
define( 'WP_DELOPAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_DELOPAY_URL', plugin_dir_url( __FILE__ ) );

$wp_delopay_includes = array(
	'class-delopay-log.php',
	'class-delopay-settings.php',
	'class-delopay-client.php',
	'class-delopay-orders.php',
	'class-delopay-categories.php',
	'class-delopay-products.php',
	'class-delopay-rest.php',
	'class-delopay-webhook.php',
	'class-delopay-connect.php',
	'class-delopay-admin-ui.php',
	'class-delopay-admin-handlers.php',
	'class-delopay-plugin-details.php',
	'admin-pages/class-delopay-admin-page.php',
	'admin-pages/class-delopay-admin-page-dashboard.php',
	'admin-pages/class-delopay-admin-page-settings.php',
	'admin-pages/class-delopay-admin-page-business.php',
	'admin-pages/class-delopay-admin-page-branding.php',
	'admin-pages/class-delopay-admin-page-orders.php',
	'admin-pages/class-delopay-admin-page-products.php',
	'admin-pages/class-delopay-admin-page-categories.php',
	'class-delopay-admin.php',
	'class-delopay-shortcodes.php',
	'class-delopay-plugin.php',
);
foreach ( $wp_delopay_includes as $wp_delopay_include_file ) {
	require_once WP_DELOPAY_DIR . 'includes/' . $wp_delopay_include_file;
}
unset( $wp_delopay_includes, $wp_delopay_include_file );

register_activation_hook( __FILE__, array( 'WP_Delopay_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_Delopay_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WP_Delopay_Plugin', 'instance' ) );
