<?php
/**
 * Orders & refunds data layer.
 *
 * Operates directly against the plugin's own custom tables. The DB sniffs are
 * disabled file-wide because we own the schema (wp_cache_* would just shadow
 * data we already control) and $wpdb->prepare() does not accept table names
 * as placeholders, forcing {$table} interpolation.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Orders {

	const STATUS_PENDING_CREATE = 'pending_create';
	const STATUS_DEFAULT        = 'requires_payment_method';

	const LOCK_TIMEOUT_DEFAULT   = 5;
	const LOCK_TIMEOUT_RECONCILE = 2;
	const RECONCILE_AGE_SECONDS  = 300;
	const RECONCILE_BATCH_LIMIT  = 50;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public static function table_orders() {
		return self::table( 'delopay_orders' ); }
	public static function table_refunds() {
		return self::table( 'delopay_refunds' ); }
	public static function table_products() {
		return self::table( 'delopay_products' ); }
	public static function table_categories() {
		return self::table( 'delopay_categories' ); }

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	private static function now() {
		return current_time( 'mysql', true );
	}

	private static function get_row( $table, $where_sql, ...$args ) {
		global $wpdb;
		// $table comes from $wpdb->prefix . 'delopay_*' (hard-coded list); $where_sql contains placeholders bound via $args below.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$table} WHERE {$where_sql} LIMIT 1";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
	}

	public static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$orders          = self::table_orders();
		$refunds         = self::table_refunds();
		$products        = self::table_products();
		$categories      = self::table_categories();

		dbDelta(
			"CREATE TABLE {$orders} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id VARCHAR(64) NOT NULL,
			payment_id VARCHAR(128) NOT NULL,
			merchant_id VARCHAR(128) NOT NULL,
			amount_minor BIGINT NOT NULL DEFAULT 0,
			currency VARCHAR(8) NOT NULL DEFAULT 'USD',
			status VARCHAR(48) NOT NULL DEFAULT 'requires_payment_method',
			error_code VARCHAR(128) DEFAULT NULL,
			error_message TEXT DEFAULT NULL,
			line_items LONGTEXT DEFAULT NULL,
			metadata LONGTEXT DEFAULT NULL,
			return_url TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			last_webhook_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY order_id (order_id),
			KEY payment_id (payment_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$refunds} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			refund_id VARCHAR(128) NOT NULL,
			order_id VARCHAR(64) NOT NULL,
			payment_id VARCHAR(128) NOT NULL,
			amount_minor BIGINT NOT NULL DEFAULT 0,
			status VARCHAR(48) NOT NULL DEFAULT 'pending',
			reason VARCHAR(128) DEFAULT NULL,
			error_code VARCHAR(128) DEFAULT NULL,
			error_message TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY refund_id (refund_id),
			KEY order_id (order_id),
			KEY payment_id (payment_id)
		) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$products} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sku VARCHAR(64) DEFAULT NULL,
			name VARCHAR(255) NOT NULL,
			description LONGTEXT DEFAULT NULL,
			price_minor BIGINT NOT NULL DEFAULT 0,
			currency VARCHAR(8) NOT NULL DEFAULT 'USD',
			image_id BIGINT UNSIGNED DEFAULT NULL,
			image_url VARCHAR(2048) DEFAULT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'active',
			sort_order INT NOT NULL DEFAULT 0,
			category_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY sku (sku),
			KEY status (status),
			KEY sort_order (sort_order),
			KEY category_id (category_id)
		) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$categories} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(64) NOT NULL,
			name VARCHAR(255) NOT NULL,
			description LONGTEXT DEFAULT NULL,
			hero_eyebrow VARCHAR(255) DEFAULT NULL,
			hero_title VARCHAR(255) DEFAULT NULL,
			hero_subtitle TEXT DEFAULT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'active',
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY sort_order (sort_order)
		) {$charset_collate};"
		);
	}

	public static function new_order_id() {
		return 'ord_' . strtolower( wp_generate_password( 16, false, false ) );
	}

	public static function create( $data ) {
		global $wpdb;
		$row = self::build_order_row( $data, $data['status'], $data['payment_id'], $data['merchant_id'] );
		$wpdb->insert( self::table_orders(), $row );
		$row['id'] = (int) $wpdb->insert_id;
		return self::hydrate( $row );
	}

	public static function create_pending( $data ) {
		global $wpdb;
		$row = self::build_order_row( $data, self::STATUS_PENDING_CREATE, '', '' );
		if ( false === $wpdb->insert( self::table_orders(), $row ) ) {
			return null;
		}
		$row['id'] = (int) $wpdb->insert_id;
		return self::hydrate( $row );
	}

	private static function build_order_row( $data, $status, $payment_id, $merchant_id ) {
		$now = self::now();
		return array(
			'order_id'        => $data['order_id'],
			'payment_id'      => (string) $payment_id,
			'merchant_id'     => (string) $merchant_id,
			'amount_minor'    => (int) $data['amount_minor'],
			'currency'        => strtoupper( $data['currency'] ),
			'status'          => $status,
			'error_code'      => null,
			'error_message'   => null,
			'line_items'      => wp_json_encode( $data['lines'] ?? array() ),
			'metadata'        => wp_json_encode( $data['metadata'] ?? array() ),
			'return_url'      => $data['return_url'] ?? null,
			'created_at'      => $now,
			'updated_at'      => $now,
			'last_webhook_at' => null,
		);
	}

	public static function attach_payment( $data ) {
		global $wpdb;
		$table = self::table_orders();

		$current = self::get_row( $table, 'order_id = %s', $data['order_id'] );
		if ( ! $current ) {
			return null;
		}

		$update = array(
			'payment_id'  => (string) $data['payment_id'],
			'merchant_id' => (string) $data['merchant_id'],
			'updated_at'  => self::now(),
		);

		if ( ! self::is_terminal( $current['status'] ) ) {
			$update['status'] = (string) ( $data['status'] ?? self::STATUS_DEFAULT );
		}

		$wpdb->update( $table, $update, array( 'order_id' => $data['order_id'] ) );

		$row = self::get_row( $table, 'order_id = %s', $data['order_id'] );
		return $row ? self::hydrate( $row ) : null;
	}

	public static function delete_pending( $order_id ) {
		global $wpdb;
		$wpdb->delete(
			self::table_orders(),
			array(
				'order_id' => $order_id,
				'status'   => self::STATUS_PENDING_CREATE,
			)
		);
	}

	public static function find( $id_or_payment ) {
		$row = self::get_row( self::table_orders(), 'order_id = %s OR payment_id = %s LIMIT 1', $id_or_payment, $id_or_payment );
		return $row ? self::hydrate( $row ) : null;
	}

	public static function terminal_statuses() {
		return array(
			'succeeded',
			'failed',
			'cancelled',
			'expired',
			'partially_captured',
			'partially_captured_and_capturable',
		);
	}

	private static function is_terminal( $status ) {
		return in_array( (string) $status, self::terminal_statuses(), true );
	}

	public static function update_status( $payment_id, $status, $error_code = null, $error_message = null, $is_webhook = false, $reference_id = null ) {
		global $wpdb;
		$table = self::table_orders();

		$current = self::get_row( $table, 'payment_id = %s', $payment_id );

		if ( ! $current && is_string( $reference_id ) && '' !== $reference_id ) {
			$current = self::get_row( $table, "order_id = %s AND (payment_id IS NULL OR payment_id = '')", $reference_id );
		}

		if ( ! $current ) {
			return null;
		}

		if ( self::is_terminal( $current['status'] ) && (string) $current['status'] !== (string) $status ) {
			if ( $is_webhook ) {
				WP_Delopay_Log::info(
					sprintf(
						'dropping out-of-order webhook: order %s is %s, ignoring incoming %s',
						(string) $current['order_id'],
						(string) $current['status'],
						(string) $status
					)
				);
			}
			return self::hydrate( $current );
		}

		$now    = self::now();
		$update = array(
			'status'        => $status,
			'error_code'    => $error_code,
			'error_message' => $error_message,
			'updated_at'    => $now,
		);
		if ( $is_webhook ) {
			$update['last_webhook_at'] = $now;
		}
		if ( '' === (string) $current['payment_id'] && '' !== (string) $payment_id ) {
			$update['payment_id'] = (string) $payment_id;
		}

		$wpdb->update( $table, $update, array( 'id' => (int) $current['id'] ) );

		$row = self::get_row( $table, 'id = %d', (int) $current['id'] );
		return $row ? self::hydrate( $row ) : null;
	}

	public static function list( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$table = self::table_orders();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset ),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	public static function count() {
		global $wpdb;
		$table = self::table_orders();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function record_refund( $data ) {
		global $wpdb;
		$table = self::table_refunds();

		if ( empty( $data['refund_id'] ) || ! is_string( $data['refund_id'] ) ) {
			WP_Delopay_Log::warning( 'refusing to record refund without refund_id' );
			return null;
		}

		$existing = self::get_row( $table, 'refund_id = %s', $data['refund_id'] );

		$order_id = $data['order_id'] ?? '';
		if ( '' === $order_id ) {
			$order    = self::find( $data['payment_id'] );
			$order_id = $order ? $order['order_id'] : '';
		}

		$now    = self::now();
		$common = array(
			'amount_minor'  => (int) $data['amount_minor'],
			'status'        => $data['status'],
			'reason'        => $data['reason'] ?? null,
			'error_code'    => $data['error_code'] ?? null,
			'error_message' => $data['error_message'] ?? null,
			'updated_at'    => $now,
		);

		if ( $existing ) {
			$wpdb->update( $table, $common, array( 'id' => (int) $existing['id'] ) );
			$row = self::get_row( $table, 'id = %d', (int) $existing['id'] );
			return $row ? $row : null;
		}

		$wpdb->insert(
			$table,
			array_merge(
				$common,
				array(
					'refund_id'  => $data['refund_id'],
					'order_id'   => $order_id,
					'payment_id' => $data['payment_id'],
					'created_at' => $now,
				)
			)
		);

		$row = self::get_row( $table, 'id = %d', (int) $wpdb->insert_id );
		return $row ? $row : null;
	}

	public static function refunds_for( $order_id ) {
		global $wpdb;
		$table = self::table_refunds();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %s ORDER BY created_at ASC", $order_id ),
			ARRAY_A
		);
		return (array) $rows;
	}

	public static function refunded_total( $order_id ) {
		global $wpdb;
		$table = self::table_refunds();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount_minor), 0) FROM {$table} WHERE order_id = %s AND status NOT IN ('failed','failure')",
				$order_id
			)
		);
	}

	public static function pending_refunds_needing_reconcile( $age_seconds = self::RECONCILE_AGE_SECONDS, $limit = self::RECONCILE_BATCH_LIMIT ) {
		global $wpdb;
		$table  = self::table_refunds();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - (int) $age_seconds );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' AND created_at <= %s ORDER BY created_at ASC LIMIT %d",
				$cutoff,
				(int) $limit
			),
			ARRAY_A
		);
		return (array) $rows;
	}

	public static function acquire_refund_lock( $order_id, $timeout = self::LOCK_TIMEOUT_DEFAULT ) {
		global $wpdb;
		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::refund_lock_name( $order_id ), (int) $timeout ) );
		return '1' === (string) $got;
	}

	public static function release_refund_lock( $order_id ) {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::refund_lock_name( $order_id ) ) );
	}

	private static function refund_lock_name( $order_id ) {
		return 'delopay_refund_' . substr( hash( 'sha256', (string) $order_id ), 0, 32 );
	}

	public static function reconcile_pending_refunds() {
		if ( ! class_exists( 'WP_Delopay_Settings' ) || ! WP_Delopay_Settings::is_configured() ) {
			return;
		}

		$pending = self::pending_refunds_needing_reconcile();
		if ( empty( $pending ) ) {
			return;
		}

		$client = new WP_Delopay_Client();

		foreach ( $pending as $row ) {
			self::reconcile_refund_row( $row, $client );
		}
	}

	private static function reconcile_refund_row( $row, $client ) {
		$refund_id = isset( $row['refund_id'] ) ? (string) $row['refund_id'] : '';
		$order_id  = isset( $row['order_id'] ) ? (string) $row['order_id'] : '';
		if ( '' === $refund_id || '' === $order_id ) {
			return;
		}

		$remote = $client->retrieve_refund( $refund_id );
		if ( is_wp_error( $remote ) || ! is_array( $remote ) ) {
			WP_Delopay_Log::warning( 'reconcile: failed to retrieve refund ' . $refund_id );
			return;
		}

		$remote_status = isset( $remote['status'] ) ? (string) $remote['status'] : '';
		if ( '' === $remote_status || 'pending' === $remote_status ) {
			return;
		}

		if ( ! self::acquire_refund_lock( $order_id, self::LOCK_TIMEOUT_RECONCILE ) ) {
			return;
		}
		try {
			self::record_refund(
				array(
					'refund_id'     => $refund_id,
					'order_id'      => $order_id,
					'payment_id'    => isset( $row['payment_id'] ) ? (string) $row['payment_id'] : '',
					'amount_minor'  => isset( $remote['amount'] ) ? (int) $remote['amount'] : (int) $row['amount_minor'],
					'status'        => $remote_status,
					'reason'        => $remote['reason'] ?? $row['reason'] ?? null,
					'error_code'    => $remote['error_code'] ?? null,
					'error_message' => $remote['error_message'] ?? null,
				)
			);
		} finally {
			self::release_refund_lock( $order_id );
		}
	}

	private static function hydrate( $row ) {
		$row['amount_minor'] = (int) $row['amount_minor'];
		$row['lines']        = ! empty( $row['line_items'] ) ? json_decode( $row['line_items'], true ) : array();
		unset( $row['line_items'] );
		$row['metadata'] = $row['metadata'] ? json_decode( $row['metadata'], true ) : array();
		return $row;
	}
}
