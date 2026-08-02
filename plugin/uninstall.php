<?php
/**
 * Plugin uninstall handler.
 *
 * Drops the plugin's own tables, removes its options/transients, and puts back
 * the WooCommerce storefront the takeover hid. The DB sniffs are disabled
 * file-wide because we own the schema and table names cannot be passed through
 * $wpdb->prepare() as placeholders.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Republish what the WooCommerce takeover hid, then drop its markers.
 *
 * Uninstalling is the merchant saying "undo everything this plugin did", and
 * leaving their products and pages drafted by a plugin that no longer exists is
 * the one outcome nothing in the UI could fix afterwards.
 *
 * Scoped to `draft`: a post the merchant has since trashed is theirs, and only
 * what is still drafted is put back.
 */
function delopay_uninstall_restore_woo_storefront(): void {
	global $wpdb;

	// The ids are read first so the caches can be invalidated afterwards. A bare
	// `UPDATE` writes straight past `clean_post_cache()`, and a site with a
	// persistent object cache would go on serving the drafted posts from cache —
	// a "restore" the merchant cannot see, with the plugin already gone.
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_status = 'draft'",
			'_delopay_woo_hidden',
			'1'
		)
	);

	if ( ! empty( $ids ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				 SET p.post_status = 'publish'
				 WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_status = 'draft'",
				'_delopay_woo_hidden',
				'1'
			)
		);

		// Primed in chunks before clearing. `clean_post_cache()` starts with
		// `get_post()`, and the ids came from a raw `get_col()` that primed
		// nothing — so without this, invalidating a 12,000-product catalog is
		// 12,000 point selects, in a request that must still get to the marker
		// and transient cleanup below.
		$delopay_id_chunks = array_chunk( array_map( 'intval', $ids ), 200 );
		foreach ( $delopay_id_chunks as $delopay_chunk ) {
			_prime_post_caches( $delopay_chunk, false, false );
			foreach ( $delopay_chunk as $delopay_restored_id ) {
				clean_post_cache( $delopay_restored_id );
			}
		}

		// Product category/tag counts are deliberately NOT recomputed here.
		// They are wrong after this restore — the publish→draft pass decremented
		// them and a raw UPDATE fires no transition to put them back — so
		// WooCommerce, which hides empty terms, may show an empty category menu
		// over a full catalog until they are rebuilt.
		//
		// Rebuilding them inline is worse than leaving them. WooCommerce
		// registers `_wc_term_recount` as the update-count callback, which costs
		// roughly five queries per term including catalog-wide `COUNT(*)`s —
		// which is why WooCommerce ships "Recount terms" as an explicit tool
		// rather than doing it on the fly. On the catalog this feature exists
		// for, that is thousands of terms in a request that must not fail: an
		// uninstall that times out here has already dropped the tables and the
		// options, `delete_plugins()` never returns, and the merchant is left
		// with no plugin UI and no way to retry. A stale count is a cosmetic
		// problem with a documented one-click fix
		// (WooCommerce → Status → Tools → Recount terms); a half-finished
		// uninstall is not.
	}

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
			'_delopay_woo_hidden'
		)
	);
}

/**
 * Remove everything this plugin owns on the current site.
 *
 * Split into a function so it can be run once per blog on multisite. Every
 * `$wpdb` property it touches — `posts`, `postmeta`, `options`, `prefix` — is
 * resolved for whichever blog is currently switched to, so the body needs no
 * per-site awareness of its own.
 */
function delopay_uninstall_site(): void {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'delopay_products',
		$wpdb->prefix . 'delopay_categories',
		$wpdb->prefix . 'delopay_orders',
		$wpdb->prefix . 'delopay_refunds',
		$wpdb->prefix . 'delopay_logs',
	);
	foreach ( $tables as $table ) {
		// Table names are built from $wpdb->prefix and a fixed list above, so they cannot be prepared.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	$options = array(
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
		// WooCommerce takeover state. These two must go, and go before the
		// storefront is restored below: `delopay_woo_redirects` is autoloaded and
		// is the only thing `maybe_redirect()` consults, so leaving it behind
		// would have a *reinstalled* plugin resume permanently redirecting the
		// old storefront — at a moment when no page carries `[delopay_products]`
		// yet.
		'delopay_woo_redirects',
		'delopay_woo_url_map',
	);
	foreach ( $options as $option_name ) {
		delete_option( $option_name );
	}

	// Cleared per site. `register_deactivation_hook` fires once, in whichever
	// blog the deactivation happened in, so on a network every other sub-site
	// would keep three recurring events with no callbacks left to run — one of
	// them on a custom schedule (`delopay_fifteen_minutes`) that no longer
	// exists either.
	foreach ( array( 'delopay_reconcile_refunds', 'delopay_health_check', 'delopay_prune_logs' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	delopay_uninstall_restore_woo_storefront();

	// Every transient this plugin sets is prefixed `delopay_` — connect handshake
	// state and the log throttle windows.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_delopay_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_delopay_' ) . '%'
		)
	);
}

// On a network, `uninstall.php` is included once, in the context of whichever
// blog the deletion request ran in. Every table and option this plugin owns is
// per-site, so without this loop a sub-site that ran the takeover keeps its
// storefront drafted forever, with the plugin gone and no way to undo it.
if ( is_multisite() ) {
	$delopay_site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 0,
			'update_site_meta_cache' => false,
		)
	);
	foreach ( (array) $delopay_site_ids as $delopay_site_id ) {
		switch_to_blog( (int) $delopay_site_id );
		// One option read before doing anything expensive. A network can carry
		// tens of thousands of blogs and most of them never ran this plugin;
		// the full teardown is ~40 queries a site, which at that scale is a
		// request that times out and leaves the network half-uninstalled.
		if ( false !== get_option( 'delopay_settings' ) || false !== get_option( 'delopay_schema_version' ) ) {
			delopay_uninstall_site();
		}
		restore_current_blog();
	}
	unset( $delopay_site_ids, $delopay_site_id );
} else {
	delopay_uninstall_site();
}
