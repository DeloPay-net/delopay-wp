<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Webhook {

	const ROUTE            = 'wp-json/delopay/v1/webhook';
	const QUERY_VAR        = 'delopay_webhook';
	const SIGNATURE_HEADER = 'x-webhook-signature-512';

	const SEEN_EVENT_OPTION = 'delopay_seen_events';
	const SEEN_EVENT_TTL    = 7 * DAY_IN_SECONDS;
	const SEEN_EVENT_MAX    = 1000;
	const SEEN_EVENT_GROUP  = 'delopay_events';

	const REFUND_LOCK_TIMEOUT = 5;

	private static $payment_event_map = array(
		'payment_succeeded'              => 'succeeded',
		'payment_intent_succeeded'       => 'succeeded',
		'payment_captured'               => 'succeeded',
		'payment_authorized'             => 'requires_capture',
		'payment_partially_authorized'   => 'requires_capture',
		'payment_failed'                 => 'failed',
		'payment_intent_failed'          => 'failed',
		'payment_processing'             => 'processing',
		'payment_intent_processing'      => 'processing',
		'payment_cancelled'              => 'cancelled',
		'payment_intent_cancelled'       => 'cancelled',
		'payment_cancelled_post_capture' => 'cancelled',
		'payment_expired'                => 'expired',
		'payment_intent_expired'         => 'expired',
	);

	private static $refund_event_map = array(
		'refund_succeeded' => 'succeeded',
		'refund_success'   => 'succeeded',
		'refund_failed'    => 'failed',
		'refund_failure'   => 'failed',
	);

	private static $dispute_event_map = array(
		'dispute_opened'     => 'opened',
		'dispute_challenged' => 'challenged',
		'dispute_won'        => 'won',
		'dispute_lost'       => 'lost',
		'dispute_accepted'   => 'accepted',
		'dispute_cancelled'  => 'cancelled',
		'dispute_expired'    => 'expired',
	);

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_route' ) );
		add_action( 'parse_request', array( $this, 'maybe_handle' ) );
	}

	public function register_route() {
		add_rewrite_rule( '^delopay-webhook/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
	}

	public function maybe_handle( $wp ) {
		$is_pretty = ! empty( $wp->query_vars[ self::QUERY_VAR ] );
		// Webhook endpoint detection — authentication is by HMAC signature in handle_request(), not WP nonce.
		$is_query = isset( $_GET[ self::QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $is_pretty && ! $is_query ) {
			return;
		}
		$this->handle_request();
		exit;
	}

	public static function rest_route() {
		register_rest_route(
			'delopay/v1',
			'/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_handler' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function rest_handler( WP_REST_Request $request ) {
		$raw = $request->get_body();
		if ( '' === $raw || null === $raw ) {
			$raw = file_get_contents( 'php://input' );
		}
		$signature = $request->get_header( self::SIGNATURE_HEADER );
		$result    = self::process( (string) $raw, $signature );
		return new WP_REST_Response( $result['body'], $result['status'] );
	}

	private function handle_request() {
		$raw       = file_get_contents( 'php://input' );
		$signature = $this->signature_from_server();
		$result    = self::process( $raw, $signature );

		status_header( $result['status'] );
		header( 'Content-Type: application/json' );
		echo wp_json_encode( $result['body'] );
	}

	private function signature_from_server() {
		foreach ( array( 'HTTP_X_WEBHOOK_SIGNATURE_512', 'HTTP_X_WEBHOOK_SIGNATURE512' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}
		if ( function_exists( 'apache_request_headers' ) ) {
			foreach ( apache_request_headers() as $k => $v ) {
				if ( 0 === strcasecmp( $k, 'X-Webhook-Signature-512' ) ) {
					return $v;
				}
			}
		}
		return '';
	}

	public static function process( $raw_body, $signature ) {
		$verified = self::verify_signature( $raw_body, $signature );
		if ( $verified ) {
			return $verified;
		}

		$event = json_decode( $raw_body, true );
		// Backend sends `event_type`; older payloads used `type`. Accept both.
		$event_type = is_array( $event ) ? (string) ( $event['event_type'] ?? $event['type'] ?? '' ) : '';
		if ( '' === $event_type ) {
			return self::response( 400, array( 'error' => 'invalid event payload' ) );
		}

		$dedup_key = self::dedup_key_for( $event, $raw_body );
		if ( ! self::claim_event( $dedup_key ) ) {
			return self::response(
				200,
				array(
					'received'  => true,
					'duplicate' => true,
				)
			);
		}

		$type = $event_type;
		// Resource object lives under `content.object` (e.g. PaymentsResponse);
		// older payloads put it directly under `data`. Accept both.
		if ( isset( $event['content']['object'] ) && is_array( $event['content']['object'] ) ) {
			$data = $event['content']['object'];
		} elseif ( isset( $event['data'] ) && is_array( $event['data'] ) ) {
			$data = $event['data'];
		} else {
			$data = array();
		}

		self::dispatch( $type, $data );

		do_action( 'delopay_webhook_received', $type, $data, $event );

		return self::response( 200, array( 'received' => true ) );
	}

	private static function verify_signature( $raw_body, $signature ) {
		$secret = (string) Delopay_Settings::get( 'webhook_secret' );

		if ( '' === $secret ) {
			return self::response( 500, array( 'error' => 'webhook secret not configured' ) );
		}
		if ( ! is_string( $signature ) || '' === trim( $signature ) ) {
			return self::response( 400, array( 'error' => 'missing X-Webhook-Signature-512 header' ) );
		}

		$expected = hash_hmac( 'sha512', $raw_body, $secret );
		if ( ! hash_equals( $expected, trim( $signature ) ) ) {
			return self::response( 400, array( 'error' => 'invalid signature' ) );
		}

		return null;
	}

	private static function response( $status, $body ) {
		return array(
			'status' => $status,
			'body'   => $body,
		);
	}

	private static function dedup_key_for( $event, $raw_body ) {
		foreach ( array( 'event_id', 'idempotent_event_id' ) as $key ) {
			if ( ! empty( $event[ $key ] ) && is_string( $event[ $key ] ) ) {
				return (string) $event[ $key ];
			}
		}
		return 'sha256:' . hash( 'sha256', (string) $raw_body );
	}

	private static function dispatch( $type, $data ) {
		if ( isset( self::$payment_event_map[ $type ] ) ) {
			self::apply_payment_update( $data, self::$payment_event_map[ $type ] );
			return;
		}
		if ( isset( self::$refund_event_map[ $type ] ) ) {
			self::apply_refund_update( $data, self::$refund_event_map[ $type ] );
			return;
		}
		if ( isset( self::$dispute_event_map[ $type ] ) ) {
			self::apply_dispute_update( $data, self::$dispute_event_map[ $type ] );
			return;
		}
		// Subscription and invoice lifecycle events. The plugin has no recurring
		// storefront yet, so we record them and fire an action for extensions /
		// future subscription support rather than mutate order state.
		if ( 0 === strpos( $type, 'subscription' ) || 0 === strpos( $type, 'invoice' ) ) {
			self::apply_subscription_update( $type, $data );
			return;
		}
		Delopay_Log::info( 'unhandled webhook event: ' . $type );
	}

	private static function claim_event( $key ) {
		if ( false === wp_cache_add( $key, time(), self::SEEN_EVENT_GROUP, self::SEEN_EVENT_TTL ) ) {
			return false;
		}

		$seen = get_option( self::SEEN_EVENT_OPTION, array() );
		if ( ! is_array( $seen ) ) {
			$seen = array();
		}
		if ( isset( $seen[ $key ] ) ) {
			return false;
		}

		$now    = time();
		$cutoff = $now - self::SEEN_EVENT_TTL;
		foreach ( $seen as $k => $ts ) {
			if ( ! is_int( $ts ) || $ts < $cutoff ) {
				unset( $seen[ $k ] );
			}
		}
		$seen[ $key ] = $now;
		if ( count( $seen ) > self::SEEN_EVENT_MAX ) {
			asort( $seen );
			$seen = array_slice( $seen, count( $seen ) - self::SEEN_EVENT_MAX, null, true );
		}
		update_option( self::SEEN_EVENT_OPTION, $seen, false );

		return true;
	}

	private static function apply_payment_update( $data, $fallback_status ) {
		if ( empty( $data['payment_id'] ) ) {
			return;
		}

		$reference = isset( $data['merchant_order_reference_id'] ) ? (string) $data['merchant_order_reference_id'] : '';
		if ( '' === $reference && isset( $data['metadata']['order_id'] ) && is_string( $data['metadata']['order_id'] ) ) {
			$reference = (string) $data['metadata']['order_id'];
		}

		Delopay_Orders::update_status(
			$data['payment_id'],
			isset( $data['status'] ) ? (string) $data['status'] : $fallback_status,
			isset( $data['error_code'] ) ? (string) $data['error_code'] : null,
			isset( $data['error_message'] ) ? (string) $data['error_message'] : null,
			true,
			$reference
		);
	}

	private static function apply_refund_update( $data, $fallback_status ) {
		if ( empty( $data['refund_id'] ) || empty( $data['payment_id'] ) ) {
			return;
		}

		$order    = Delopay_Orders::find( (string) $data['payment_id'] );
		$order_id = $order ? (string) $order['order_id'] : '';

		$locked = '' !== $order_id && Delopay_Orders::acquire_refund_lock( $order_id, self::REFUND_LOCK_TIMEOUT );

		try {
			Delopay_Orders::record_refund(
				array(
					'refund_id'     => (string) $data['refund_id'],
					'order_id'      => $order_id,
					'payment_id'    => (string) $data['payment_id'],
					'amount_minor'  => isset( $data['amount'] ) ? (int) $data['amount'] : 0,
					'status'        => isset( $data['status'] ) ? (string) $data['status'] : $fallback_status,
					'reason'        => $data['reason'] ?? null,
					'error_code'    => $data['error_code'] ?? null,
					'error_message' => $data['error_message'] ?? null,
				)
			);
		} finally {
			if ( $locked ) {
				Delopay_Orders::release_refund_lock( $order_id );
			}
		}
	}

	/**
	 * Handle a dispute lifecycle event. The plugin does not yet store disputes,
	 * so we log a notice (visible to the merchant) and fire an action other code
	 * can hook. Disputes are also viewable on demand via the admin Disputes page.
	 *
	 * @param array  $data   Dispute object from the webhook.
	 * @param string $status Normalised dispute status.
	 */
	private static function apply_dispute_update( $data, $status ) {
		$dispute_id = isset( $data['dispute_id'] ) ? (string) $data['dispute_id'] : '';
		$payment_id = isset( $data['payment_id'] ) ? (string) $data['payment_id'] : '';

		Delopay_Log::info(
			sprintf(
				'dispute %s is now "%s"%s',
				'' !== $dispute_id ? $dispute_id : '(unknown)',
				$status,
				'' !== $payment_id ? ' (payment ' . $payment_id . ')' : ''
			)
		);

		/**
		 * Fires when a dispute webhook is received.
		 *
		 * @param string $status     Normalised dispute status.
		 * @param array  $data       Dispute object.
		 * @param string $payment_id Associated payment id, if present.
		 */
		do_action( 'delopay_dispute_event', $status, $data, $payment_id );
	}

	/**
	 * Handle a subscription / invoice lifecycle event. No recurring storefront
	 * exists yet, so we log and expose an action hook for extensions.
	 *
	 * @param string $type Full event type (e.g. invoice_paid).
	 * @param array  $data Event object.
	 */
	private static function apply_subscription_update( $type, $data ) {
		$ref = isset( $data['subscription_id'] ) ? (string) $data['subscription_id']
			: ( isset( $data['id'] ) ? (string) $data['id'] : '' );

		Delopay_Log::info(
			sprintf( 'subscription/invoice event "%s"%s', $type, '' !== $ref ? ' (' . $ref . ')' : '' )
		);

		/**
		 * Fires when a subscription or invoice webhook is received.
		 *
		 * @param string $type Full event type.
		 * @param array  $data Event object.
		 */
		do_action( 'delopay_subscription_event', $type, $data );
	}
}

add_action( 'rest_api_init', array( 'Delopay_Webhook', 'rest_route' ) );
