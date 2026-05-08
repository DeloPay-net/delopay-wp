<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DELOPAY_SHOP_PAGE_CACHE_GROUP = 'delopay-shop';
const DELOPAY_SHOP_PAGE_CACHE_TTL   = MINUTE_IN_SECONDS;

function delopay_shop_brand_name() {
	if ( class_exists( 'WP_Delopay_Settings' ) ) {
		$name = WP_Delopay_Settings::get( 'business_name' );
		if ( ! empty( $name ) ) {
			return $name;
		}
	}
	return get_bloginfo( 'name' );
}

function delopay_shop_find_page_with_shortcode( $shortcode ) {
	$cache_key = 'delopay_shop_pageshortcode_' . md5( $shortcode );
	$cached    = wp_cache_get( $cache_key, DELOPAY_SHOP_PAGE_CACHE_GROUP );
	if ( false !== $cached ) {
		return '__none__' === $cached ? null : $cached;
	}
	foreach ( get_pages( array( 'sort_column' => 'menu_order' ) ) as $page ) {
		if ( has_shortcode( $page->post_content, $shortcode ) ) {
			$url = get_permalink( $page );
			wp_cache_set( $cache_key, $url, DELOPAY_SHOP_PAGE_CACHE_GROUP, DELOPAY_SHOP_PAGE_CACHE_TTL );
			return $url;
		}
	}
	wp_cache_set( $cache_key, '__none__', DELOPAY_SHOP_PAGE_CACHE_GROUP, DELOPAY_SHOP_PAGE_CACHE_TTL );
	return null;
}

function delopay_shop_plugin_configured() {
	if ( ! class_exists( 'WP_Delopay_Settings' ) ) {
		return false;
	}
	$api_key    = (string) WP_Delopay_Settings::get( 'api_key' );
	$profile_id = (string) WP_Delopay_Settings::get( 'profile_id' );
	return '' !== trim( $api_key ) && '' !== trim( $profile_id );
}

function delopay_shop_footer_copy() {
	$saved = trim( (string) delopay_shop_customizer_get( 'footer_copyright' ) );
	if ( '' !== $saved ) {
		return $saved;
	}

	$out = '© ' . esc_html( gmdate( 'Y' ) ) . ' ' . esc_html( delopay_shop_brand_name() );
	if ( class_exists( 'WP_Delopay_Settings' ) ) {
		$email   = WP_Delopay_Settings::get( 'business_email' );
		$support = WP_Delopay_Settings::get( 'business_support' );
		if ( $email ) {
			$out .= ' · <a href="' . esc_url( 'mailto:' . $email ) . '" class="hover:text-fg">' . esc_html( $email ) . '</a>';
		}
		if ( $support ) {
			$out .= ' · ' . esc_html( $support );
		}
	}
	return $out;
}
