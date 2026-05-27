<?php
/**
 * Plugin Name:       DeloPay
 * Plugin URI:        https://delopay.net/docs/
 * Description:       Take online payments through DeloPay's hosted checkout. Manage products, orders and refunds from one admin panel without handling card data.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DeloPay
 * Author URI:        https://delopay.net
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       delopay
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DELOPAY_VERSION', '1.0.0' );
define( 'DELOPAY_FILE', __FILE__ );
define( 'DELOPAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'DELOPAY_URL', plugin_dir_url( __FILE__ ) );

$delopay_includes = array(
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
	'admin-pages/class-delopay-admin-page-disputes.php',
	'admin-pages/class-delopay-admin-page-products.php',
	'admin-pages/class-delopay-admin-page-categories.php',
	'class-delopay-admin.php',
	'class-delopay-shortcodes.php',
	'class-delopay-plugin.php',
);
foreach ( $delopay_includes as $delopay_include_file ) {
	require_once DELOPAY_DIR . 'includes/' . $delopay_include_file;
}
unset( $delopay_includes, $delopay_include_file );

register_activation_hook( __FILE__, array( 'Delopay_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Delopay_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Delopay_Plugin', 'instance' ) );
