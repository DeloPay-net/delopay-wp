<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DELOPAY_SHOP_TOKEN_MAP = array(
	'bg'          => array( 'color_bg', 'dark_color_bg' ),
	'surface'     => array( 'color_surface', 'dark_color_surface' ),
	'surface-alt' => array( 'color_surface_alt', 'dark_color_surface_alt' ),
	'fg'          => array( 'color_fg', 'dark_color_fg' ),
	'muted'       => array( 'color_muted', 'dark_color_muted' ),
	'line'        => array( 'color_line', 'dark_color_line' ),
	'line-strong' => array( 'color_line_strong', 'dark_color_line_strong' ),
	'accent'      => array( 'color_accent', 'dark_color_accent' ),
	'accent-fg'   => array( 'color_accent_fg', 'dark_color_accent_fg' ),
	'success'     => array( 'color_success', 'dark_color_success' ),
	'danger'      => array( 'color_danger', 'dark_color_danger' ),
);

const DELOPAY_SHOP_PALETTE_LIGHT = 0;
const DELOPAY_SHOP_PALETTE_DARK  = 1;

function delopay_shop_build_css_vars() {
	$schema = delopay_shop_customizer_schema();
	$mode   = WP_Delopay_Settings::color_mode();

	$light_decls = '';
	$dark_decls  = '';

	if ( 'light' === $mode ) {
		$light_decls = delopay_shop_render_palette( $schema, DELOPAY_SHOP_PALETTE_LIGHT, true );
	} elseif ( 'dark' === $mode ) {
		$dark_decls = delopay_shop_render_palette( $schema, DELOPAY_SHOP_PALETTE_DARK, true );
	} else {
		$light_decls = delopay_shop_render_palette( $schema, DELOPAY_SHOP_PALETTE_LIGHT, false );
		$dark_decls  = delopay_shop_render_palette( $schema, DELOPAY_SHOP_PALETTE_DARK, false );
	}

	$shared = delopay_shop_render_shared_tokens( $schema );

	$css = '';
	if ( '' !== $light_decls || '' !== $shared ) {
		$css .= ':root{' . $light_decls . $shared . '}';
	}
	if ( '' !== $dark_decls ) {
		$css .= 'dark' === $mode
			? ':root{' . $dark_decls . '}'
			: '@media(prefers-color-scheme:dark){:root{' . $dark_decls . '}}';
	}

	return $css;
}

function delopay_shop_render_palette( $schema, $idx, $always_emit ) {
	$out = '';
	foreach ( DELOPAY_SHOP_TOKEN_MAP as $name => $pair ) {
		$setting = $pair[ $idx ];
		$value   = delopay_shop_customizer_get( $setting );
		$default = $schema[ $setting ]['default'] ?? null;
		if ( ! $always_emit && $value === $default ) {
			continue;
		}
		$rgb = delopay_shop_hex_to_rgb_tuple( $value );
		if ( $rgb ) {
			$out .= '--ds-' . $name . ':' . $rgb . ';';
		}
	}
	return $out;
}

function delopay_shop_render_shared_tokens( $schema ) {
	$tokens = array(
		array(
			'max_width',
			'--ds-max-w',
			static function ( $v ) {
								return (int) $v . 'px'; },
		),
		array(
			'border_radius',
			'--ds-radius',
			static function ( $v ) {
								return (int) $v . 'px'; },
		),
		array( 'display_font_family', '--ds-font-display', 'delopay_shop_format_font_family' ),
		array( 'body_font_family', '--ds-font-body', 'delopay_shop_format_font_family' ),
	);

	$out = '';
	foreach ( $tokens as [ $key, $css_name, $formatter ] ) {
		$value   = delopay_shop_customizer_get( $key );
		$default = $schema[ $key ]['default'] ?? null;
		if ( delopay_shop_token_matches_default( $value, $default, $key ) ) {
			continue;
		}
		$out .= $css_name . ':' . $formatter( $value ) . ';';
	}
	return $out;
}

function delopay_shop_token_matches_default( $value, $default_value, $key ) {
	$numeric_keys = array( 'max_width', 'border_radius' );
	if ( in_array( $key, $numeric_keys, true ) ) {
		return (int) $value === (int) $default_value;
	}
	return $value === $default_value;
}

function delopay_shop_attach_css_vars() {
	$css = delopay_shop_build_css_vars();
	if ( '' === $css ) {
		return;
	}
	wp_add_inline_style( 'delopay-shop-tailwind', $css );
}
add_action( 'wp_enqueue_scripts', 'delopay_shop_attach_css_vars', 30 );

function delopay_shop_hex_to_rgb_tuple( $hex ) {
	if ( ! is_string( $hex ) ) {
		return '';
	}
	$hex = ltrim( trim( $hex ), '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return '';
	}
	return (int) hexdec( substr( $hex, 0, 2 ) ) . ' '
		. (int) hexdec( substr( $hex, 2, 2 ) ) . ' '
		. (int) hexdec( substr( $hex, 4, 2 ) );
}

function delopay_shop_format_font_family( $family ) {
	if ( empty( $family ) ) {
		return 'inherit';
	}
	if ( 'system-sans' === $family ) {
		return 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
	}
	if ( 'system-serif' === $family ) {
		return 'ui-serif, Georgia, "Times New Roman", serif';
	}
	if ( preg_match( '/\s/', $family ) ) {
		return '"' . $family . '"';
	}
	return $family;
}

function delopay_shop_body_class( $classes ) {
	if ( delopay_shop_customizer_get( 'header_sticky' ) ) {
		$classes[] = 'theme-sticky-header';
	}
	$classes[] = 'theme-delopay-shop';
	return $classes;
}
add_filter( 'body_class', 'delopay_shop_body_class' );
