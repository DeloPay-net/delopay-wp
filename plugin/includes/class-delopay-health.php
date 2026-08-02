<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the stored API key actually works, as opposed to merely existing.
 *
 * A revoked or mistyped key fails every payment while the admin still shows a
 * saved key, so "is a key set?" is the wrong question. Two things answer the
 * right one:
 *
 * 1. A background probe (hourly cron) that makes one cheap authenticated call.
 * 2. Every real API call — `Delopay_Client` reports the HTTP status it got, so
 *    a key revoked five minutes ago is marked bad on the next checkout instead
 *    of at the next cron tick.
 *
 * The verdict is stored against a fingerprint of the key it was reached for, so
 * saving a different key resets the state instead of inheriting the old one's.
 */
class Delopay_Health {

	const OPTION = 'delopay_connection_health';

	const CHECK_HOOK     = 'delopay_health_check';
	const CHECK_SCHEDULE = 'hourly';
	const CHECK_DELAY    = 300;

	/** No key stored — the site was never connected. */
	const STATE_NOT_CONNECTED = 'not_connected';
	/** A key is stored but nothing has confirmed it yet. */
	const STATE_UNKNOWN = 'unknown';
	/** The last authenticated call was accepted. */
	const STATE_OK = 'ok';
	/** DeloPay rejected the key (401/403). Payments are failing right now. */
	const STATE_INVALID = 'invalid';
	/** The API could not be reached at all — a network fault, not a key fault. */
	const STATE_UNREACHABLE = 'unreachable';

	/**
	 * How long a recorded verdict is left alone before a same-verdict API call
	 * refreshes its timestamp. Without this every checkout would write an
	 * option row just to say "still fine".
	 */
	const REFRESH_AFTER = 900;

	/**
	 * Register the cron worker and the credential-change listener. Called once
	 * from Delopay_Plugin.
	 */
	public static function hooks(): void {
		add_action( self::CHECK_HOOK, array( __CLASS__, 'run_scheduled_check' ) );
		add_action( 'update_option_' . Delopay_Settings::OPTION_KEY, array( __CLASS__, 'on_credentials_saved' ) );
	}

	/**
	 * Cron entry point. Wraps check_now() because an action callback must not
	 * return a value.
	 */
	public static function run_scheduled_check(): void {
		self::check_now();
	}

	/**
	 * A saved settings form can carry a new API key (the "paste credentials
	 * manually" path). Drop the old verdict and get a fresh one soon rather
	 * than waiting up to an hour for the next cron tick.
	 */
	public static function on_credentials_saved(): void {
		$api_key = (string) Delopay_Settings::get( 'api_key' );
		$stored  = self::stored();

		if ( is_array( $stored ) && '' !== $api_key && ( $stored['fingerprint'] ?? '' ) === self::fingerprint( $api_key ) ) {
			return;
		}

		self::reset();
		if ( '' !== $api_key ) {
			wp_schedule_single_event( time() + 10, self::CHECK_HOOK );
		}
	}

	/**
	 * Current connection state.
	 *
	 * @return string One of the STATE_* constants.
	 */
	public static function state(): string {
		$api_key = (string) Delopay_Settings::get( 'api_key' );
		if ( '' === $api_key ) {
			return self::STATE_NOT_CONNECTED;
		}

		$stored = self::stored();
		// A verdict reached for a different key says nothing about this one.
		if ( ! is_array( $stored ) || ( $stored['fingerprint'] ?? '' ) !== self::fingerprint( $api_key ) ) {
			return self::STATE_UNKNOWN;
		}

		$state = (string) ( $stored['state'] ?? self::STATE_UNKNOWN );
		$known = array( self::STATE_OK, self::STATE_INVALID, self::STATE_UNREACHABLE, self::STATE_UNKNOWN );
		return in_array( $state, $known, true ) ? $state : self::STATE_UNKNOWN;
	}

	/**
	 * True when DeloPay is actively rejecting this site's key — the one state
	 * that warrants shouting at the merchant on every admin screen.
	 */
	public static function is_invalid(): bool {
		return self::STATE_INVALID === self::state();
	}

	/**
	 * The full stored record (state, checked_at, status, message), or an empty
	 * array when nothing has been recorded for the current key.
	 *
	 * @return array<string, mixed>
	 */
	public static function details(): array {
		$stored  = self::stored();
		$api_key = (string) Delopay_Settings::get( 'api_key' );
		if ( ! is_array( $stored ) || '' === $api_key || ( $stored['fingerprint'] ?? '' ) !== self::fingerprint( $api_key ) ) {
			return array();
		}
		return $stored;
	}

	/**
	 * When the current verdict was reached, as a unix timestamp (0 if never).
	 */
	public static function checked_at(): int {
		$details = self::details();
		return (int) ( $details['checked_at'] ?? 0 );
	}

	/**
	 * The API's own words for why the key was rejected, for the admin notice.
	 */
	public static function message(): string {
		$details = self::details();
		return (string) ( $details['message'] ?? '' );
	}

	/**
	 * Human label for a state, used by the dashboard checklist and settings card.
	 *
	 * @param string $state One of the STATE_* constants.
	 */
	public static function label( $state ): string {
		switch ( $state ) {
			case self::STATE_OK:
				return __( 'Connected', 'delopay' );
			case self::STATE_INVALID:
				return __( 'API key rejected', 'delopay' );
			case self::STATE_UNREACHABLE:
				return __( 'DeloPay unreachable', 'delopay' );
			case self::STATE_NOT_CONNECTED:
				return __( 'Not connected', 'delopay' );
			default:
				return __( 'Not verified yet', 'delopay' );
		}
	}

	/**
	 * Marker shown in front of the dashboard checklist row.
	 *
	 * @param string $state One of the STATE_* constants.
	 */
	public static function icon( $state ): string {
		if ( self::STATE_OK === $state ) {
			return '✅';
		}
		if ( self::STATE_INVALID === $state ) {
			return '❌';
		}
		return '⚠️';
	}

	/**
	 * Record what an authenticated API call actually got back.
	 *
	 * Only called for requests made with the *stored* credentials — a probe
	 * against credentials the connect flow just received says nothing about
	 * what this site is configured with.
	 *
	 * @param int    $status  HTTP status, or 0 when the request never completed.
	 * @param string $message Error message to show the merchant, if any.
	 */
	public static function record_status( $status, $message = '' ): void {
		$status = (int) $status;

		if ( 401 === $status || 403 === $status ) {
			self::store( self::STATE_INVALID, $status, (string) $message );
			return;
		}
		if ( $status >= 200 && $status < 300 ) {
			self::store( self::STATE_OK, $status, '' );
			return;
		}
		if ( $status >= 500 ) {
			// DeloPay's own fault. It proves nothing either way about our
			// credentials, so leave the verdict exactly as it was — in
			// particular, a 500 must not promote a known-invalid key back to
			// "connected" and silence the notice the merchant needs.
			return;
		}
		if ( 0 === $status ) {
			// Transport failure (DNS, TLS, timeout). Also says nothing about the
			// key, but "we cannot reach DeloPay at all" is worth showing. Never
			// let it soften a known-invalid verdict.
			if ( self::STATE_INVALID !== self::state() ) {
				self::store( self::STATE_UNREACHABLE, 0, (string) $message );
			}
			return;
		}
		// Any other 4xx (400, 404, 422, 429 …): the API authenticated us and
		// then applied its own business rules, so the connection itself is fine.
		self::store( self::STATE_OK, $status, '' );
	}

	/**
	 * Run the probe now and store the verdict. Wired to the hourly cron and to
	 * the "Check connection" button on the Logs screen.
	 *
	 * @return string The resulting state.
	 */
	public static function check_now(): string {
		$api_key = (string) Delopay_Settings::get( 'api_key' );
		if ( '' === $api_key ) {
			self::reset();
			return self::STATE_NOT_CONNECTED;
		}

		$client = new Delopay_Client();
		if ( ! $client->is_ready() ) {
			return self::STATE_NOT_CONNECTED;
		}

		$before = self::state();

		// probe_credentials() goes through Delopay_Client::request(), which
		// reports every outcome to record_status() — including the transport
		// failures the probe itself reports as "ok" because they say nothing
		// about the key. So read the verdict back rather than re-deriving it.
		$probe = $client->probe_credentials();
		$state = self::state();

		// Force a write so "checked N minutes ago" reflects this run even when
		// the verdict is unchanged.
		$message = self::STATE_OK === $state ? '' : (string) ( $probe['message'] ?? '' );
		self::store( $state, (int) ( $probe['status'] ?? 0 ), $message, true );

		// Logged on transition only: an hourly "still broken" line would bury
		// everything else in the log the merchant is being sent to read.
		if ( $state !== $before ) {
			self::log_transition( $before, $state, $probe );
		}

		return $state;
	}

	/**
	 * Record a change of connection state in the merchant-visible log.
	 *
	 * @param string               $before Previous state.
	 * @param string               $after  New state.
	 * @param array<string, mixed> $probe  Raw probe result.
	 */
	private static function log_transition( $before, $after, array $probe ): void {
		$context = array(
			'previous' => $before,
			'status'   => $probe['status'] ?? 0,
			'message'  => $probe['message'] ?? '',
		);

		if ( self::STATE_INVALID === $after ) {
			Delopay_Log::error( 'connection check: DeloPay rejected the stored API key', $context );
			return;
		}
		if ( self::STATE_UNREACHABLE === $after ) {
			Delopay_Log::warning( 'connection check: could not reach DeloPay', $context );
			return;
		}
		if ( self::STATE_OK === $after && self::STATE_UNKNOWN !== $before ) {
			Delopay_Log::info( 'connection check: connection recovered', $context );
		}
	}

	/**
	 * Forget the recorded verdict — used when credentials change under us, so
	 * the next probe starts from scratch rather than inheriting a stale ❌.
	 */
	public static function reset(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Write a verdict, but only when it carries new information: the state
	 * changed, the key changed, or the last write is old enough that the
	 * "checked X ago" line would be misleading.
	 *
	 * @param string $state   One of the STATE_* constants.
	 * @param int    $status  HTTP status behind the verdict.
	 * @param string $message Error message, if any.
	 * @param bool   $force   Always write (explicit checks).
	 */
	private static function store( $state, $status, $message, $force = false ): void {
		$api_key = (string) Delopay_Settings::get( 'api_key' );
		if ( '' === $api_key ) {
			return;
		}
		$fingerprint = self::fingerprint( $api_key );
		$stored      = self::stored();

		if ( ! $force && is_array( $stored )
			&& ( $stored['state'] ?? '' ) === $state
			&& ( $stored['fingerprint'] ?? '' ) === $fingerprint
			&& ( time() - (int) ( $stored['checked_at'] ?? 0 ) ) < self::REFRESH_AFTER
		) {
			return;
		}

		update_option(
			self::OPTION,
			array(
				'state'       => (string) $state,
				'status'      => (int) $status,
				'message'     => self::trim_message( $message ),
				'checked_at'  => time(),
				'fingerprint' => $fingerprint,
			),
			false
		);
	}

	/**
	 * The raw stored record, without the fingerprint check callers get from
	 * details() — used internally where the fingerprint is the thing being
	 * compared.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function stored() {
		$stored = get_option( self::OPTION, null );
		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Identify the key without storing it a second time.
	 *
	 * @param string $api_key The key in play.
	 */
	private static function fingerprint( $api_key ): string {
		return substr( hash( 'sha256', (string) $api_key ), 0, 16 );
	}

	/**
	 * Strip markup and cap the length of an API error before it is stored and
	 * shown in an admin notice.
	 *
	 * @param string $message Raw API error message.
	 */
	private static function trim_message( $message ): string {
		$message = trim( wp_strip_all_tags( (string) $message ) );
		if ( strlen( $message ) > 300 ) {
			$message = substr( $message, 0, 300 ) . '…';
		}
		return $message;
	}
}
