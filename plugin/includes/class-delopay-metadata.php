<?php
/**
 * Checkout metadata attached to products and categories.
 *
 * These key/value pairs ride along on the payment DeloPay creates for an
 * order (`metadata` on the payment intent). Their reason for existing is the
 * checkout's custom-field rule builder: a merchant configures a field once on
 * the DeloPay shop and gates it on metadata, e.g. "show the Spotify login
 * fields only when `product_type` is `spotify`". The shop is what knows which
 * product is being bought, so the shop is what stamps the metadata.
 *
 * Stored as a JSON object in a LONGTEXT column on both `delopay_products` and
 * `delopay_categories`. Category metadata applies to every product in the
 * category; a product's own keys win on collision.
 *
 * Keys follow the same shape DeloPay's custom-field keys do (letter first,
 * then letters/digits/underscore/dash) so a rule can name either side
 * without surprises.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Metadata {

	const MAX_PAIRS        = 20;
	const MAX_KEY_LENGTH   = 40;
	const MAX_VALUE_LENGTH = 500;

	/**
	 * Keys the plugin derives itself on every order. Merchant-defined
	 * product/category metadata may not overwrite them — a rule that reads
	 * `category_slugs` has to be able to trust it.
	 *
	 * @var string[]
	 */
	const RESERVED_KEYS = array(
		'order_id',
		'site_url',
		'creem_product_id',
		'product_skus',
		'product_ids',
		'category_slugs',
		'item_count',
	);

	/**
	 * Pull metadata out of an admin form submission.
	 *
	 * Accepts either the repeater shape the admin pages post
	 * (`metadata_keys[]` + `metadata_values[]`) or an already-associative
	 * `metadata` array, so REST/programmatic callers work too. Rows with a
	 * blank key are dropped — that is how the UI expresses "delete this row".
	 *
	 * @param array<string, mixed> $input Raw form input.
	 * @return array<string, string> Sanitized key/value pairs.
	 */
	public static function from_input( array $input ): array {
		if ( isset( $input['metadata'] ) && is_array( $input['metadata'] ) ) {
			return self::sanitize_pairs( $input['metadata'] );
		}

		$keys   = isset( $input['metadata_keys'] ) && is_array( $input['metadata_keys'] )
			? $input['metadata_keys']
			: array();
		$values = isset( $input['metadata_values'] ) && is_array( $input['metadata_values'] )
			? $input['metadata_values']
			: array();

		$pairs = array();
		foreach ( $keys as $index => $key ) {
			$pairs[ (string) $key ] = isset( $values[ $index ] ) ? $values[ $index ] : '';
		}
		return self::sanitize_pairs( $pairs );
	}

	/**
	 * Drop unusable rows and normalize the rest.
	 *
	 * @param array<mixed, mixed> $pairs Raw key => value map.
	 * @return array<string, string>
	 */
	public static function sanitize_pairs( array $pairs ): array {
		$clean = array();
		foreach ( $pairs as $key => $value ) {
			if ( count( $clean ) >= self::MAX_PAIRS ) {
				break;
			}
			$key = self::clean_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			$clean[ $key ] = self::clean_value( $value );
		}
		return $clean;
	}

	private static function clean_key( string $key ): string {
		$key = trim( sanitize_text_field( wp_unslash( $key ) ) );
		if ( '' === $key || strlen( $key ) > self::MAX_KEY_LENGTH ) {
			return '';
		}
		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $key ) ) {
			return '';
		}
		if ( in_array( $key, self::RESERVED_KEYS, true ) ) {
			return '';
		}
		return $key;
	}

	/**
	 * Normalize one value to a bounded plain string.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function clean_value( $value ): string {
		if ( is_array( $value ) ) {
			// A list value is joined so the checkout's `contains` / `is one
			// of` operators can read it, matching how the plugin encodes its
			// own derived list keys.
			$value = implode( ',', array_map( 'strval', $value ) );
		}
		$value = trim( sanitize_text_field( wp_unslash( (string) $value ) ) );
		return substr( $value, 0, self::MAX_VALUE_LENGTH );
	}

	/**
	 * Decode a stored JSON column into a key/value map. Tolerant: anything
	 * that isn't a JSON object reads as "no metadata".
	 *
	 * @param mixed $raw Column value.
	 * @return array<string, string>
	 */
	public static function decode( $raw ): array {
		if ( is_array( $raw ) ) {
			return self::sanitize_pairs( $raw );
		}
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? self::sanitize_pairs( $decoded ) : array();
	}

	/**
	 * Encode for storage. An empty map stores as NULL rather than "{}" so
	 * "has no metadata" is one representation, not two.
	 *
	 * @param array<string, string> $pairs Sanitized pairs.
	 */
	public static function encode( array $pairs ): ?string {
		if ( empty( $pairs ) ) {
			return null;
		}
		$json = wp_json_encode( $pairs );
		return is_string( $json ) ? $json : null;
	}
}
