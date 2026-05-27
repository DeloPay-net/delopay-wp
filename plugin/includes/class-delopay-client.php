<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Client {

	const REQUEST_TIMEOUT = 30;

	private $api_key;
	private $base_url;
	private $profile_id;

	public function __construct( $api_key = null, $base_url = null, $profile_id = null ) {
		$this->api_key    = $api_key ?? WP_Delopay_Settings::get( 'api_key' );
		$this->base_url   = rtrim( $base_url ?? WP_Delopay_Settings::get_api_base_url(), '/' );
		$this->profile_id = $profile_id ?? WP_Delopay_Settings::get( 'profile_id' );
	}

	public function is_ready() {
		return ! empty( $this->api_key ) && ! empty( $this->base_url );
	}

	public function profile_id() {
		return $this->profile_id;
	}

	public function create_payment( $params ) {
		if ( ! empty( $this->profile_id ) && empty( $params['profile_id'] ) ) {
			$params['profile_id'] = $this->profile_id;
		}
		return $this->request( 'POST', '/payments', $params );
	}

	public function retrieve_payment( $payment_id ) {
		return $this->request( 'GET', '/payments/' . rawurlencode( $payment_id ) );
	}

	public function create_refund( $params ) {
		return $this->request( 'POST', '/refunds', $params );
	}

	public function retrieve_refund( $refund_id ) {
		return $this->request( 'GET', '/refunds/' . rawurlencode( $refund_id ) );
	}

	public function probe_credentials() {
		$probe_id = 'probe_' . wp_generate_password( 16, false, false );
		$result   = $this->retrieve_payment( $probe_id );

		if ( ! is_wp_error( $result ) ) {
			return array(
				'ok'      => true,
				'status'  => 200,
				'message' => '',
			);
		}

		$data    = $result->get_error_data();
		$status  = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
		$message = (string) $result->get_error_message();

		if ( 401 === $status || 403 === $status ) {
			return array(
				'ok'      => false,
				'status'  => $status,
				'message' => $message,
			);
		}
		if ( 404 === $status ) {
			return array(
				'ok'      => true,
				'status'  => 404,
				'message' => '',
			);
		}
		return array(
			'ok'      => true,
			'status'  => $status,
			'message' => $message,
		);
	}

	private function request( $method, $path, $body = null ) {
		if ( ! $this->is_ready() ) {
			return new WP_Error(
				'wp_delopay_not_configured',
				__( 'DeloPay is not connected. Open DeloPay → Settings and click "Connect to DeloPay".', 'wp-delopay' )
			);
		}

		$args = array(
			'method'  => $method,
			'timeout' => self::REQUEST_TIMEOUT,
			'headers' => array(
				'api-key'    => $this->api_key,
				'Accept'     => 'application/json',
				'User-Agent' => 'wp-delopay/' . WP_DELOPAY_VERSION . '; ' . home_url( '/' ),
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->base_url . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$raw     = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'wp_delopay_api_error',
				self::extract_error_message( $decoded, $code, $raw ),
				array(
					'status' => $code,
					'body'   => $decoded,
				)
			);
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	private static function extract_error_message( $decoded, $code, $raw ) {
		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['error']['message'] ) ) {
				return (string) $decoded['error']['message'];
			}
			if ( isset( $decoded['message'] ) ) {
				return (string) $decoded['message'];
			}
			if ( isset( $decoded['error'] ) && is_string( $decoded['error'] ) ) {
				return $decoded['error'];
			}
			return sprintf( 'HTTP %d (unrecognized response shape)', (int) $code );
		}

		$snippet = trim( wp_strip_all_tags( (string) $raw ) );
		if ( '' === $snippet ) {
			return sprintf( 'HTTP %d (empty body)', (int) $code );
		}
		if ( function_exists( 'mb_substr' ) && mb_strlen( $snippet ) > 160 ) {
			$snippet = mb_substr( $snippet, 0, 160 ) . '…';
		} elseif ( strlen( $snippet ) > 160 ) {
			$snippet = substr( $snippet, 0, 160 ) . '…';
		}
		return sprintf( 'HTTP %d: %s', (int) $code, $snippet );
	}
}
