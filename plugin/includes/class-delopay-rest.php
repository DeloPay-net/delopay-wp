<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_REST {

	const REST_NS = 'delopay/v1';

	const MAX_QUANTITY_PER_LINE = 999;
	const MAX_LINES_PER_ORDER   = 100;
	const MAX_AMOUNT_MINOR      = 10000000000;
	const PUBLIC_LIST_LIMIT     = 200;
	const DEFAULT_REFUND_REASON = 'requested_by_customer';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	public function register() {
		foreach ( $this->routes() as $route ) {
			register_rest_route( self::REST_NS, $route['path'], $route['args'] );
		}
	}

	private function routes() {
		$first_party = array( $this, 'require_first_party' );
		$admin       = array( $this, 'require_admin' );

		return array(
			array(
				'path' => '/orders',
				'args' => $this->route(
					'POST',
					'create_order',
					$first_party,
					array(
						'product_id' => false,
						'sku'        => false,
						'quantity'   => false,
						'items'      => false,
						'return_url' => false,
					)
				),
			),
			array(
				'path' => '/orders/(?P<id>[A-Za-z0-9_\-]+)',
				'args' => $this->route( 'GET', 'get_order', $first_party ),
			),
			array(
				'path' => '/products',
				'args' => $this->route(
					'GET',
					'list_products',
					$first_party,
					array(
						'ids'      => false,
						'category' => false,
					)
				),
			),
			array(
				'path' => '/categories',
				'args' => $this->route( 'GET', 'list_categories', $first_party ),
			),
			array(
				'path' => '/admin/refund',
				'args' => $this->route(
					'POST',
					'admin_refund',
					$admin,
					array(
						'order_id'     => true,
						'amount_minor' => false,
						'reason'       => false,
					)
				),
			),
		);
	}

	private function route( $verb, $callback, $permission, array $args = array() ) {
		$methods = 'POST' === $verb ? WP_REST_Server::CREATABLE : WP_REST_Server::READABLE;
		$shaped  = array();
		foreach ( $args as $name => $required ) {
			$shaped[ $name ] = array( 'required' => (bool) $required );
		}
		return array(
			'methods'             => $methods,
			'callback'            => array( $this, $callback ),
			'permission_callback' => $permission,
			'args'                => $shaped,
		);
	}

	public function require_admin() {
		return current_user_can( 'manage_options' );
	}

	public function require_first_party( WP_REST_Request $request ) {
		$header_nonce = $request->get_header( 'x-wp-nonce' );
		$nonce        = $header_nonce ? $header_nonce : $request->get_param( '_wpnonce' );
		if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}

		$home = wp_parse_url( home_url() );
		if ( ! is_array( $home ) || empty( $home['host'] ) ) {
			return $this->forbidden( __( 'Origin check failed.', 'wp-delopay' ) );
		}
		$expected_host = strtolower( $home['host'] );

		foreach ( array( 'origin', 'referer' ) as $hdr ) {
			$value = $request->get_header( $hdr );
			if ( ! $value ) {
				continue;
			}
			$parsed = wp_parse_url( $value );
			if ( ! empty( $parsed['host'] ) && strtolower( $parsed['host'] ) === $expected_host ) {
				return true;
			}
		}

		return $this->forbidden( __( 'This endpoint requires a same-origin request with a valid REST nonce.', 'wp-delopay' ) );
	}

	private function forbidden( $message ) {
		return new WP_Error( 'rest_forbidden', $message, array( 'status' => 403 ) );
	}

	private static function error_response( $message, $status, array $extra = array() ) {
		return new WP_REST_Response( array_merge( array( 'error' => $message ), $extra ), $status );
	}

	private function safe_return_url( $candidate ) {
		if ( ! is_string( $candidate ) || '' === trim( $candidate ) ) {
			return null;
		}
		$parsed = wp_parse_url( $candidate );
		if ( ! is_array( $parsed ) ) {
			return null;
		}
		if ( isset( $parsed['scheme'] ) && ! in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			return null;
		}
		$validated = wp_validate_redirect( esc_url_raw( $candidate ), '' );
		return '' === $validated ? null : $validated;
	}

	public function list_products( WP_REST_Request $request ) {
		$ids_param = $request->get_param( 'ids' );
		if ( '' !== (string) $ids_param ) {
			$ids = array_filter( array_map( 'absint', explode( ',', (string) $ids_param ) ) );
			$out = array();
			foreach ( $ids as $id ) {
				$p = WP_Delopay_Products::find( $id );
				if ( $p ) {
					$out[] = $this->shape_product( $p );
				}
			}
			return new WP_REST_Response( $out, 200 );
		}

		$category = $request->get_param( 'category' );
		$category = is_string( $category ) && '' !== $category ? $category : null;
		$products = WP_Delopay_Products::list_published( self::PUBLIC_LIST_LIMIT, $category );
		return new WP_REST_Response( array_map( array( $this, 'shape_product' ), $products ), 200 );
	}

	public function list_categories( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WP REST callback signature.
		unset( $request );
		$categories = WP_Delopay_Categories::list_published();
		$counts     = WP_Delopay_Categories::product_counts();
		$out        = array_map(
			static function ( $c ) use ( $counts ) {
				return self::shape_category( $c, $counts );
			},
			$categories
		);
		return new WP_REST_Response( $out, 200 );
	}

	private static function shape_category( $c, array $counts ) {
		return array(
			'id'            => (int) $c['id'],
			'slug'          => (string) $c['slug'],
			'name'          => (string) $c['name'],
			'description'   => (string) $c['description'],
			'sort_order'    => (int) $c['sort_order'],
			'product_count' => isset( $counts[ (int) $c['id'] ] ) ? (int) $counts[ (int) $c['id'] ] : 0,
		);
	}

	private function shape_product( $product ) {
		return array(
			'id'            => (int) $product['id'],
			'sku'           => (string) $product['sku'],
			'name'          => (string) $product['name'],
			'price_minor'   => (int) $product['price_minor'],
			'currency'      => (string) $product['currency'],
			'image_url'     => (string) $product['image_url'],
			'category_slug' => (string) ( $product['category_slug'] ?? '' ),
			'category_name' => (string) ( $product['category_name'] ?? '' ),
		);
	}

	public function create_order( WP_REST_Request $request ) {
		if ( ! WP_Delopay_Settings::is_configured() ) {
			return self::error_response(
				__( 'DeloPay is not connected. Open DeloPay → Settings and click "Connect to DeloPay".', 'wp-delopay' ),
				503
			);
		}

		$validated = $this->resolve_and_validate_lines( $request );
		if ( $validated instanceof WP_REST_Response ) {
			return $validated;
		}

		$order_id    = WP_Delopay_Orders::new_order_id();
		$safe_return = $this->safe_return_url( $request->get_param( 'return_url' ) );
		$return_url  = $safe_return ? $safe_return : WP_Delopay_Settings::get_complete_url();
		$return_url  = add_query_arg( 'order_id', $order_id, $return_url );

		$placeholder = WP_Delopay_Orders::create_pending(
			array(
				'order_id'     => $order_id,
				'amount_minor' => $validated['amount_minor'],
				'currency'     => $validated['currency'],
				'lines'        => $validated['lines'],
				'metadata'     => array(
					'order_id' => $order_id,
					'site_url' => home_url( '/' ),
				),
				'return_url'   => $return_url,
			)
		);
		if ( ! $placeholder ) {
			WP_Delopay_Log::error( 'failed to insert local order placeholder', array( 'order_id' => $order_id ) );
			return self::error_response( __( 'failed to create local order', 'wp-delopay' ), 500 );
		}

		$payment = $this->create_remote_payment( $order_id, $validated, $return_url );
		if ( $payment instanceof WP_REST_Response ) {
			WP_Delopay_Orders::delete_pending( $order_id );
			return $payment;
		}

		$order = WP_Delopay_Orders::attach_payment(
			array(
				'order_id'    => $order_id,
				'payment_id'  => $payment['payment_id'],
				'merchant_id' => $payment['merchant_id'],
				'status'      => $payment['status'] ?? WP_Delopay_Orders::STATUS_DEFAULT,
			)
		);
		if ( ! $order ) {
			WP_Delopay_Log::error( 'attach_payment returned null', array( 'order_id' => $order_id ) );
			return self::error_response( __( 'failed to finalize local order', 'wp-delopay' ), 500 );
		}

		return new WP_REST_Response( $this->shape_order_response( $order, $return_url ), 200 );
	}

	private function shape_order_response( $order, $return_url ) {
		return array(
			'order_id'     => $order['order_id'],
			'payment_id'   => $order['payment_id'],
			'merchant_id'  => $order['merchant_id'],
			'amount_minor' => $order['amount_minor'],
			'currency'     => $order['currency'],
			'status'       => $order['status'],
			'checkout_url' => $this->build_checkout_url( $order ),
			'return_url'   => $return_url,
		);
	}

	private function resolve_and_validate_lines( WP_REST_Request $request ) {
		$items_input = $this->normalize_items_input( $request );
		if ( $items_input instanceof WP_REST_Response ) {
			return $items_input;
		}
		return $this->validate_lines( $items_input );
	}

	private function normalize_items_input( WP_REST_Request $request ) {
		$items = $request->get_param( 'items' );
		if ( is_array( $items ) && ! empty( $items ) ) {
			return $items;
		}

		$product_id_param = $request->get_param( 'product_id' );
		$ref              = $product_id_param ? $product_id_param : $request->get_param( 'sku' );
		if ( ! $ref ) {
			return self::error_response( __( 'product_id (or sku) required', 'wp-delopay' ), 400 );
		}
		$quantity_param = $request->get_param( 'quantity' );
		return array(
			array(
				'product_id' => $ref,
				'quantity'   => (int) ( $quantity_param ? $quantity_param : 1 ),
			),
		);
	}

	private function validate_lines( array $items_input ) {
		if ( count( $items_input ) > self::MAX_LINES_PER_ORDER ) {
			return self::error_response(
				/* translators: %d: maximum allowed number of line items per order. */
				sprintf( __( 'too many line items (max %d)', 'wp-delopay' ), self::MAX_LINES_PER_ORDER ),
				422
			);
		}

		$lines    = array();
		$currency = null;

		foreach ( $items_input as $it ) {
			$line = $this->validate_one_line( $it, $currency );
			if ( $line instanceof WP_REST_Response ) {
				return $line;
			}
			$currency = $line['_currency'];
			unset( $line['_currency'] );
			$lines[] = $line;
		}

		$amount_minor = array_sum(
			array_map(
				static function ( $l ) {
					return $l['unit_price_minor'] * $l['quantity']; },
				$lines
			)
		);

		if ( $amount_minor <= 0 ) {
			return self::error_response( __( 'order amount must be greater than zero', 'wp-delopay' ), 400 );
		}
		if ( $amount_minor > self::MAX_AMOUNT_MINOR ) {
			return self::error_response( __( 'order amount exceeds the per-order maximum', 'wp-delopay' ), 422 );
		}

		return array(
			'lines'        => $lines,
			'currency'     => $currency,
			'amount_minor' => $amount_minor,
		);
	}

	private function validate_one_line( $it, $current_currency ) {
		$ref = $it['product_id'] ?? ( $it['sku'] ?? null );
		$qty = (int) ( $it['quantity'] ?? 1 );

		if ( ! $ref || $qty < 1 ) {
			return self::error_response( __( 'invalid line item', 'wp-delopay' ), 400 );
		}
		if ( $qty > self::MAX_QUANTITY_PER_LINE ) {
			return self::error_response(
				/* translators: %d: maximum allowed quantity per line item. */
				sprintf( __( 'quantity exceeds max of %d', 'wp-delopay' ), self::MAX_QUANTITY_PER_LINE ),
				422
			);
		}

		$product = WP_Delopay_Products::find( $ref );
		if ( ! $product ) {
			return self::error_response(
				/* translators: %s: the product reference (id or sku) that wasn't found. */
				sprintf( __( 'unknown product: %s', 'wp-delopay' ), (string) $ref ),
				400
			);
		}

		if ( null !== $current_currency && strtoupper( (string) $current_currency ) !== strtoupper( (string) $product['currency'] ) ) {
			return self::error_response( __( 'cannot mix currencies in a single order', 'wp-delopay' ), 400 );
		}

		return array(
			'product_id'       => $product['id'],
			'product_name'     => $product['name'],
			'quantity'         => $qty,
			'unit_price_minor' => (int) $product['price_minor'],
			'_currency'        => $product['currency'],
		);
	}

	private function create_remote_payment( $order_id, array $validated, $return_url ) {
		$client  = new WP_Delopay_Client();
		$payment = $client->create_payment(
			$this->build_create_payment_params( $order_id, $validated, $return_url )
		);

		if ( is_wp_error( $payment ) ) {
			WP_Delopay_Log::error(
				'create_payment failed',
				array(
					'order_id' => $order_id,
					'message'  => $payment->get_error_message(),
					'detail'   => $payment->get_error_data(),
				)
			);
			return self::error_response(
				self::admin_error_message(
					__( 'Could not start payment. Please try again.', 'wp-delopay' ),
					$payment->get_error_message()
				),
				502
			);
		}

		if ( empty( $payment['payment_id'] ) || empty( $payment['merchant_id'] ) ) {
			WP_Delopay_Log::error(
				'create_payment response missing ids',
				array(
					'order_id' => $order_id,
					'response' => $payment,
				)
			);
			return self::error_response( __( 'DeloPay response missing payment_id/merchant_id', 'wp-delopay' ), 502 );
		}

		return $payment;
	}

	/**
	 * Visitors see the generic message; admins see the underlying API
	 * error so they can debug a misconfigured connection.
	 */
	private static function admin_error_message( $public_message, $detail ) {
		$detail = trim( (string) $detail );
		if ( '' === $detail || ! current_user_can( 'manage_options' ) ) {
			return $public_message;
		}
		/* translators: 1: generic message, 2: underlying API error */
		return sprintf( __( '%1$s (admin: %2$s)', 'wp-delopay' ), $public_message, $detail );
	}

	private function build_create_payment_params( $order_id, array $validated, $return_url ) {
		$order_details = array_map(
			static function ( $l ) {
				return array(
					'product_name' => $l['product_name'],
					'quantity'     => $l['quantity'],
					'amount'       => $l['unit_price_minor'],
				);
			},
			$validated['lines']
		);

		return array(
			'amount'                      => $validated['amount_minor'],
			'currency'                    => $validated['currency'],
			'confirm'                     => false,
			'capture_method'              => 'automatic',
			'return_url'                  => $return_url,
			'description'                 => sprintf(
				/* translators: 1: order id, 2: site name */
				__( 'Order %1$s on %2$s', 'wp-delopay' ),
				$order_id,
				get_bloginfo( 'name' )
			),
			'payment_link'                => true,
			'order_details'               => $order_details,
			'metadata'                    => array(
				'order_id' => $order_id,
				'site_url' => home_url( '/' ),
			),
			'merchant_order_reference_id' => $order_id,
		);
	}

	public function get_order( WP_REST_Request $request ) {
		$id    = $request['id'];
		$order = WP_Delopay_Orders::find( $id );
		if ( ! $order ) {
			return self::error_response( __( 'order not found', 'wp-delopay' ), 404 );
		}

		$order = $this->maybe_refresh_order_status( $order );

		return new WP_REST_Response( $this->shape_order_status( $order ), 200 );
	}

	private function shape_order_status( $order ) {
		return array(
			'order_id'        => $order['order_id'],
			'payment_id'      => $order['payment_id'],
			'merchant_id'     => $order['merchant_id'],
			'status'          => $order['status'],
			'amount_minor'    => $order['amount_minor'],
			'currency'        => $order['currency'],
			'last_webhook_at' => $order['last_webhook_at'],
			'error_code'      => $order['error_code'],
			'error_message'   => $order['error_message'],
			'refunded_minor'  => WP_Delopay_Orders::refunded_total( $order['order_id'] ),
		);
	}

	private function maybe_refresh_order_status( $order ) {
		if ( in_array( $order['status'], WP_Delopay_Orders::terminal_statuses(), true ) ) {
			return $order;
		}

		$client = new WP_Delopay_Client();
		$remote = $client->retrieve_payment( $order['payment_id'] );
		if ( is_wp_error( $remote ) || ! isset( $remote['status'] ) || $remote['status'] === $order['status'] ) {
			return $order;
		}

		$updated = WP_Delopay_Orders::update_status(
			$order['payment_id'],
			$remote['status'],
			$remote['error_code'] ?? null,
			$remote['error_message'] ?? null,
			false
		);
		return $updated ? $updated : $order;
	}

	public function admin_refund( WP_REST_Request $request ) {
		$order_id = (string) $request->get_param( 'order_id' );
		$order    = WP_Delopay_Orders::find( $order_id );
		if ( ! $order ) {
			return self::error_response( __( 'order not found', 'wp-delopay' ), 404 );
		}

		$requested = $request->get_param( 'amount_minor' );
		if ( null !== $requested && ( ! is_numeric( $requested ) || $requested <= 0 ) ) {
			return self::error_response( __( 'amount_minor must be a positive number', 'wp-delopay' ), 400 );
		}

		if ( ! WP_Delopay_Orders::acquire_refund_lock( $order['order_id'] ) ) {
			return self::error_response(
				__( 'another refund is in progress for this order; try again in a moment', 'wp-delopay' ),
				409
			);
		}

		try {
			return $this->process_refund( $order, $requested, $request->get_param( 'reason' ) );
		} finally {
			WP_Delopay_Orders::release_refund_lock( $order['order_id'] );
		}
	}

	private function process_refund( $order, $requested, $reason_input ) {
		$refunded_so_far = WP_Delopay_Orders::refunded_total( $order['order_id'] );
		$remaining       = (int) $order['amount_minor'] - $refunded_so_far;
		if ( $remaining <= 0 ) {
			return self::error_response( __( 'order already fully refunded', 'wp-delopay' ), 400 );
		}

		$amount = null === $requested ? $remaining : (int) $requested;
		if ( $amount > $remaining ) {
			return self::error_response(
				/* translators: %d: refundable amount remaining on the order, in minor units. */
				sprintf( __( 'refund exceeds remaining %d', 'wp-delopay' ), $remaining ),
				400
			);
		}

		$reason = is_string( $reason_input ) && '' !== $reason_input ? $reason_input : self::DEFAULT_REFUND_REASON;

		$refund = $this->call_create_refund( $order, $amount, $reason );
		if ( $refund instanceof WP_REST_Response ) {
			return $refund;
		}

		$refund_id = isset( $refund['refund_id'] ) && is_string( $refund['refund_id'] ) ? $refund['refund_id'] : '';
		if ( '' === $refund_id ) {
			WP_Delopay_Log::error( 'DeloPay refund response missing refund_id', array( 'order_id' => $order['order_id'] ) );
			return new WP_REST_Response(
				array(
					'error'  => __( 'DeloPay accepted the refund but returned no refund_id; not recorded locally. Reconcile manually from the DeloPay dashboard.', 'wp-delopay' ),
					'detail' => $refund,
				),
				502
			);
		}

		$booked_amount = (int) ( $refund['amount'] ?? $amount );
		$over_refund   = $this->detect_over_refund( $order, $booked_amount, $refund_id, $refund );
		if ( $over_refund instanceof WP_REST_Response ) {
			return $over_refund;
		}

		WP_Delopay_Orders::record_refund(
			array(
				'refund_id'     => $refund_id,
				'order_id'      => $order['order_id'],
				'payment_id'    => $order['payment_id'],
				'amount_minor'  => $booked_amount,
				'status'        => $refund['status'] ?? 'pending',
				'reason'        => $refund['reason'] ?? $reason,
				'error_code'    => $refund['error_code'] ?? null,
				'error_message' => $refund['error_message'] ?? null,
			)
		);

		return new WP_REST_Response( $refund, 200 );
	}

	private function call_create_refund( $order, $amount, $reason ) {
		$client = new WP_Delopay_Client();
		$refund = $client->create_refund(
			array(
				'payment_id' => $order['payment_id'],
				'amount'     => $amount,
				'reason'     => $reason,
				'metadata'   => array( 'order_id' => $order['order_id'] ),
			)
		);

		if ( is_wp_error( $refund ) ) {
			return new WP_REST_Response(
				array(
					'error'  => $refund->get_error_message(),
					'detail' => $refund->get_error_data(),
				),
				502
			);
		}

		return $refund;
	}

	private function detect_over_refund( $order, $booked_amount, $refund_id, $refund_payload ) {
		$projected_total = WP_Delopay_Orders::refunded_total( $order['order_id'] ) + $booked_amount;
		if ( $projected_total <= (int) $order['amount_minor'] ) {
			return null;
		}

		WP_Delopay_Log::error(
			'post-API over-refund averted',
			array(
				'order_id'  => $order['order_id'],
				'projected' => $projected_total,
				'captured'  => (int) $order['amount_minor'],
				'refund_id' => $refund_id,
			)
		);

		return new WP_REST_Response(
			array(
				'error'  => __( 'refund created at DeloPay but would over-refund locally; reconcile manually', 'wp-delopay' ),
				'detail' => $refund_payload,
			),
			409
		);
	}

	private function build_checkout_url( $order ) {
		$base = rtrim( (string) WP_Delopay_Settings::get( 'checkout_base_url' ), '/' );
		if ( '' === $base ) {
			return '';
		}
		return $base . '/pay/' . rawurlencode( $order['merchant_id'] ) . '/' . rawurlencode( $order['payment_id'] );
	}
}
