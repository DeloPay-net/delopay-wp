<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Settings {

	const OPTION_KEY     = 'wp_delopay_settings';
	const SETTINGS_GROUP = 'wp_delopay_settings_group';

	const ENV_PRODUCTION = 'production';
	const ENV_SANDBOX    = 'sandbox';
	const ENV_CUSTOM     = 'custom';

	const FALLBACK_COMPLETE_PATH = '/delopay-complete/';

	const COLOR_MODE_AUTO  = 'auto';
	const COLOR_MODE_LIGHT = 'light';
	const COLOR_MODE_DARK  = 'dark';

	const COLOR_TOKENS_CONFIGURABLE = array( 'surface_alt', 'fg', 'muted', 'line', 'accent', 'accent_fg' );
	const COLOR_TOKENS_FIXED        = array( 'bg', 'surface' );
	const COLOR_TOKENS_ALL          = array( 'bg', 'surface', 'surface_alt', 'fg', 'muted', 'line', 'accent', 'accent_fg' );

	private static $palette_defaults = array(
		'light' => array(
			'bg'          => '#ffffff',
			'surface'     => '#ffffff',
			'surface_alt' => '#f8fafc',
			'fg'          => '#0f172a',
			'muted'       => '#475569',
			'line'        => '#e2e8f0',
			'accent'      => '#1e4feb',
			'accent_fg'   => '#ffffff',
		),
		'dark'  => array(
			'bg'          => '#0a0f1c',
			'surface'     => '#101727',
			'surface_alt' => '#172033',
			'fg'          => '#e2e8f0',
			'muted'       => '#94a3b8',
			'line'        => '#1f2a44',
			'accent'      => '#4b72ef',
			'accent_fg'   => '#ffffff',
		),
	);

	private static $env_urls = array(
		'production' => array(
			'control_center_url' => 'https://dashboard.delopay.net',
			'checkout_base_url'  => 'https://checkout.delopay.net',
		),
		'sandbox'    => array(
			'control_center_url' => 'https://sandbox.delopay.net',
			'checkout_base_url'  => 'https://checkout-sandbox.delopay.net',
		),
	);

	private static $defaults = array(
		'api_key'            => '',
		'webhook_secret'     => '',
		'profile_id'         => '',
		'project_id'         => '',
		'environment'        => 'production',
		'control_center_url' => 'https://dashboard.delopay.net',
		'currency'           => 'USD',
		'checkout_base_url'  => 'https://checkout.delopay.net',
		'complete_page_id'   => 0,
		'business_name'      => '',
		'business_email'     => '',
		'business_support'   => '',
		'cart_checkout_mode' => 'both',
		'color_mode'         => 'auto',
		'light_surface_alt'  => '',
		'light_fg'           => '',
		'light_muted'        => '',
		'light_line'         => '',
		'light_accent'       => '',
		'light_accent_fg'    => '',
		'dark_surface_alt'   => '',
		'dark_fg'            => '',
		'dark_muted'         => '',
		'dark_line'          => '',
		'dark_accent'        => '',
		'dark_accent_fg'     => '',
	);

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public function register() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::$defaults,
			)
		);
	}

	public function sanitize( $input ) {
		$out = self::all();

		$trimmed = array(
			'api_key'        => 'trim_unslashed',
			'webhook_secret' => 'trim_unslashed',
		);
		foreach ( $trimmed as $key => $_ ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = trim( wp_unslash( $input[ $key ] ) );
			}
		}

		$text_fields = array( 'profile_id', 'project_id', 'business_name', 'business_support' );
		foreach ( $text_fields as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		$env = isset( $input['environment'] ) ? sanitize_key( $input['environment'] ) : $out['environment'];
		if ( ! in_array( $env, array( self::ENV_PRODUCTION, self::ENV_SANDBOX, self::ENV_CUSTOM ), true ) ) {
			$env = self::ENV_PRODUCTION;
		}
		$out['environment'] = $env;

		if ( self::ENV_CUSTOM === $env ) {
			foreach ( array( 'control_center_url', 'checkout_base_url' ) as $url_key ) {
				if ( isset( $input[ $url_key ] ) ) {
					$out[ $url_key ] = esc_url_raw( trim( $input[ $url_key ] ), array( 'http', 'https' ) );
				}
			}
		} else {
			$out['control_center_url'] = self::$env_urls[ $env ]['control_center_url'];
			$out['checkout_base_url']  = self::$env_urls[ $env ]['checkout_base_url'];
		}

		if ( isset( $input['currency'] ) ) {
			$out['currency'] = strtoupper( sanitize_text_field( substr( $input['currency'], 0, 3 ) ) );
		}
		if ( isset( $input['complete_page_id'] ) ) {
			$out['complete_page_id'] = (int) $input['complete_page_id'];
		}
		if ( isset( $input['business_email'] ) ) {
			$out['business_email'] = sanitize_email( $input['business_email'] );
		}
		if ( isset( $input['cart_checkout_mode'] ) ) {
			$mode                      = sanitize_key( $input['cart_checkout_mode'] );
			$out['cart_checkout_mode'] = in_array( $mode, array( 'both', 'embedded', 'external' ), true ) ? $mode : 'both';
		}

		if ( isset( $input['color_mode'] ) ) {
			$mode              = sanitize_key( $input['color_mode'] );
			$out['color_mode'] = in_array( $mode, array( self::COLOR_MODE_AUTO, self::COLOR_MODE_LIGHT, self::COLOR_MODE_DARK ), true )
				? $mode
				: self::COLOR_MODE_AUTO;
		}
		foreach ( array( 'light', 'dark' ) as $mode ) {
			foreach ( self::COLOR_TOKENS_CONFIGURABLE as $token ) {
				$key = $mode . '_' . $token;
				if ( isset( $input[ $key ] ) ) {
					$out[ $key ] = self::sanitize_hex_color( $input[ $key ] );
				}
			}
		}

		return $out;
	}

	private static function sanitize_hex_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#?([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value, $m ) ) {
			$hex = $m[1];
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			return '#' . strtolower( $hex );
		}
		return '';
	}

	public static function palette_defaults( $mode = null ) {
		if ( null === $mode ) {
			return self::$palette_defaults;
		}
		return self::$palette_defaults[ $mode ] ?? self::$palette_defaults['light'];
	}

	public static function palette( $mode ) {
		$mode    = self::COLOR_MODE_DARK === $mode ? self::COLOR_MODE_DARK : self::COLOR_MODE_LIGHT;
		$prefix  = $mode . '_';
		$default = self::$palette_defaults[ $mode ];
		$out     = array();
		foreach ( self::COLOR_TOKENS_ALL as $token ) {
			if ( in_array( $token, self::COLOR_TOKENS_FIXED, true ) ) {
				$out[ $token ] = $default[ $token ];
				continue;
			}
			$value         = (string) self::get( $prefix . $token );
			$out[ $token ] = '' !== $value ? $value : $default[ $token ];
		}
		return $out;
	}

	public static function color_mode() {
		$mode = (string) self::get( 'color_mode' );
		return in_array( $mode, array( self::COLOR_MODE_AUTO, self::COLOR_MODE_LIGHT, self::COLOR_MODE_DARK ), true )
			? $mode
			: self::COLOR_MODE_AUTO;
	}

	public static function cart_checkout_mode() {
		$mode = (string) self::get( 'cart_checkout_mode' );
		return in_array( $mode, array( 'both', 'embedded', 'external' ), true ) ? $mode : 'both';
	}

	public static function get_environment() {
		$saved = self::get( 'environment' );
		if ( in_array( $saved, array( self::ENV_PRODUCTION, self::ENV_SANDBOX, self::ENV_CUSTOM ), true ) ) {
			return $saved;
		}
		$cc       = (string) self::get( 'control_center_url' );
		$checkout = (string) self::get( 'checkout_base_url' );
		foreach ( self::$env_urls as $env => $urls ) {
			if ( rtrim( $cc, '/' ) === rtrim( $urls['control_center_url'], '/' )
				&& rtrim( $checkout, '/' ) === rtrim( $urls['checkout_base_url'], '/' )
			) {
				return $env;
			}
		}
		return self::ENV_CUSTOM;
	}

	public static function env_urls() {
		return self::$env_urls;
	}

	public static function all() {
		$saved  = get_option( self::OPTION_KEY, array() );
		$merged = wp_parse_args( is_array( $saved ) ? $saved : array(), self::$defaults );

		if ( defined( 'WP_DELOPAY_API_KEY' ) && is_string( WP_DELOPAY_API_KEY ) && '' !== WP_DELOPAY_API_KEY ) {
			$merged['api_key'] = WP_DELOPAY_API_KEY;
		}
		if ( defined( 'WP_DELOPAY_WEBHOOK_SECRET' ) && is_string( WP_DELOPAY_WEBHOOK_SECRET ) && '' !== WP_DELOPAY_WEBHOOK_SECRET ) {
			$merged['webhook_secret'] = WP_DELOPAY_WEBHOOK_SECRET;
		}
		return $merged;
	}

	public static function get( $key, $default_value = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default_value;
	}

	public static function is_overridden( $key ) {
		if ( 'api_key' === $key ) {
			return defined( 'WP_DELOPAY_API_KEY' ) && is_string( WP_DELOPAY_API_KEY ) && '' !== WP_DELOPAY_API_KEY;
		}
		if ( 'webhook_secret' === $key ) {
			return defined( 'WP_DELOPAY_WEBHOOK_SECRET' ) && is_string( WP_DELOPAY_WEBHOOK_SECRET ) && '' !== WP_DELOPAY_WEBHOOK_SECRET;
		}
		return false;
	}

	public static function defaults() {
		return self::$defaults;
	}

	public static function seed_defaults() {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, self::$defaults, '', false );
			return;
		}

		global $wpdb;
		// Direct update of wp_options.autoload — equivalent helpers landed in WP 6.4 but we support 6.0+.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->options,
			array( 'autoload' => 'no' ),
			array( 'option_name' => self::OPTION_KEY )
		);
		wp_cache_delete( 'alloptions', 'options' );
	}

	public static function get_complete_url() {
		$page_id = (int) self::get( 'complete_page_id' );
		if ( $page_id > 0 ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}
		return home_url( self::FALLBACK_COMPLETE_PATH );
	}

	public static function is_configured() {
		return ! empty( self::get( 'api_key' ) );
	}

	public static function get_api_base_url() {
		$base = rtrim( (string) self::get( 'control_center_url' ), '/' );
		return '' === $base ? '' : $base . '/api';
	}

	public static function get_branding_url() {
		$base       = rtrim( (string) self::get( 'control_center_url' ), '/' );
		$project_id = (string) self::get( 'project_id' );
		$profile_id = (string) self::get( 'profile_id' );

		if ( '' === $base || '' === $project_id || '' === $profile_id ) {
			return null;
		}

		return sprintf(
			'%s/projects/%s/shops/%s?tab=checkout',
			$base,
			rawurlencode( $project_id ),
			rawurlencode( $profile_id )
		);
	}
}
