<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Client {

	const REQUEST_TIMEOUT = 30;

	private $api_key;
	private $base_url;
	private $profile_id;

	public function __construct( $api_key = null, $base_url = null, $profile_id = null ) {
		$this->api_key    = $api_key ?? Delopay_Settings::get( 'api_key' );
		$this->base_url   = rtrim( $base_url ?? Delopay_Settings::get_api_base_url(), '/' );
		$this->profile_id = $profile_id ?? Delopay_Settings::get( 'profile_id' );
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

	/**
	 * Capture a previously authorized payment (two-step / manual capture flow).
	 *
	 * @param string $payment_id Payment id to capture.
	 * @param array  $params     Optional body, e.g. array( 'amount_to_capture' => 1000 ).
	 */
	public function capture_payment( $payment_id, $params = array() ) {
		return $this->request( 'POST', '/payments/' . rawurlencode( $payment_id ) . '/capture', $params );
	}

	/**
	 * Cancel/void a payment before it is captured.
	 *
	 * @param string $payment_id     Payment id to cancel.
	 * @param string $cancel_reason  Optional human-readable reason.
	 */
	public function cancel_payment( $payment_id, $cancel_reason = '' ) {
		$body = array();
		if ( '' !== (string) $cancel_reason ) {
			$body['cancellation_reason'] = (string) $cancel_reason;
		}
		return $this->request( 'POST', '/payments/' . rawurlencode( $payment_id ) . '/cancel', $body );
	}

	public function create_refund( $params ) {
		return $this->request( 'POST', '/refunds', $params );
	}

	public function retrieve_refund( $refund_id ) {
		return $this->request( 'GET', '/refunds/' . rawurlencode( $refund_id ) );
	}

	/**
	 * List disputes (read-only). Optional filters: limit, offset, dispute_status, etc.
	 *
	 * @param array $query Query-string filters.
	 */
	public function list_disputes( $query = array() ) {
		return $this->request( 'GET', '/disputes/list' . self::query_string( $query ) );
	}

	/**
	 * Retrieve a single dispute by id (read-only).
	 *
	 * @param string $dispute_id Dispute id.
	 */
	public function retrieve_dispute( $dispute_id ) {
		return $this->request( 'GET', '/disputes/' . rawurlencode( $dispute_id ) );
	}

	// Subscriptions (profile-scoped — the backend requires an X-Profile-Id
	// header to resolve the shop / billing processor, else IR_04).

	/**
	 * List purchasable subscription items (plans/addons).
	 *
	 * @param array $query e.g. array( 'item_type' => 'plan' ).
	 */
	public function get_subscription_items( $query = array() ) {
		return $this->request( 'GET', '/subscriptions/items' . self::query_string( $query ), null, $this->profile_header() );
	}

	/**
	 * Estimate the cost of a subscription before creating it.
	 *
	 * @param array $query e.g. array( 'item_price_id' => 'price_x' ).
	 */
	public function get_subscription_estimate( $query = array() ) {
		return $this->request( 'GET', '/subscriptions/estimate' . self::query_string( $query ), null, $this->profile_header() );
	}

	/**
	 * Create a subscription (without confirming). The buyer completes payment on
	 * the hosted checkout using the returned client_secret.
	 *
	 * @param array $params Subscription create body.
	 */
	public function create_subscription( $params ) {
		return $this->request( 'POST', '/subscriptions/create', $params, $this->profile_header() );
	}

	/**
	 * Retrieve a subscription by id.
	 *
	 * @param string $subscription_id Subscription id.
	 */
	public function get_subscription( $subscription_id ) {
		return $this->request( 'GET', '/subscriptions/' . rawurlencode( $subscription_id ), null, $this->profile_header() );
	}

	/**
	 * Pause a subscription.
	 *
	 * @param string $subscription_id Subscription id.
	 * @param array  $params          Optional pause options.
	 */
	public function pause_subscription( $subscription_id, $params = array() ) {
		return $this->request( 'POST', '/subscriptions/' . rawurlencode( $subscription_id ) . '/pause', $params, $this->profile_header() );
	}

	/**
	 * Resume a paused subscription.
	 *
	 * @param string $subscription_id Subscription id.
	 * @param array  $params          Optional resume options.
	 */
	public function resume_subscription( $subscription_id, $params = array() ) {
		return $this->request( 'POST', '/subscriptions/' . rawurlencode( $subscription_id ) . '/resume', $params, $this->profile_header() );
	}

	/**
	 * Cancel a subscription.
	 *
	 * @param string $subscription_id Subscription id.
	 * @param array  $params          Optional cancel options.
	 */
	public function cancel_subscription( $subscription_id, $params = array() ) {
		return $this->request( 'POST', '/subscriptions/' . rawurlencode( $subscription_id ) . '/cancel', $params, $this->profile_header() );
	}

	/**
	 * Header set for profile-scoped subscription calls.
	 *
	 * @return array
	 */
	private function profile_header() {
		return ! empty( $this->profile_id )
			? array( 'X-Profile-Id' => $this->profile_id )
			: array();
	}

	/**
	 * Build a query string ("?a=b&c=d") from a filter array, skipping null/empty.
	 *
	 * @param array $query Associative filters.
	 * @return string
	 */
	private static function query_string( $query ) {
		if ( empty( $query ) || ! is_array( $query ) ) {
			return '';
		}
		$clean = array();
		foreach ( $query as $key => $value ) {
			if ( null === $value || '' === $value ) {
				continue;
			}
			$clean[ $key ] = $value;
		}
		return empty( $clean ) ? '' : '?' . http_build_query( $clean );
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

	private function request( $method, $path, $body = null, $extra_headers = array() ) {
		if ( ! $this->is_ready() ) {
			return new WP_Error(
				'delopay_not_configured',
				__( 'DeloPay is not connected. Open DeloPay → Settings and click "Connect to DeloPay".', 'delopay' )
			);
		}

		$headers = array(
			'api-key'    => $this->api_key,
			'Accept'     => 'application/json',
			'User-Agent' => 'delopay/' . DELOPAY_VERSION . '; ' . home_url( '/' ),
		);
		if ( is_array( $extra_headers ) && ! empty( $extra_headers ) ) {
			$headers = array_merge( $headers, $extra_headers );
		}

		$args = array(
			'method'  => $method,
			'timeout' => self::REQUEST_TIMEOUT,
			'headers' => $headers,
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			// An empty PHP array encodes to "[]", which the API's object-shaped
			// request bodies reject. Send "{}" so the Content-Type is honoured.
			$args['body'] = ( is_array( $body ) && empty( $body ) )
				? '{}'
				: wp_json_encode( $body );
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
				'delopay_api_error',
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
