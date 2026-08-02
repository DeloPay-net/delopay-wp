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

$delopay_tables = array(
	$wpdb->prefix . 'delopay_products',
	$wpdb->prefix . 'delopay_categories',
	$wpdb->prefix . 'delopay_orders',
	$wpdb->prefix . 'delopay_refunds',
	$wpdb->prefix . 'delopay_logs',
);
foreach ( $delopay_tables as $delopay_table ) {
	// Table names are built from $wpdb->prefix and a fixed list above, so they cannot be prepared.
	$wpdb->query( "DROP TABLE IF EXISTS {$delopay_table}" );
}
unset( $delopay_tables, $delopay_table );

$delopay_options = array(
	'delopay_settings',
	// 'delopay_db_version' predates the current schema tracking; kept so an
	// install that still carries the old name is cleaned up too.
	'delopay_db_version',
	'delopay_schema_version',
	'delopay_seen_events',
	'delopay_connect_merchant_id',
	'delopay_connect_api_key_id',
	'delopay_connect_connected_at',
	'delopay_connection_health',
);
foreach ( $delopay_options as $delopay_option_name ) {
	delete_option( $delopay_option_name );
}
unset( $delopay_options, $delopay_option_name );

// Every transient this plugin sets is prefixed `delopay_` — connect handshake
// state and the log throttle windows.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_delopay_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_delopay_' ) . '%'
	)
);
