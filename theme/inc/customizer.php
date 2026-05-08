<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DELOPAY_SHOP_PANEL          = 'delopay_shop_panel';
const DELOPAY_SHOP_SETTING_PREFIX = 'delopay_shop_';

function delopay_shop_customizer_sections() {
	return array(
		'delopay_shop_brand'       => __( 'Brand', 'delopay-shop' ),
		'delopay_shop_colors'      => __( 'Colors (light)', 'delopay-shop' ),
		'delopay_shop_colors_dark' => __( 'Colors (dark)', 'delopay-shop' ),
		'delopay_shop_type'        => __( 'Typography', 'delopay-shop' ),
		'delopay_shop_layout'      => __( 'Layout', 'delopay-shop' ),
		'delopay_shop_grid'        => __( 'Product grid', 'delopay-shop' ),
		'delopay_shop_footer'      => __( 'Footer', 'delopay-shop' ),
	);
}

function delopay_shop_customizer_schema() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = array_merge(
		delopay_shop_schema_brand(),
		delopay_shop_schema_colors_light(),
		delopay_shop_schema_colors_dark(),
		delopay_shop_schema_typography(),
		delopay_shop_schema_layout(),
		delopay_shop_schema_grid(),
		delopay_shop_schema_footer()
	);
	return $cache;
}

function delopay_shop_schema_colors_light() {
	return delopay_shop_build_color_fields(
		'delopay_shop_colors',
		'',
		array(
			array( 'color_bg', __( 'Background', 'delopay-shop' ), '#f5f4ea' ),
			array( 'color_surface', __( 'Surface (cards)', 'delopay-shop' ), '#ffffff' ),
			array( 'color_surface_alt', __( 'Surface alt (pills, hovers)', 'delopay-shop' ), '#ebe9da' ),
			array( 'color_fg', __( 'Text', 'delopay-shop' ), '#1c2014' ),
			array( 'color_muted', __( 'Muted text', 'delopay-shop' ), '#6b7060' ),
			array( 'color_line', __( 'Border', 'delopay-shop' ), '#ddd9c5' ),
			array( 'color_line_strong', __( 'Border (strong, hover)', 'delopay-shop' ), '#c2bfa9' ),
			array( 'color_accent', __( 'Accent', 'delopay-shop' ), '#5b7a2a' ),
			array( 'color_accent_fg', __( 'Text on accent', 'delopay-shop' ), '#ffffff' ),
			array( 'color_success', __( 'Success', 'delopay-shop' ), '#15803d' ),
			array( 'color_danger', __( 'Danger', 'delopay-shop' ), '#b91c1c' ),
		)
	);
}

function delopay_shop_schema_colors_dark() {
	return delopay_shop_build_color_fields(
		'delopay_shop_colors_dark',
		'dark_',
		array(
			array( 'color_bg', __( 'Background (dark)', 'delopay-shop' ), '#0f120a' ),
			array( 'color_surface', __( 'Surface (dark)', 'delopay-shop' ), '#181b11' ),
			array( 'color_surface_alt', __( 'Surface alt (dark)', 'delopay-shop' ), '#1f2316' ),
			array( 'color_fg', __( 'Text (dark)', 'delopay-shop' ), '#ecebd7' ),
			array( 'color_muted', __( 'Muted text (dark)', 'delopay-shop' ), '#93957d' ),
			array( 'color_line', __( 'Border (dark)', 'delopay-shop' ), '#2a2e1f' ),
			array( 'color_line_strong', __( 'Border strong (dark)', 'delopay-shop' ), '#3a3f2c' ),
			array( 'color_accent', __( 'Accent (dark)', 'delopay-shop' ), '#a8c66c' ),
			array( 'color_accent_fg', __( 'Text on accent (dark)', 'delopay-shop' ), '#14180c' ),
			array( 'color_success', __( 'Success (dark)', 'delopay-shop' ), '#4ade80' ),
			array( 'color_danger', __( 'Danger (dark)', 'delopay-shop' ), '#f87171' ),
		)
	);
}

function delopay_shop_build_color_fields( $section, $key_prefix, $fields ) {
	$out = array();
	foreach ( $fields as [ $key, $label, $default ] ) {
		$out[ $key_prefix . $key ] = array(
			'section' => $section,
			'type'    => 'color',
			'label'   => $label,
			'default' => $default,
		);
	}
	return $out;
}

function delopay_shop_schema_brand() {
	return array(
		'tagline'         => array(
			'section' => 'delopay_shop_brand',
			'type'    => 'text',
			'label'   => __( 'Tagline', 'delopay-shop' ),
			'default' => '',
		),
		'show_brand_text' => array(
			'section' => 'delopay_shop_brand',
			'type'    => 'checkbox',
			'label'   => __( 'Show brand name as text (alongside / instead of logo)', 'delopay-shop' ),
			'default' => true,
		),
	);
}

function delopay_shop_schema_typography() {
	return array(
		'display_font_family' => array(
			'section' => 'delopay_shop_type',
			'type'    => 'select',
			'label'   => __( 'Display font (headings)', 'delopay-shop' ),
			'default' => 'system-sans',
			'choices' => delopay_shop_font_choices( 'display' ),
		),
		'body_font_family'    => array(
			'section' => 'delopay_shop_type',
			'type'    => 'select',
			'label'   => __( 'Body font', 'delopay-shop' ),
			'default' => 'system-sans',
			'choices' => delopay_shop_font_choices( 'body' ),
		),
	);
}

function delopay_shop_schema_layout() {
	return array(
		'max_width'     => array(
			'section' => 'delopay_shop_layout',
			'type'    => 'select',
			'label'   => __( 'Max content width', 'delopay-shop' ),
			'default' => '1080',
			'choices' => array(
				'960'  => __( 'Narrow (960px)', 'delopay-shop' ),
				'1080' => __( 'Demo default (1080px)', 'delopay-shop' ),
				'1200' => __( 'Wide (1200px)', 'delopay-shop' ),
				'1440' => __( 'Extra wide (1440px)', 'delopay-shop' ),
			),
		),
		'border_radius' => array(
			'section' => 'delopay_shop_layout',
			'type'    => 'select',
			'label'   => __( 'Corner radius', 'delopay-shop' ),
			'default' => '12',
			'choices' => array(
				'0'  => __( 'Sharp (0)', 'delopay-shop' ),
				'4'  => __( 'Subtle (4px)', 'delopay-shop' ),
				'8'  => __( 'Soft (8px)', 'delopay-shop' ),
				'12' => __( 'Demo default (12px)', 'delopay-shop' ),
				'16' => __( 'Round (16px)', 'delopay-shop' ),
				'24' => __( 'Pill-ish (24px)', 'delopay-shop' ),
			),
		),
		'header_sticky' => array(
			'section' => 'delopay_shop_layout',
			'type'    => 'checkbox',
			'label'   => __( 'Sticky header', 'delopay-shop' ),
			'default' => true,
		),
	);
}

function delopay_shop_schema_grid() {
	return array(
		'grid_columns' => array(
			'section' => 'delopay_shop_grid',
			'type'    => 'select',
			'label'   => __( 'Columns', 'delopay-shop' ),
			'default' => '3',
			'choices' => array(
				'2' => __( '2', 'delopay-shop' ),
				'3' => __( '3', 'delopay-shop' ),
				'4' => __( '4', 'delopay-shop' ),
			),
		),
		'grid_limit'   => array(
			'section'     => 'delopay_shop_grid',
			'type'        => 'number',
			'label'       => __( 'Items per page', 'delopay-shop' ),
			'default'     => 12,
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 96,
				'step' => 1,
			),
		),
	);
}

function delopay_shop_schema_footer() {
	return array(
		'footer_copyright'  => array(
			'section'     => 'delopay_shop_footer',
			'type'        => 'textarea',
			'label'       => __( 'Footer copy', 'delopay-shop' ),
			'default'     => '',
			'description' => __( 'Defaults to "© YEAR Business name" + the contact info from the plugin.', 'delopay-shop' ),
		),
		'footer_show_badge' => array(
			'section' => 'delopay_shop_footer',
			'type'    => 'checkbox',
			'label'   => __( 'Show "Payments by DeloPay" badge', 'delopay-shop' ),
			'default' => true,
		),
	);
}

function delopay_shop_font_choices( $variant ) {
	$shared  = array(
		'system-sans'  => __( 'System sans (Inter-like)', 'delopay-shop' ),
		'system-serif' => __( 'System serif', 'delopay-shop' ),
	);
	$display = array(
		'Vollkorn'           => 'Vollkorn',
		'Playfair Display'   => 'Playfair Display',
		'Lora'               => 'Lora',
		'Cormorant Garamond' => 'Cormorant Garamond',
		'Fraunces'           => 'Fraunces',
		'Inter'              => 'Inter',
		'Manrope'            => 'Manrope',
	);
	$body    = array(
		'Inter'          => 'Inter',
		'DM Sans'        => 'DM Sans',
		'Manrope'        => 'Manrope',
		'Hanken Grotesk' => 'Hanken Grotesk',
		'IBM Plex Sans'  => 'IBM Plex Sans',
		'Source Sans 3'  => 'Source Sans 3',
		'Vollkorn'       => 'Vollkorn',
		'Lora'           => 'Lora',
	);
	return array_merge( 'display' === $variant ? $display : $body, $shared );
}

function delopay_shop_register_customizer( WP_Customize_Manager $wp_customize ) {
	$wp_customize->add_panel(
		DELOPAY_SHOP_PANEL,
		array(
			'title'       => __( 'DeloPay Shop', 'delopay-shop' ),
			'description' => __( 'Visual options for the DeloPay Shop theme. Business info, products, and payment settings live in the DeloPay plugin menu.', 'delopay-shop' ),
			'priority'    => 30,
		)
	);

	$priority = 10;
	foreach ( delopay_shop_customizer_sections() as $id => $title ) {
		$wp_customize->add_section(
			$id,
			array(
				'title'    => $title,
				'panel'    => DELOPAY_SHOP_PANEL,
				'priority' => $priority,
			)
		);
		$priority += 10;
	}

	foreach ( delopay_shop_customizer_schema() as $key => $field ) {
		delopay_shop_register_setting( $wp_customize, $key, $field );
	}
}
add_action( 'customize_register', 'delopay_shop_register_customizer' );

function delopay_shop_register_setting( WP_Customize_Manager $wp_customize, $key, $field ) {
	$setting_id = DELOPAY_SHOP_SETTING_PREFIX . $key;

	$sanitize = delopay_shop_sanitizer_for( $field['type'] );
	if ( 'select' === $field['type'] && ! empty( $field['choices'] ) ) {
		$choices  = array_keys( $field['choices'] );
		$default  = $field['default'];
		$sanitize = static function ( $value ) use ( $choices, $default ) {
			$value = sanitize_text_field( $value );
			return in_array( $value, $choices, true ) ? $value : $default;
		};
	}

	$wp_customize->add_setting(
		$setting_id,
		array(
			'default'           => $field['default'],
			'transport'         => 'refresh',
			'sanitize_callback' => $sanitize,
			'capability'        => 'edit_theme_options',
		)
	);

	$control_args = array(
		'label'       => $field['label'],
		'section'     => $field['section'],
		'description' => $field['description'] ?? '',
	);

	$control = delopay_shop_build_control( $wp_customize, $setting_id, $field, $control_args );
	$wp_customize->add_control( $control['id'], $control['args'] );
}

function delopay_shop_build_control( WP_Customize_Manager $wp_customize, $setting_id, $field, array $control_args ) {
	$type           = $field['type'];
	$object_classes = array(
		'color' => 'WP_Customize_Color_Control',
		'image' => 'WP_Customize_Image_Control',
	);

	if ( isset( $object_classes[ $type ] ) ) {
		$class = $object_classes[ $type ];
		return array(
			'id'   => new $class( $wp_customize, $setting_id, $control_args + array( 'settings' => $setting_id ) ),
			'args' => array(),
		);
	}

	$args_by_type = array(
		'select'   => array(
			'type'    => 'select',
			'choices' => $field['choices'] ?? array(),
		),
		'checkbox' => array( 'type' => 'checkbox' ),
		'textarea' => array( 'type' => 'textarea' ),
		'number'   => array(
			'type'        => 'number',
			'input_attrs' => $field['input_attrs'] ?? array(),
		),
	);
	$extra        = $args_by_type[ $type ] ?? array( 'type' => 'text' );

	return array(
		'id'   => $setting_id,
		'args' => $control_args + $extra,
	);
}

function delopay_shop_sanitizer_for( $type ) {
	static $map = array(
		'color'    => 'sanitize_hex_color',
		'image'    => 'esc_url_raw',
		'checkbox' => 'delopay_shop_sanitize_checkbox',
		'select'   => 'sanitize_text_field',
		'textarea' => 'wp_kses_post',
		'number'   => 'absint',
	);
	return $map[ $type ] ?? 'sanitize_text_field';
}

function delopay_shop_sanitize_checkbox( $value ) {
	return ( true === $value || 'true' === $value || '1' === $value || 1 === $value || 'on' === $value ) ? 1 : 0;
}

function delopay_shop_customizer_get( $key ) {
	$schema = delopay_shop_customizer_schema();
	if ( ! isset( $schema[ $key ] ) ) {
		return null;
	}
	return get_theme_mod( DELOPAY_SHOP_SETTING_PREFIX . $key, $schema[ $key ]['default'] );
}
