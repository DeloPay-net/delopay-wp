<?php
/**
 * Soft dependency on the DeloPay plugin.
 *
 * The theme works without the plugin (templates render, customizer works,
 * shortcodes degrade to passthrough). When the plugin is missing we surface
 * an admin notice and ask the user to install/activate it. We do not switch
 * themes, auto-activate plugins, or block the front-end — all of which are
 * Theme Review violations.
 *
 * The cleaner declaration of this dependency is the `Requires Plugins`
 * header in style.css (WP 6.5+); this file only exists to give a clear
 * call-to-action on older sites and to expose `delopay_shop_plugin_active()`
 * for templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function delopay_shop_plugin_active() {
	return class_exists( 'WP_Delopay_Plugin' );
}

function delopay_shop_admin_notices() {
	if ( delopay_shop_plugin_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$plugin_file  = DELOPAY_SHOP_PLUGIN_FILE;
	$activate_url = '';
	if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
		$activate_url = wp_nonce_url(
			admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $plugin_file ) ),
			'activate-plugin_' . $plugin_file
		);
	}
	$msg = '<strong>' . esc_html__( 'DeloPay Shop needs the DeloPay plugin.', 'delopay-shop' ) . '</strong> ';
	if ( $activate_url ) {
		$msg .= sprintf(
			/* translators: %s = activation link */
			esc_html__( 'Plugin is installed but inactive. %s', 'delopay-shop' ),
			'<a href="' . esc_url( $activate_url ) . '" class="button button-primary" style="margin-left:8px;">'
				. esc_html__( 'Activate now', 'delopay-shop' )
				. '</a>'
		);
	} else {
		$msg .= esc_html__( 'Install and activate the DeloPay plugin to use this theme.', 'delopay-shop' );
	}
	echo '<div class="notice notice-warning"><p>' . wp_kses_post( $msg ) . '</p></div>';
}
add_action( 'admin_notices', 'delopay_shop_admin_notices' );
