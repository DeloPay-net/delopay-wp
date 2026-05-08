<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class WP_Delopay_Admin_Page {

	abstract public function slug();

	abstract public function label();

	abstract public function render();

	/*
	 * Read-only $_GET helpers for admin-list filters / pagination / "which form to render" routing.
	 * No state changes happen on read; admin pages are capability-gated at registration so a nonce
	 * adds no real protection here.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Recommended
	 */
	protected function get( $key, $default_value = '' ) {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default_value;
	}

	protected function get_key( $key, $default_value = '' ) {
		return isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : $default_value;
	}

	protected function get_int( $key ) {
		return isset( $_GET[ $key ] ) ? (int) $_GET[ $key ] : 0;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}
