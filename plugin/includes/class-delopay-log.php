<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Log {

	const PREFIX = '[wp-delopay] ';

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

	public static function info( $message, $context = array() ) {
		self::write( 'info', $message, $context );
	}

	public static function warning( $message, $context = array() ) {
		self::write( 'warning', $message, $context );
	}

	public static function error( $message, $context = array() ) {
		self::write( 'error', $message, $context );
	}

	private static function write( $level, $message, $context ) {
		if ( 'error' !== $level && ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) ) {
			return;
		}

		$line = self::PREFIX . strtoupper( $level ) . ': ' . (string) $message;
		if ( ! empty( $context ) ) {
			$encoded = wp_json_encode( self::redact( $context ) );
			if ( false !== $encoded ) {
				$line .= ' ' . $encoded;
			}
		}
		// This file IS the logger — error_log is the intended sink, gated behind WP_DEBUG_LOG above for non-error levels.
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
