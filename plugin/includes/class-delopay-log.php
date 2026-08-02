<?php
/**
 * Plugin log — the merchant-visible one.
 *
 * Two sinks, deliberately independent:
 *
 * - The database table (`delopay_logs`), which takes *every* level and is what
 *   `DeloPay → Logs` reads. A merchant on shared hosting has no way to read
 *   `error_log()`, and asking them to get server access to find out why
 *   checkout is broken is the failure this table exists to prevent.
 * - `error_log()`, unchanged: errors always, info/warning only under
 *   WP_DEBUG_LOG. That gate is about not filling a shared PHP error log with
 *   chatter; it was never meant to decide what the admin screen may show.
 *
 * Context arrays go through redact() before either sink sees them.
 *
 * The DB sniffs are disabled file-wide for the same reason as the other
 * data-layer files: the plugin owns this table, so wp_cache_* would shadow
 * data we control, and $wpdb->prepare() cannot bind a table name. Every query
 * below interpolates exactly one thing — that table name, built from
 * $wpdb->prefix and a fixed suffix — and binds everything else.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Log {

	const PREFIX = '[delopay] ';

	const LEVEL_INFO    = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	const PRUNE_HOOK     = 'delopay_prune_logs';
	const PRUNE_SCHEDULE = 'daily';
	const PRUNE_DELAY    = 3600;

	/** Entries older than this are dropped even if the table is well under the row cap. */
	const RETENTION_DAYS = 7;
	/** Hard cap on stored rows, so a wedged integration cannot fill the database. */
	const RETENTION_MAX_ROWS = 500;

	const MESSAGE_MAX_CHARS = 500;
	const CONTEXT_MAX_CHARS = 4000;

	/** Default window for throttled entries from endpoints the public can hit. */
	const THROTTLE_TTL = 300;

	private static $redact_keys = array(
		'api_key',
		'api-key',
		'webhook_secret',
		'authorization',
		'x-webhook-signature-512',
		'x_webhook_signature_512',
		'signature',
		'password',
		'secret',
		'token',
	);

	/**
	 * Register the retention cron worker. Called once from Delopay_Plugin.
	 */
	public static function hooks(): void {
		add_action( self::PRUNE_HOOK, array( __CLASS__, 'prune' ) );
	}

	/**
	 * Levels in severity order, for the admin filter.
	 *
	 * @return string[]
	 */
	public static function levels(): array {
		return array( self::LEVEL_ERROR, self::LEVEL_WARNING, self::LEVEL_INFO );
	}

	public static function info( $message, $context = array() ) {
		self::write( self::LEVEL_INFO, $message, $context );
	}

	public static function warning( $message, $context = array() ) {
		self::write( self::LEVEL_WARNING, $message, $context );
	}

	public static function error( $message, $context = array() ) {
		self::write( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log at most one entry per $key per $ttl seconds.
	 *
	 * For paths an unauthenticated caller can trigger at will — the webhook
	 * receiver above all. Without this, anyone who knows the endpoint could
	 * push every real entry out of a 500-row table by replaying garbage.
	 *
	 * @param string               $level   One of the LEVEL_* constants.
	 * @param string               $key     Throttle bucket; distinct causes need distinct keys.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context payload.
	 * @param int                  $ttl     Seconds to suppress repeats for.
	 */
	public static function throttled( $level, $key, $message, $context = array(), $ttl = self::THROTTLE_TTL ): void {
		$transient = 'delopay_log_t_' . md5( (string) $level . '|' . (string) $key );
		if ( false !== get_transient( $transient ) ) {
			return;
		}
		set_transient( $transient, 1, max( 1, (int) $ttl ) );
		self::write( (string) $level, $message, $context );
	}

	private static function write( $level, $message, $context ) {
		$redacted = self::redact( $context );

		self::persist( $level, $message, $redacted );

		if ( 'error' !== $level && ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) ) {
			return;
		}

		$line = self::PREFIX . strtoupper( $level ) . ': ' . (string) $message;
		if ( ! empty( $redacted ) ) {
			$encoded = wp_json_encode( $redacted );
			if ( false !== $encoded ) {
				$line .= ' ' . $encoded;
			}
		}
		// This file IS the logger — error_log is the intended sink, gated behind WP_DEBUG_LOG above for non-error levels.
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Append to the admin-visible table.
	 *
	 * Silent on failure by design: a logger that throws or warns while
	 * recording a payment error turns one problem into two.
	 *
	 * @param string $level    One of the LEVEL_* constants.
	 * @param string $message  Log message.
	 * @param mixed  $redacted Context, already through redact().
	 */
	private static function persist( $level, $message, $redacted ): void {
		if ( ! Delopay_Orders::has_logs_table() ) {
			return;
		}

		global $wpdb;

		$context = '';
		if ( ! empty( $redacted ) ) {
			$encoded = wp_json_encode( $redacted );
			$context = false === $encoded ? '' : self::clamp( $encoded, self::CONTEXT_MAX_CHARS );
		}

		$wpdb->insert(
			Delopay_Orders::table_logs(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'level'      => in_array( $level, self::levels(), true ) ? $level : self::LEVEL_INFO,
				'message'    => self::clamp( (string) $message, self::MESSAGE_MAX_CHARS ),
				'context'    => $context,
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Read a page of entries for the admin screen, newest first.
	 *
	 * @param array{level?: string, per_page?: int, page?: int} $args Query args.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args = array() ): array {
		$empty = array(
			'rows'  => array(),
			'total' => 0,
		);
		if ( ! Delopay_Orders::has_logs_table() ) {
			return $empty;
		}

		global $wpdb;
		$table = Delopay_Orders::table_logs();

		$level    = isset( $args['level'] ) ? (string) $args['level'] : '';
		$level    = in_array( $level, self::levels(), true ) ? $level : '';
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		if ( '' !== $level ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE level = %s", $level ) );
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE level = %s ORDER BY id DESC LIMIT %d OFFSET %d",
					$level,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$rows  = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
				ARRAY_A
			);
		}

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Drop entries past the retention window, then past the row cap.
	 * Wired to a daily cron; also safe to call directly.
	 */
	public static function prune(): void {
		if ( ! Delopay_Orders::has_logs_table() ) {
			return;
		}

		global $wpdb;
		$table = Delopay_Orders::table_logs();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS ) )
			)
		);

		// Ids are monotonic, so the id of the newest row to keep is the cutoff.
		$cutoff_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d", self::RETENTION_MAX_ROWS )
		);
		if ( null !== $cutoff_id ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id <= %d", (int) $cutoff_id ) );
		}
	}

	/**
	 * Empty the admin-visible log. Does not touch error_log output.
	 */
	public static function clear(): void {
		if ( ! Delopay_Orders::has_logs_table() ) {
			return;
		}
		global $wpdb;
		$table = Delopay_Orders::table_logs();
		// Table name comes from $wpdb->prefix and a fixed suffix; DELETE (not TRUNCATE) so the id sequence keeps advancing.
		$wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Shorten a value to fit its column, marking the cut with an ellipsis.
	 *
	 * @param string $value Text to shorten.
	 * @param int    $max   Maximum length in characters.
	 */
	private static function clamp( $value, $max ): string {
		$value = (string) $value;
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $value ) > $max ? mb_substr( $value, 0, $max ) . '…' : $value;
		}
		return strlen( $value ) > $max ? substr( $value, 0, $max ) . '…' : $value;
	}

	public static function redact( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $k => $v ) {
			$key_lc = is_string( $k ) ? strtolower( $k ) : $k;
			if ( is_string( $key_lc ) && in_array( $key_lc, self::$redact_keys, true ) ) {
				$out[ $k ] = '[redacted]';
				continue;
			}
			$out[ $k ] = is_array( $v ) ? self::redact( $v ) : $v;
		}
		return $out;
	}
}
