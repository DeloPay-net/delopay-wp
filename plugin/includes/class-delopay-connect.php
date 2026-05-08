<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Connect {

	const TRANSIENT_PREFIX = 'delopay_connect_';
	const STATE_TTL        = 600;
	const STATE_REGEX      = '/^[A-Za-z0-9_\-]{16,128}$/';

	const OPTION_MERCHANT_ID  = 'wp_delopay_connect_merchant_id';
	const OPTION_API_KEY_ID   = 'wp_delopay_connect_api_key_id';
	const OPTION_CONNECTED_AT = 'wp_delopay_connect_connected_at';

	const REVOKE_TIMEOUT = 15;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$admin_only = array( $this, 'require_admin' );
		$public     = '__return_true';

		register_rest_route(
			'delopay/v1',
			'/connect/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => $admin_only,
				'args'                => array(
					'environment'        => array( 'required' => false ),
					'control_center_url' => array( 'required' => false ),
				),
			)
		);

		register_rest_route(
			'delopay/v1',
			'/connect/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete' ),
				'permission_callback' => $public,
			)
		);

		register_rest_route(
			'delopay/v1',
			'/connect/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => $admin_only,
			)
		);

		register_rest_route(
			'delopay/v1',
			'/connect/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => $admin_only,
			)
		);
	}

	public function require_admin() {
		return current_user_can( 'manage_options' );
	}

	public function start( WP_REST_Request $request ) {
		$env = $this->resolve_environment( (string) $request->get_param( 'environment' ) );

		$control_center_url = $this->resolve_control_center_url( $env, (string) $request->get_param( 'control_center_url' ) );
		if ( '' === $control_center_url ) {
			return new WP_REST_Response( array( 'error' => __( 'Control center URL is not configured.', 'wp-delopay' ) ), 400 );
		}

		$state       = self::random_token( 32 );
		$site_url    = home_url( '/' );
		$webhook_url = rest_url( 'delopay/v1/webhook' );
		$callback    = rest_url( 'delopay/v1/connect/complete' );

		set_transient(
			self::TRANSIENT_PREFIX . $state,
			array(
				'user_id'            => get_current_user_id(),
				'site_url'           => $site_url,
				'webhook_url'        => $webhook_url,
				'callback'           => $callback,
				'environment'        => $env,
				'control_center_url' => $control_center_url,
				'created_at'         => time(),
			),
			self::STATE_TTL
		);

		$redirect_url = add_query_arg(
			array(
				'state'       => $state,
				'site_url'    => $site_url,
				'site_name'   => get_bloginfo( 'name' ),
				'webhook_url' => $webhook_url,
				'callback'    => $callback,
				'environment' => $env,
			),
			$control_center_url . '/connect'
		);

		return new WP_REST_Response(
			array(
				'state'        => $state,
				'redirect_url' => $redirect_url,
				'expires_in'   => self::STATE_TTL,
				'site_origin'  => self::origin_of( $site_url ),
			),
			200
		);
	}

	private function resolve_environment( $param ) {
		$env   = sanitize_key( $param );
		$valid = array( WP_Delopay_Settings::ENV_PRODUCTION, WP_Delopay_Settings::ENV_SANDBOX, WP_Delopay_Settings::ENV_CUSTOM );
		return in_array( $env, $valid, true ) ? $env : WP_Delopay_Settings::get_environment();
	}

	private function resolve_control_center_url( $env, $param ) {
		$env_urls = WP_Delopay_Settings::env_urls();
		if ( WP_Delopay_Settings::ENV_CUSTOM === $env ) {
			$url = esc_url_raw( trim( $param ), array( 'https', 'http' ) );
			if ( '' === $url ) {
				$url = (string) WP_Delopay_Settings::get( 'control_center_url' );
			}
		} else {
			$url = $env_urls[ $env ]['control_center_url'];
		}
		return rtrim( $url, '/' );
	}

	public function complete( WP_REST_Request $request ) {
		$body  = $this->read_body( $request );
		$state = isset( $body['state'] ) ? (string) $body['state'] : '';
		if ( '' === $state || ! preg_match( self::STATE_REGEX, $state ) ) {
			return self::render_complete_page( false, __( 'Missing or invalid connect state.', 'wp-delopay' ) );
		}

		$pending = get_transient( self::TRANSIENT_PREFIX . $state );
		delete_transient( self::TRANSIENT_PREFIX . $state );

		if ( ! is_array( $pending ) ) {
			return self::render_complete_page( false, __( 'Connect link expired or already used. Please try again.', 'wp-delopay' ) );
		}

		$creds = $this->extract_credentials( $body );
		if ( '' === $creds['api_key'] || '' === $creds['webhook_secret'] || '' === $creds['profile_id'] ) {
			return self::render_complete_page( false, __( 'Control center returned incomplete credentials.', 'wp-delopay' ) );
		}

		$probe_base = rtrim( (string) $pending['control_center_url'], '/' ) . '/api';
		$probe      = ( new WP_Delopay_Client( $creds['api_key'], $probe_base, $creds['profile_id'] ) )->probe_credentials();
		if ( empty( $probe['ok'] ) ) {
			WP_Delopay_Log::warning(
				'connect probe rejected credentials',
				array(
					'status'  => $probe['status'] ?? 0,
					'message' => $probe['message'] ?? '',
				)
			);
			return self::render_complete_page(
				false,
				__( 'DeloPay rejected the credentials returned by the control center. Please retry the Connect flow.', 'wp-delopay' )
			);
		}

		$this->persist_credentials( $creds, $pending );

		return self::render_complete_page( true, '', self::origin_of( home_url( '/' ) ) );
	}

	private function read_body( WP_REST_Request $request ) {
		$content_type = (string) $request->get_header( 'content_type' );
		$body         = false !== stripos( $content_type, 'application/json' )
			? $request->get_json_params()
			: $request->get_body_params();
		return is_array( $body ) ? $body : array();
	}

	private function extract_credentials( array $body ) {
		return array(
			'api_key'        => isset( $body['api_key'] ) ? trim( (string) $body['api_key'] ) : '',
			'webhook_secret' => isset( $body['webhook_secret'] ) ? trim( (string) $body['webhook_secret'] ) : '',
			'profile_id'     => isset( $body['profile_id'] ) ? sanitize_text_field( (string) $body['profile_id'] ) : '',
			'project_id'     => isset( $body['project_id'] ) ? sanitize_text_field( (string) $body['project_id'] ) : '',
			'merchant_id'    => isset( $body['merchant_id'] ) ? sanitize_text_field( (string) $body['merchant_id'] ) : '',
			'api_key_id'     => isset( $body['api_key_id'] ) ? sanitize_text_field( (string) $body['api_key_id'] ) : '',
		);
	}

	private function persist_credentials( array $creds, array $pending ) {
		$current                       = WP_Delopay_Settings::all();
		$current['api_key']            = $creds['api_key'];
		$current['webhook_secret']     = $creds['webhook_secret'];
		$current['profile_id']         = $creds['profile_id'];
		$current['project_id']         = $creds['project_id'];
		$current['environment']        = $pending['environment'];
		$current['control_center_url'] = $pending['control_center_url'];

		$env_urls = WP_Delopay_Settings::env_urls();
		if ( WP_Delopay_Settings::ENV_CUSTOM !== $pending['environment'] && isset( $env_urls[ $pending['environment'] ] ) ) {
			$current['checkout_base_url'] = $env_urls[ $pending['environment'] ]['checkout_base_url'];
		}

		if ( '' !== $creds['merchant_id'] ) {
			update_option( self::OPTION_MERCHANT_ID, $creds['merchant_id'], false );
		}
		if ( '' !== $creds['api_key_id'] ) {
			update_option( self::OPTION_API_KEY_ID, $creds['api_key_id'], false );
		}

		update_option( WP_Delopay_Settings::OPTION_KEY, $current );
		update_option( self::OPTION_CONNECTED_AT, time(), false );
	}

	public function cancel( WP_REST_Request $request ) {
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		if ( '' !== $state ) {
			delete_transient( self::TRANSIENT_PREFIX . $state );
		}
		return new WP_REST_Response( array( 'cancelled' => true ), 200 );
	}

	public function disconnect( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WP REST callback signature.
		unset( $request );
		[ $revoke_status, $revoke_error ] = $this->revoke_remote_key();

		$current                   = WP_Delopay_Settings::all();
		$current['api_key']        = '';
		$current['webhook_secret'] = '';
		$current['profile_id']     = '';
		$current['project_id']     = '';
		update_option( WP_Delopay_Settings::OPTION_KEY, $current );

		delete_option( self::OPTION_MERCHANT_ID );
		delete_option( self::OPTION_API_KEY_ID );
		delete_option( self::OPTION_CONNECTED_AT );

		return new WP_REST_Response(
			array(
				'disconnected' => true,
				'revoke'       => $revoke_status,
				'revoke_error' => $revoke_error,
			),
			200
		);
	}

	private function revoke_remote_key() {
		$api_key     = (string) WP_Delopay_Settings::get( 'api_key' );
		$merchant_id = (string) get_option( self::OPTION_MERCHANT_ID, '' );
		$key_id      = (string) get_option( self::OPTION_API_KEY_ID, '' );

		if ( '' === $api_key || '' === $merchant_id || '' === $key_id ) {
			return array( 'skipped', null );
		}

		$base = rtrim( (string) WP_Delopay_Settings::get_api_base_url(), '/' );
		if ( '' === $base ) {
			return array( 'skipped', null );
		}

		$response = wp_remote_request(
			$base . '/api_keys/' . rawurlencode( $merchant_id ) . '/' . rawurlencode( $key_id ),
			array(
				'method'  => 'DELETE',
				'timeout' => self::REVOKE_TIMEOUT,
				'headers' => array(
					'api-key'    => $api_key,
					'Accept'     => 'application/json',
					'User-Agent' => 'wp-delopay/' . WP_DELOPAY_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return array( 'revoked', null );
		}
		return array( 'failed', sprintf( 'HTTP %d', $code ) );
	}

	private static function render_complete_page( $ok, $error_message = '', $opener_origin = '' ) {
		$status = $ok ? 200 : 400;
		$title  = $ok
			? __( 'Connected to DeloPay', 'wp-delopay' )
			: __( 'Connection failed', 'wp-delopay' );
		$body   = $ok
			? __( 'You can close this window — your site is now connected to DeloPay.', 'wp-delopay' )
			: $error_message;

		$post_message_origin = '' !== $opener_origin ? $opener_origin : '*';

		$payload = wp_json_encode(
			array(
				'source' => 'wp-delopay-connect',
				'ok'     => (bool) $ok,
				'error'  => $ok ? null : $error_message,
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		$symbol_svg = '<svg viewBox="0 0 64 64" width="44" height="44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
			. '<path d="M10 12 L28 12 L46 32 L28 52 L10 52 L28 32 Z" fill="#1E4FEB"/>'
			. '<path d="M30 12 L42 12 L54 24 L54 40 L42 52 L30 52 L48 32 Z" fill="#1E4FEB" fill-opacity="0.55"/>'
			. '</svg>';

		$styles =
			':root{--dp-blue:#1E4FEB;--dp-success:#10b981;--dp-warning:#d63638;'
			. '--dp-text:#0f172a;--dp-text-secondary:#475569;--dp-border:#e2e8f0;}'
			. 'html,body{height:100%;}'
			. 'body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
			. 'background:#f8fafc;color:var(--dp-text);'
			. 'display:flex;align-items:center;justify-content:center;padding:1rem;}'
			. '.card{background:#fff;border:1px solid var(--dp-border);border-radius:6px;'
			. 'padding:2.25rem 2.5rem;max-width:420px;text-align:center;'
			. 'box-shadow:0 1px 2px rgba(15,23,42,.04);}'
			. '.symbol{display:inline-flex;align-items:center;justify-content:center;'
			. 'width:64px;height:64px;border-radius:6px;margin-bottom:1rem;'
			. 'background:rgba(30,79,235,0.08);}'
			. '.card h1{font-size:1.25rem;margin:0 0 .5rem;font-weight:600;}'
			. '.card p{margin:0;color:var(--dp-text-secondary);line-height:1.5;}'
			. '.ok .accent{color:var(--dp-success);}'
			. '.err .accent{color:var(--dp-warning);}'
			. '.brand-bar{display:block;width:32px;height:3px;border-radius:2px;'
			. 'background:var(--dp-blue);margin:0 auto 1.25rem;}';

		$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title>'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<style>' . $styles . '</style>'
			. '</head><body>'
			. '<div class="card ' . ( $ok ? 'ok' : 'err' ) . '">'
			. '<div class="symbol">' . $symbol_svg . '</div>'
			. '<span class="brand-bar accent" aria-hidden="true"></span>'
			. '<h1>' . esc_html( $title ) . '</h1>'
			. '<p>' . esc_html( $body ) . '</p>'
			. '</div>'
			. '<script>(function(){try{if(window.opener){window.opener.postMessage(' . $payload . ',' . wp_json_encode( $post_message_origin ) . ');}}catch(e){}'
			. 'setTimeout(function(){try{window.close();}catch(e){}},800);})();</script>'
			. '</body></html>';

		$response = new WP_REST_Response( null, $status );
		$response->header( 'Content-Type', 'text/html; charset=UTF-8' );

		add_filter(
			'rest_pre_serve_request',
			static function ( $served, $result ) use ( $html, $response ) {
				if ( $result === $response ) {
					echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					return true;
				}
				return $served;
			},
			10,
			2
		);

		return $response;
	}

	private static function random_token( $bytes ) {
		$raw = function_exists( 'random_bytes' ) ? random_bytes( $bytes ) : openssl_random_pseudo_bytes( $bytes );
		// URL-safe base64 encoding of cryptographic random bytes for OAuth-style tokens, not code obfuscation.
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function origin_of( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}
		return $origin;
	}

	public static function is_connected() {
		return (bool) get_option( self::OPTION_CONNECTED_AT, 0 );
	}

	public static function connected_at() {
		return (int) get_option( self::OPTION_CONNECTED_AT, 0 );
	}
}
