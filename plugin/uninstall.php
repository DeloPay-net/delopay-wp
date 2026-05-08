<?php
/**
 * Plugin uninstall handler.
 *
 * Drops the plugin's own tables and removes its options/transients. The DB
 * sniffs are disabled file-wide because we own the schema and table names
 * cannot be passed through $wpdb->prepare() as placeholders.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wp_delopay_tables = array(
	$wpdb->prefix . 'delopay_products',
	$wpdb->prefix . 'delopay_categories',
	$wpdb->prefix . 'delopay_orders',
	$wpdb->prefix . 'delopay_refunds',
);
foreach ( $wp_delopay_tables as $wp_delopay_table ) {
	// Table names are built from $wpdb->prefix and a fixed list above, so they cannot be prepared.
	$wpdb->query( "DROP TABLE IF EXISTS {$wp_delopay_table}" );
}
unset( $wp_delopay_tables, $wp_delopay_table );

$wp_delopay_options = array(
	'wp_delopay_settings',
	'wp_delopay_db_version',
	'wp_delopay_seen_events',
	'wp_delopay_connect_merchant_id',
	'wp_delopay_connect_api_key_id',
	'wp_delopay_connect_connected_at',
);
foreach ( $wp_delopay_options as $wp_delopay_option_name ) {
	delete_option( $wp_delopay_option_name );
}
unset( $wp_delopay_options, $wp_delopay_option_name );

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_delopay_connect_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_delopay_connect_' ) . '%'
	)
);
