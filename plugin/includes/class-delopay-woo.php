<?php
/**
 * Replacing an existing WooCommerce storefront.
 *
 * The realistic onboarding path is not a blank WordPress install — it is a site
 * that already runs WooCommerce and wants DeloPay instead. Activating this
 * plugin does not make WooCommerce go away, and WooCommerce usually cannot
 * simply be deactivated (block themes hard-depend on it and 500 the front end
 * without it). So the old storefront keeps serving in parallel:
 *
 * - `/shop/` still renders the Woo product archive with "Add to cart" buttons
 *   that dead-end at a cart nobody uses;
 * - `/cart/`, `/checkout/`, `/my-account/` stay publicly reachable and
 *   indexable even though nothing links to them.
 *
 * There is a harder failure mode behind the same cause. If the DeloPay
 * shortcodes are pasted onto WooCommerce's *own* cart/checkout pages, Woo's
 * `template_redirect` sends its checkout page to its cart page whenever *Woo's*
 * cart is empty — and Woo's cart is always empty, because the DeloPay cart
 * lives in `localStorage` and is invisible to it. The result is a checkout that
 * 302s away forever with no error anywhere. Two of two replace-installs hit
 * this, so it is detected here rather than documented and hoped for.
 *
 * @package Delopay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Woo {

	/**
	 * How much of the Woo storefront is currently being redirected away.
	 *
	 * Stored separately from the per-post marks below so the redirects can be
	 * turned off without un-hiding anything, and vice versa. Two scopes, because
	 * the two actions promise different things:
	 *
	 * - {@see SCOPE_ALL} — the takeover. Products, the product archive, product
	 *   taxonomies and all four Woo pages.
	 * - {@see SCOPE_PAGES} — the "give each shortcode its own page" fix. Only the
	 *   Woo pages it just retired. A live Woo catalog is untouched: that button
	 *   asks to fix one misplaced shortcode, and permanently redirecting every
	 *   product URL is not a side effect anyone asked for.
	 */
	const OPTION_REDIRECTS = 'delopay_woo_redirects';

	/** Redirect the whole WooCommerce storefront. */
	const SCOPE_ALL = 'all';

	/** Redirect only the WooCommerce pages this plugin retired. */
	const SCOPE_PAGES = 'pages';

	/**
	 * The URLs the WooCommerce storefront occupied when it was replaced.
	 *
	 * Written before anything is drafted, because drafting a page destroys the
	 * pretty permalink that is sitting in search results — and that URL is
	 * precisely the one that has to keep redirecting afterwards.
	 */
	const OPTION_URL_MAP = 'delopay_woo_url_map';

	/**
	 * Marks a post this plugin moved to draft, so "undo" can restore exactly
	 * what it hid and nothing else. A product the merchant drafted themselves
	 * carries no mark and is left alone on undo.
	 */
	const META_HIDDEN = '_delopay_woo_hidden';

	/**
	 * Products hidden per click. A replace-install can carry thousands of demo
	 * products and one PHP request should not try to rewrite all of them; the
	 * admin is told how many are left and can run it again.
	 */
	const BATCH = 500;

	/** WooCommerce page keys this plugin knows how to replace. */
	const WOO_PAGES = array( 'shop', 'cart', 'checkout', 'myaccount' );

	/**
	 * The singleton.
	 *
	 * @var Delopay_Woo|null
	 */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 5 );
		add_action( 'admin_notices', array( $this, 'conflict_notice' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'forget_republished_post' ), 10, 3 );
	}

	/**
	 * Drop our marker when the merchant republishes a post themselves.
	 *
	 * The marker is what "undo" restores and what the page scope treats as
	 * "this URL is ours to redirect". A merchant who republishes Woo's cart page
	 * by hand has taken it back; leaving the marker on would keep redirecting a
	 * live page away, with nothing in the UI to explain it, and would have Undo
	 * later claim to have restored something it did not hide.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       The post.
	 */
	public static function forget_republished_post( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || $new_status === $old_status ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		// `undo_takeover()` republishes and clears the marker itself; this hook
		// firing during that pass is harmless, it clears the same marker.
		delete_post_meta( (int) $post->ID, self::META_HIDDEN );
	}

	/* --------------------------- Detection ---------------------------- */

	/**
	 * Is WooCommerce active on this site?
	 *
	 * @return bool
	 */
	public static function is_woo_active() {
		return class_exists( 'WooCommerce' ) || function_exists( 'wc_get_page_id' );
	}

	/**
	 * WooCommerce page ids, keyed by Woo's page slug key.
	 *
	 * `wc_get_page_id()` answers 0 or -1 for a page Woo does not have; both are
	 * dropped here so callers only ever see real post ids.
	 *
	 * @return array<string, int>
	 */
	public static function woo_page_ids() {
		$ids = array();
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return $ids;
		}
		foreach ( self::WOO_PAGES as $key ) {
			$id = (int) wc_get_page_id( $key );
			if ( $id > 0 && 'page' === get_post_type( $id ) ) {
				$ids[ $key ] = $id;
			}
		}
		return $ids;
	}

	/**
	 * What is still leaking from the old storefront.
	 *
	 * @return array{
	 *     woo_active: bool,
	 *     published_products: int,
	 *     published_pages: array<string, array{id:int, title:string, url:string}>,
	 *     hidden_products: int,
	 *     hidden_pages: int,
	 *     redirects: bool,
	 *     conflicts: array<int, array{id:int, title:string, url:string, woo_page:string, shortcode:string}>,
	 *     structural: array<int, array{id:int, title:string, role:string}>,
	 *     storefront_ready: bool
	 * }
	 */
	public static function survey() {
		$survey = array(
			'woo_active'         => self::is_woo_active(),
			'published_products' => 0,
			'published_pages'    => array(),
			'hidden_products'    => 0,
			'hidden_pages'       => 0,
			'redirects'          => self::redirects_enabled(),
			'conflicts'          => self::shortcode_conflicts(),
			'structural'         => array(),
			'storefront_ready'   => (bool) self::preferred_page_with_shortcode( 'delopay_products' ),
		);

		// Counted before the WooCommerce check, not after: deactivating
		// WooCommerce is the *goal* of a successful replacement, and the undo
		// control has to survive it. Everything this plugin hid is still hidden
		// and its URLs still redirect whether Woo is loaded or not.
		//
		// Two counting queries rather than a `get_post_type()` per id: the id
		// query does not prime the post cache, so on the very site `BATCH`
		// exists for — thousands of hidden products — that shape fires
		// thousands of point selects on every render of the one screen that
		// carries the Undo button.
		$survey['hidden_products'] = self::count_hidden( 'product' );
		$survey['hidden_pages']    = self::count_hidden( 'page' );

		if ( ! $survey['woo_active'] ) {
			return $survey;
		}

		$survey['published_products'] = self::count_published_products();

		foreach ( self::woo_page_ids() as $key => $id ) {
			if ( 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$survey['published_pages'][ $key ] = array(
				'id'    => $id,
				'title' => (string) get_the_title( $id ),
				'url'   => (string) get_permalink( $id ),
			);
		}

		// A Woo page that is also the homepage (or the posts page) is neither
		// hidden nor redirected — see `is_structural_page()`. It is reported
		// instead, because it is the one leak the merchant has to close by hand.
		foreach ( self::woo_page_ids() as $id ) {
			if ( ! self::is_structural_page( $id ) ) {
				continue;
			}
			$survey['structural'][] = array(
				'id'    => (int) $id,
				'title' => (string) get_the_title( $id ),
				'role'  => (int) get_option( 'page_on_front' ) === (int) $id ? 'front' : 'posts',
			);
		}

		return $survey;
	}

	/**
	 * Number of published WooCommerce products.
	 *
	 * @return int
	 */
	private static function count_published_products() {
		if ( ! post_type_exists( 'product' ) ) {
			return 0;
		}
		$counts = wp_count_posts( 'product' );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * How many posts of a type this plugin is currently hiding.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	private static function count_hidden( $post_type ) {
		global $wpdb;

		// A real COUNT, not `count( get_posts( … ) )`. On the site `BATCH`
		// exists for — thousands of hidden products — materialising every id
		// just to measure the array would run two unbounded selects on every
		// render of the one screen that carries the Undo button.
		//
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- an aggregate over
		// this plugin's own marker; `wp_cache_*` would shadow a value that
		// changes on every takeover/undo click, and there is no core helper
		// that counts posts by meta.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s",
				self::META_HIDDEN,
				'1',
				$post_type
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return (int) $count;
	}

	/**
	 * Ids of every post this plugin has hidden, whatever its current status.
	 *
	 * @param string|string[] $post_type Post type(s) to count.
	 * @return int[]
	 */
	private static function hidden_post_ids( $post_type = array( 'product', 'page' ) ) {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				// Every status, not `'any'` — which quietly excludes `trash`.
				// A trashed post keeps its meta, so with `'any'` a marked post
				// the merchant threw away would be counted by `count_hidden()`
				// forever while `undo_takeover()` never saw it to clear the
				// marker: Undo would report "1 still hidden" on every click and
				// never disarm the redirects.
				'post_status'      => array_keys( get_post_stati() ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'meta_key'         => self::META_HIDDEN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one admin-screen query over a marker this plugin writes; there is no taxonomy equivalent.
				'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * DeloPay shortcodes sitting on a WooCommerce page.
	 *
	 * The checkout case is the one that silently breaks a store (Woo redirects
	 * its checkout page to its cart page whenever Woo's own cart is empty, which
	 * it always is), but a DeloPay shortcode on any Woo page means the two
	 * storefronts share a URL and cannot both be replaced cleanly.
	 *
	 * @return array<int, array{id:int, title:string, url:string, woo_page:string, shortcode:string}>
	 */
	public static function shortcode_conflicts() {
		$conflicts = array();
		if ( ! self::is_woo_active() ) {
			return $conflicts;
		}

		$interesting = array(
			'cart'     => 'delopay_cart',
			'checkout' => 'delopay_checkout',
		);

		foreach ( self::woo_page_ids() as $key => $id ) {
			$post = get_post( $id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			foreach ( $interesting as $shortcode ) {
				if ( ! has_shortcode( (string) $post->post_content, $shortcode ) ) {
					continue;
				}
				$conflicts[] = array(
					'id'        => $id,
					'title'     => (string) get_the_title( $id ),
					'url'       => (string) get_permalink( $id ),
					'woo_page'  => $key,
					'shortcode' => $shortcode,
				);
			}
		}

		return $conflicts;
	}

	/* ----------------------------- Actions ---------------------------- */

	public static function redirects_enabled(): bool {
		return '' !== self::redirect_scope();
	}

	/**
	 * The WooCommerce paths currently being redirected, for display.
	 *
	 * Read from the captured map rather than named in the copy: the takeover
	 * skips any page whose replacement does not exist, and the split claims only
	 * what it retired, so a fixed "/shop, /cart and /checkout" line is wrong in
	 * both of those states — and a merchant who believes it leaves the old
	 * storefront serving.
	 *
	 * @return string[]
	 */
	public static function redirected_paths() {
		if ( ! self::redirects_enabled() ) {
			return array();
		}

		$map   = self::url_map();
		$pages = self::woo_page_ids();
		$all   = self::SCOPE_ALL === self::redirect_scope();
		$paths = array();

		// Filtered through the same test `target_for_retired_path()` applies.
		// The map records a URL for every key an action *claimed*, but a page
		// that turned out to be unhidable — it serves another DeloPay shortcode,
		// or the merchant has since republished it — is still serving, and
		// listing it here would tell the merchant a leak is closed when it is
		// not.
		foreach ( $map['pages'] as $path => $key ) {
			$claimed = isset( $pages[ $key ] ) ? self::claims_post( $pages[ $key ] ) : $all;
			// A claim with no destination left is not a redirect. Since the
			// DeloPay page a URL was pointed at can be deleted at any time,
			// `target_for_page_key()` may now answer `null` and the request
			// serves its 404 — listing it here would report a leak as closed
			// when it has just reopened.
			if ( $claimed && null !== self::target_for_page_key( $key ) ) {
				$paths[] = $path;
			}
		}

		if ( $all && null !== self::storefront_url() ) {
			if ( '' !== $map['archive'] ) {
				$paths[] = $map['archive'];
			}
			if ( '' !== $map['product_base'] ) {
				$paths[] = untrailingslashit( $map['product_base'] ) . '/*';
			}
		}

		// Deduped: WooCommerce registers the product archive *at* the shop page's
		// URI, so `$map['pages']['/shop']` and `$map['archive']` normalise to
		// the same path and the plain takeover would print `/shop` twice on the
		// one screen whose job is to be an accurate inventory.
		$paths = array_values( array_unique( $paths ) );
		sort( $paths );
		return $paths;
	}

	/**
	 * Why {@see redirected_paths()} is empty while redirects are still armed.
	 *
	 * Two very different causes, and the fix differs:
	 *
	 * - `woo_inactive` — the page scope proves ownership from the
	 *   `META_HIDDEN` marker on a Woo page, and without WooCommerce there is no
	 *   `wc_get_page_id()` to find those pages. `target_for_retired_path()`
	 *   fails closed, so the URLs serve 404s. The DeloPay pages are fine;
	 *   telling the merchant to republish them would waste their time.
	 * - `released` — the merchant republished the WooCommerce pages themselves,
	 *   which drops the marker (`forget_republished_post()`) and with it our
	 *   claim. Nothing is missing and nothing is 404ing; only the armed option
	 *   is left over.
	 * - `no_targets` — the DeloPay pages these URLs pointed at were deleted or
	 *   unpublished. Republishing them does fix it.
	 *
	 * @return string `''` when the list is non-empty or redirects are off.
	 */
	public static function empty_redirect_reason() {
		if ( ! self::redirects_enabled() || ! empty( self::redirected_paths() ) ) {
			return '';
		}

		if ( ! self::is_woo_active() && self::SCOPE_ALL !== self::redirect_scope() ) {
			return 'woo_inactive';
		}

		if ( self::SCOPE_PAGES === self::redirect_scope() && self::is_woo_active() && self::every_claim_released() ) {
			return 'released';
		}

		return 'no_targets';
	}

	/**
	 * Has every page in the captured map been taken back by the merchant?
	 *
	 * True only when each mapped page still exists, still has somewhere to point,
	 * and simply no longer carries our marker — which is what republishing it by
	 * hand does. A page that has vanished, or whose DeloPay replacement has, is a
	 * different problem with a different fix.
	 *
	 * @return bool
	 */
	private static function every_claim_released() {
		$map   = self::url_map();
		$pages = self::woo_page_ids();

		if ( empty( $map['pages'] ) ) {
			return false;
		}

		foreach ( $map['pages'] as $key ) {
			if ( ! isset( $pages[ $key ] ) ) {
				return false;
			}
			if ( self::claims_post( $pages[ $key ] ) ) {
				return false;
			}
			if ( null === self::target_for_page_key( $key ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Which redirects are armed: `''`, {@see SCOPE_PAGES} or {@see SCOPE_ALL}.
	 *
	 * `'1'` is the value the first release of this feature wrote, and it meant
	 * the full takeover — so it is read as {@see SCOPE_ALL} rather than being
	 * silently ignored on a site that upgraded mid-replacement.
	 *
	 * @return string
	 */
	public static function redirect_scope(): string {
		$stored = (string) get_option( self::OPTION_REDIRECTS, '' );

		if ( '1' === $stored || self::SCOPE_ALL === $stored ) {
			return self::SCOPE_ALL;
		}

		return self::SCOPE_PAGES === $stored ? self::SCOPE_PAGES : '';
	}

	/**
	 * Hide the WooCommerce storefront and start redirecting its URLs.
	 *
	 * Refuses if there is no DeloPay catalog page to send anyone to. The
	 * redirects are permanent — browsers and CDNs cache a 301 indefinitely, and
	 * Undo cannot reach a visitor who already followed one — so pointing the
	 * whole old storefront at a storefront that does not exist yet is not a
	 * mistake this offers to make.
	 *
	 * @param bool $enable_redirects Whether to also turn redirects on.
	 * @return array{products:int, pages:int, remaining:int, blocked:string}
	 */
	public static function apply_takeover( $enable_redirects = true ) {
		$stats = array(
			'products'  => 0,
			'pages'     => 0,
			'remaining' => 0,
			'blocked'   => '',
		);

		if ( ! self::is_woo_active() ) {
			$stats['blocked'] = 'woo_inactive';
			return $stats;
		}

		if ( ! self::preferred_page_with_shortcode( 'delopay_products' ) ) {
			$stats['blocked'] = 'no_storefront';
			return $stats;
		}

		// Same reasoning, one level down. `page_url()` answers `null` when no
		// page carries the shortcode, so a claimed URL with no destination
		// serves its 404 rather than a redirect — survivable, but pointless:
		// hiding Woo's cart page to replace it with nothing is strictly worse
		// than leaving it up. Only the keys with a real destination are claimed;
		// the rest keep serving whatever they serve.
		$claim_keys = array( 'shop', 'myaccount' );
		if ( self::preferred_page_with_shortcode( 'delopay_cart' ) ) {
			$claim_keys[] = 'cart';
		}
		if ( self::preferred_page_with_shortcode( 'delopay_checkout' ) ) {
			$claim_keys[] = 'checkout';
		}

		// Captured before anything is drafted: a drafted page's permalink is no
		// longer the pretty URL that is sitting in search results and in
		// visitors' history, and that URL is exactly what has to keep
		// redirecting.
		self::capture_url_map( $claim_keys );

		$stats['products']  = self::hide_products();
		$stats['remaining'] = self::count_published_products();
		$stats['pages']     = self::hide_woo_pages( $claim_keys );

		if ( $enable_redirects ) {
			// Autoloaded: `maybe_redirect()` reads it on every front-end
			// request, and a non-autoloaded option costs an extra `wp_options`
			// query per page view on installs without a persistent object
			// cache. The URL map below stays non-autoloaded — it is only
			// consulted on a 404.
			update_option( self::OPTION_REDIRECTS, self::SCOPE_ALL, true );
		}

		Delopay_Shortcodes::invalidate_page_cache();

		return $stats;
	}

	/**
	 * Restore everything this plugin hid and stop redirecting.
	 *
	 * Only posts carrying our own marker are touched, and only if they are
	 * still drafts — a page the merchant has since republished or trashed is
	 * left exactly as they left it. (Content is not compared: a draft the
	 * merchant edited while it was hidden is still republished.)
	 *
	 * Batched like the takeover, and for the same reason: a catalog big enough
	 * to need batching on the way down needs it on the way back too. The
	 * redirects stay armed until the last marked post is restored — switching
	 * them off over a half-republished catalog would leave the un-restored half
	 * reachable at URLs that answer 404.
	 *
	 * @return array{restored:int, remaining:int}
	 */
	public static function undo_takeover() {
		$restored = 0;
		$hidden   = self::hidden_post_ids();

		foreach ( array_slice( $hidden, 0, self::BATCH ) as $id ) {
			if ( 'draft' === get_post_status( $id ) ) {
				if ( ! self::set_post_status( $id, 'publish' ) ) {
					continue;
				}
				++$restored;
			}
			delete_post_meta( $id, self::META_HIDDEN );
		}

		// Counted after the batch, not calculated from before it. A republish
		// that errors leaves its marker in place and is skipped above, so
		// `count( $hidden ) - BATCH` would report "nothing left" over posts that
		// are still drafted — and then disarm the redirects that were the only
		// thing keeping their URLs reachable.
		$remaining = self::count_hidden( 'product' ) + self::count_hidden( 'page' );

		if ( 0 === $remaining ) {
			delete_option( self::OPTION_REDIRECTS );
			delete_option( self::OPTION_URL_MAP );
		}

		Delopay_Shortcodes::invalidate_page_cache();

		return array(
			'restored'  => $restored,
			'remaining' => $remaining,
		);
	}

	/**
	 * Remember which URLs the old storefront occupied, before we take them down.
	 *
	 * Drafting a page 404s it, and a 404 has no queried object to recognise —
	 * `is_page( $cart_id )` can only ever match while the page is still
	 * published. The URLs themselves outlive that: they are in search indexes,
	 * in bookmarks and in the merchant's own menus. So the paths are written
	 * down here and matched textually afterwards.
	 *
	 * Products are covered by their permalink base rather than one entry each —
	 * a catalog can carry thousands, and every one of them lives under the same
	 * prefix.
	 *
	 * @param string[]|null $claim_keys WooCommerce page keys being retired, or
	 *                                  null for all of them (the takeover).
	 */
	private static function capture_url_map( ?array $claim_keys = null ): void {
		// Merged, not replaced. The two actions that hide pages can run in
		// either order — "give each shortcode its own page" from the notice
		// drafts the cart and checkout that the takeover deliberately skipped —
		// and a second capture must not forget the URLs the first one recorded
		// while those pages were still published.
		$map = self::url_map();

		foreach ( self::woo_page_ids() as $key => $id ) {
			if ( 'publish' !== get_post_status( $id ) || self::is_structural_page( $id ) ) {
				continue;
			}
			// `null` means "all of them" — the takeover. A caller that names its
			// keys is retiring only those, and recording a URL it never claimed
			// would let a later 404 on that URL redirect anyway.
			if ( null !== $claim_keys && ! in_array( $key, $claim_keys, true ) ) {
				continue;
			}
			$path = self::path_of( (string) get_permalink( $id ) );
			if ( '' !== $path ) {
				$map['pages'][ $path ] = $key;
			}
		}

		$archive = get_post_type_archive_link( 'product' );
		if ( is_string( $archive ) ) {
			$map['archive'] = self::path_of( $archive );
		}

		if ( function_exists( 'wc_get_permalink_structure' ) ) {
			$structure = wc_get_permalink_structure();
			$slug      = isset( $structure['product_rewrite_slug'] ) ? trim( (string) $structure['product_rewrite_slug'], '/' ) : '';
			// `%product_cat%` in the slug means product URLs are nested under
			// their category and share no usable literal prefix, so no base is
			// recorded rather than one that would over-match.
			if ( '' !== $slug && false === strpos( $slug, '%' ) ) {
				// The slug is site-relative. WordPress installed in a
				// subdirectory serves products at `/store/product/x`, so the
				// install's own path has to be in front of it or the prefix
				// match never fires — and the "one entry covering a catalog of
				// any size" promise below quietly covers nothing.
				$home = self::path_of( home_url( '/' ) );
				$home = '/' === $home ? '' : $home;

				$map['product_base'] = $home . '/' . $slug . '/';
			}
		}

		update_option( self::OPTION_URL_MAP, $map, false );
	}

	/**
	 * The captured URL map, in the shape `capture_url_map()` writes.
	 *
	 * @return array{pages: array<string, string>, archive: string, product_base: string}
	 */
	private static function url_map(): array {
		$stored = get_option( self::OPTION_URL_MAP, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array(
			'pages'        => isset( $stored['pages'] ) && is_array( $stored['pages'] ) ? $stored['pages'] : array(),
			'archive'      => isset( $stored['archive'] ) ? (string) $stored['archive'] : '',
			'product_base' => isset( $stored['product_base'] ) ? (string) $stored['product_base'] : '',
		);
	}

	/**
	 * The path component of a URL, normalised without a trailing slash.
	 *
	 * @param string $url Absolute or relative URL.
	 * @return string
	 */
	private static function path_of( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( '' === $path ) {
			return '';
		}

		// Decoded before comparison. A permalink for a non-ASCII slug is stored
		// percent-encoded and lower-case (`%c3%bc`) while browsers send it
		// upper-case (`%C3%BC`); comparing the raw forms makes two spellings of
		// the same URL look different. Decoding normalises both.
		$path = rawurldecode( $path );

		// The site root has to stay distinguishable from "no path at all":
		// `untrailingslashit( '/' )` is the empty string, and an empty target
		// path is the one value the loop guard treats as "cannot compare".
		$trimmed = untrailingslashit( $path );
		return '' === $trimmed ? '/' : $trimmed;
	}

	/**
	 * Move published Woo products to draft, up to one batch.
	 *
	 * @return int Number moved.
	 */
	private static function hide_products() {
		if ( ! post_type_exists( 'product' ) ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'numberposts'      => self::BATCH,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);

		$hidden = 0;
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( ! self::set_post_status( $id, 'draft' ) ) {
				continue;
			}
			update_post_meta( $id, self::META_HIDDEN, '1' );
			++$hidden;
		}

		return $hidden;
	}

	/**
	 * Move the Woo cart/checkout/shop/my-account pages to draft.
	 *
	 * A Woo page carrying *any* DeloPay shortcode is skipped, not just the two
	 * the conflict notice is about: `[delopay_products]` pasted onto Woo's own
	 * shop page is the obvious move on a replace-install, and drafting it would
	 * delete the merchant's only catalog page — after which every product URL
	 * redirects to a storefront that no longer exists.
	 *
	 * @param string[] $claim_keys WooCommerce page keys with a working
	 *                             replacement to redirect to.
	 * @return int Number moved.
	 */
	private static function hide_woo_pages( array $claim_keys ) {
		$hidden = 0;

		foreach ( self::woo_page_ids() as $key => $id ) {
			// A page whose replacement does not exist is left serving. Taking it
			// down without a redirect to put in its place turns a working URL
			// into a 404, which is not an improvement on a parallel storefront.
			if ( ! in_array( $key, $claim_keys, true ) ) {
				continue;
			}
			if ( self::page_carries_any_delopay_shortcode( $id ) ) {
				continue;
			}
			if ( self::is_structural_page( $id ) ) {
				continue;
			}
			if ( 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			if ( ! self::set_post_status( $id, 'draft' ) ) {
				continue;
			}
			update_post_meta( $id, self::META_HIDDEN, '1' );
			++$hidden;
		}

		return $hidden;
	}

	/**
	 * Move a post between statuses without touching anything else about it.
	 *
	 * `wp_update_post()` reads the existing row, merges the change in and sends
	 * the whole thing back through `wp_insert_post()` — which runs
	 * `sanitize_post( …, 'db' )`, which runs `content_save_pre`, which is where
	 * `wp_filter_post_kses` lives whenever the current user lacks
	 * `unfiltered_html`. On multisite that is every non-super-admin, and
	 * `DISALLOW_UNFILTERED_HTML` does the same on a single site. A status-only
	 * change would then quietly strip iframes, scripts, styles and page-builder
	 * markup out of hundreds of product descriptions — and Undo would strip them
	 * a second time. "Nothing is deleted" has to be true.
	 *
	 * The kses filters are removed for the duration and restored exactly as they
	 * were, so every other hook a status transition normally fires still runs.
	 *
	 * @param int    $id     Post id.
	 * @param string $status New post status.
	 * @return bool Whether the status was changed.
	 */
	private static function set_post_status( $id, $status ) {
		$filtering = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $filtering ) {
			kses_remove_filters();
		}

		$updated = wp_update_post(
			array(
				'ID'          => (int) $id,
				'post_status' => $status,
			),
			true
		);

		if ( $filtering ) {
			kses_init_filters();
		}

		return ! is_wp_error( $updated );
	}

	/**
	 * Does this page render any part of the DeloPay storefront?
	 *
	 * Every shortcode counts. Losing the catalog page or the order-complete page
	 * is as damaging as losing the cart: buyers who have already paid land on
	 * the complete page, and a 404 there looks like a failed payment.
	 *
	 * @param int $id Page id.
	 * @return bool
	 */
	private static function page_carries_any_delopay_shortcode( $id ) {
		return self::page_carries_delopay_shortcode_other_than( $id, array() );
	}

	/**
	 * Does this page still serve a DeloPay shortcode outside a given set?
	 *
	 * Used to decide whether a Woo page is safe to draft once the shortcodes
	 * being relocated have somewhere else to live.
	 *
	 * @param int      $id      Page id.
	 * @param string[] $exclude Shortcode tags to ignore.
	 * @return bool
	 */
	private static function page_carries_delopay_shortcode_other_than( $id, array $exclude ) {
		$post = get_post( (int) $id );
		if ( ! $post ) {
			return false;
		}

		foreach ( Delopay_Shortcodes::tags() as $tag ) {
			if ( in_array( $tag, $exclude, true ) ) {
				continue;
			}
			if ( has_shortcode( (string) $post->post_content, $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is this page load-bearing for the whole site?
	 *
	 * A merchant is free to point "Your homepage displays" at Woo's shop page,
	 * and some themes ship exactly that. Drafting the front page or the posts
	 * page does not hide a storefront — it takes the site down, which is a price
	 * far beyond what "hide the WooCommerce storefront" asks for. Redirecting it
	 * is no better: a 301 on the site root moves the homepage in every browser
	 * that follows it. Both are left alone and reported instead.
	 *
	 * @param int $id Page id.
	 * @return bool
	 */
	private static function is_structural_page( $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}

		// WordPress does not clear `page_on_front` when the merchant switches
		// Reading back to "Your latest posts" — the dropdown submits its value
		// either way. Trusting it unconditionally would mark a page as the
		// homepage forever after it stopped being one, and this plugin would
		// then refuse to hide or redirect a leak it is perfectly able to close,
		// while telling the merchant to change a setting they already changed.
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return false;
		}

		return (int) get_option( 'page_on_front' ) === $id || (int) get_option( 'page_for_posts' ) === $id;
	}

	/**
	 * Can the "give each shortcode its own page" action finish this conflict?
	 *
	 * It cannot when drafting the Woo page is off the table — the page is the
	 * site's homepage, or it also serves a DeloPay shortcode that is not being
	 * relocated (the catalog, the order-complete page). Leaving the Woo page
	 * published keeps the conflict alive, so the merchant has to move the
	 * shortcode by hand.
	 *
	 * @param int $id Woo page id.
	 * @return bool
	 */
	private static function can_relocate_from( $id ) {
		$id = (int) $id;

		if ( self::is_structural_page( $id ) ) {
			return false;
		}

		if ( self::page_carries_delopay_shortcode_other_than(
			$id,
			array( 'delopay_cart', 'delopay_checkout' )
		) ) {
			return false;
		}

		$post = get_post( $id );
		if ( ! $post ) {
			return false;
		}

		// A replacement that exists but is not published serves nothing, so the
		// Woo page cannot come down — and the button would report "0 created" on
		// every press while the conflict stayed live.
		foreach ( array( 'delopay_cart', 'delopay_checkout' ) as $tag ) {
			if ( ! has_shortcode( (string) $post->post_content, $tag ) ) {
				continue;
			}
			if ( self::unpublished_replacement_for( $tag ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Has this page already been reported as waiting on an unpublished replacement?
	 *
	 * @param array<int, array{id:int}> $unpublish Entries emitted so far.
	 * @param int                       $id        Page id.
	 * @return bool
	 */
	private static function page_had_pending( array $unpublish, $id ) {
		foreach ( $unpublish as $entry ) {
			if ( (int) $entry['id'] === (int) $id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is an unpublished replacement the only thing stopping this conflict being fixed?
	 *
	 * @param array{id:int, shortcode:string} $conflict One conflict entry.
	 * @return bool
	 */
	private static function blocked_only_by_unpublished_replacement( array $conflict ) {
		$id = (int) $conflict['id'];

		if ( self::is_structural_page( $id ) ) {
			return false;
		}

		return ! self::page_carries_delopay_shortcode_other_than(
			$id,
			array( 'delopay_cart', 'delopay_checkout' )
		);
	}

	/**
	 * The replacement page for a shortcode that exists but is not published.
	 *
	 * `null` when there is none — either because no replacement has been made
	 * yet (the button will make one) or because the one that exists is already
	 * serving. The distinction matters to the notice: "publish the page you
	 * already have" and "move this shortcode onto a page of its own" are
	 * different instructions, and giving the wrong one sends the merchant to
	 * create a duplicate.
	 *
	 * @param string $shortcode Shortcode tag.
	 * @return WP_Post|null
	 */
	private static function unpublished_replacement_for( $shortcode ) {
		if ( self::non_woo_page_with_shortcode( $shortcode ) ) {
			return null;
		}
		return self::any_status_page_with_shortcode( $shortcode );
	}

	/**
	 * Give a conflicting Woo page's shortcode a page of its own.
	 *
	 * Creates `delopay-cart` / `delopay-checkout` carrying the shortcode, then
	 * drafts the Woo page it came from — leaving both published would make
	 * `page_with_shortcode()` pick between two pages by title, which is not a
	 * decision the merchant made.
	 *
	 * @return array{created:int, hidden:int, skipped:int, redirects:bool}
	 */
	public static function split_conflicting_pages() {
		$created = 0;
		$hidden  = 0;
		$skipped = 0;

		// Same reason as in `apply_takeover()`: whatever this is about to draft
		// has to keep redirecting from the URL it has today — and only that.
		$conflicts   = self::shortcode_conflicts();
		$pages_by_id = array_flip( self::woo_page_ids() );
		$claim_keys  = array();
		foreach ( $conflicts as $conflict ) {
			if ( isset( $pages_by_id[ (int) $conflict['id'] ] ) ) {
				$claim_keys[] = (string) $pages_by_id[ (int) $conflict['id'] ];
			}
		}
		self::capture_url_map( array_values( array_unique( $claim_keys ) ) );

		// Grouped by page, because one Woo page can hold both shortcodes and the
		// decision to draft it can only be taken once every shortcode it serves
		// has somewhere else to live.
		$relocated = array();

		foreach ( $conflicts as $conflict ) {
			$shortcode = $conflict['shortcode'];
			$page_id   = (int) $conflict['id'];

			$relocated[ $page_id ]   = isset( $relocated[ $page_id ] ) ? $relocated[ $page_id ] : array();
			$relocated[ $page_id ][] = $shortcode;

			// Creation asks a *broader* question than drafting does: is there
			// already a non-Woo page carrying this shortcode in any status? A
			// page left `pending` by an editorial-workflow plugin serves
			// nothing — so the Woo page stays up, below — but re-creating it on
			// every click would pile up orphan duplicates. Drafting asks the
			// narrower "is it published" question a few lines down.
			if ( ! self::any_status_page_with_shortcode( $shortcode ) ) {
				$new_id = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => 'delopay_cart' === $shortcode
							? __( 'Cart', 'delopay' )
							: __( 'Checkout', 'delopay' ),
						'post_name'    => str_replace( '_', '-', $shortcode ),
						'post_content' => "<!-- wp:shortcode -->\n[" . $shortcode . "]\n<!-- /wp:shortcode -->",
					),
					false
				);
				if ( $new_id && ! is_wp_error( $new_id ) ) {
					++$created;
				}
			}
		}

		foreach ( $relocated as $page_id => $shortcodes ) {
			// Same predicate the notice uses to decide whether to offer the
			// button at all, so the two can never disagree about what this
			// action is able to finish.
			if ( ! self::can_relocate_from( $page_id ) ) {
				++$skipped;
				continue;
			}

			// And the collected list is the point of collecting it: every
			// shortcode this page serves must now have a published page of its
			// own. `wp_insert_post()` above can fail, and an editorial-workflow
			// plugin can filter the new page to `pending` — in which case
			// drafting Woo's page would leave the store with no checkout at all
			// while `/checkout/` permanently 301s to the homepage. Same reason
			// `apply_takeover()` will not claim a key it cannot resolve.
			$homeless = false;
			foreach ( $shortcodes as $tag ) {
				if ( ! self::non_woo_page_with_shortcode( $tag ) ) {
					$homeless = true;
					break;
				}
			}
			if ( $homeless ) {
				++$skipped;
				continue;
			}

			if ( 'publish' === get_post_status( $page_id ) && self::set_post_status( $page_id, 'draft' ) ) {
				update_post_meta( $page_id, self::META_HIDDEN, '1' );
				++$hidden;
			}
		}

		// Drafting a page that was serving the merchant's live cart turns its
		// URL — the one their menu links to — into a plain 404. The map
		// captured above is only consulted while redirects are on, so this
		// action arms them for exactly the URLs it just retired: the page
		// scope, never the whole catalog. If a takeover has already armed the
		// full scope, leave it there rather than narrowing it.
		if ( $hidden > 0 && self::SCOPE_ALL !== self::redirect_scope() ) {
			update_option( self::OPTION_REDIRECTS, self::SCOPE_PAGES, true );
		}

		Delopay_Shortcodes::invalidate_page_cache();

		return array(
			'created'   => $created,
			'hidden'    => $hidden,
			'skipped'   => $skipped,
			'redirects' => $hidden > 0,
		);
	}

	/* ---------------------------- Redirects --------------------------- */

	/**
	 * Send the old storefront's URLs at the new one.
	 *
	 * Drafting the Woo pages 404s them, but `/shop/` survives as a post-type
	 * archive with no page behind it — a URL only a redirect can remove. The
	 * same hook covers single products and product taxonomy archives, which are
	 * just as indexed and just as dead-ended.
	 */
	public function maybe_redirect(): void {
		if ( ! self::redirects_enabled() || is_admin() ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		// A merchant has to be able to look at what was hidden before deciding
		// whether to undo. Previewing a drafted product or page is the only way
		// to do that, and the settings card links straight into it.
		if ( is_preview() && current_user_can( 'edit_posts' ) ) {
			return;
		}

		// No target also means "no destination exists" — the DeloPay page this
		// URL was pointed at has since been deleted or unpublished. Serving the
		// 404 is the recoverable outcome; a permanent redirect to whatever else
		// we could think of is not.
		$target = $this->redirect_target();
		if ( ! $target ) {
			return;
		}

		// A shortcode left on a Woo page can make source and target the same
		// URL; redirecting then loops the browser until it gives up.
		if ( $this->is_current_url( $target ) ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * Where the request being served should go instead, if anywhere.
	 *
	 * Two ways to recognise the old storefront, because a URL is reachable in
	 * two states. While the Woo pages are still published the query resolves and
	 * `is_page()` answers; once they are drafted the same URL is a 404 with no
	 * queried object at all, and only the path recorded at takeover time can
	 * still identify it.
	 *
	 * @return string|null
	 */
	private function redirect_target() {
		$resolved = $this->target_for_resolved_query();
		if ( $resolved ) {
			return $resolved;
		}

		return is_404() ? $this->target_for_retired_path() : null;
	}

	/**
	 * The target for a request WordPress still resolves to a Woo object.
	 *
	 * @return string|null
	 */
	private function target_for_resolved_query() {
		// Redirecting the site's front page would move the homepage, and a 301
		// makes that stick in every browser that follows it. Which page is the
		// homepage is the merchant's decision, taken in Settings → Reading; the
		// settings card asks them to change it rather than changing it for them.
		//
		// This has to come first, not inside the page branch below: when Woo's
		// shop page *is* the front page it is also the product archive, so the
		// archive test would claim the request before any page test ran — and
		// in that state WordPress answers `get_queried_object()` with the post
		// *type*, whose id is 0, so the request has to be recognised by what it
		// is rather than by which post it resolved to.
		// `get_queried_object_id()` is only a *post* id on a singular query — on
		// a product-category archive it is a term id, on an author archive a
		// user id — and comparing one of those against `page_on_front` would
		// exempt whichever archive happened to share a number with the
		// homepage. Same guard `is_current_url()` uses, for the same reason.
		$queried_post = ( is_singular() || is_page() ) ? get_queried_object_id() : 0;
		if ( is_front_page() || is_home() || self::is_structural_page( $queried_post ) ) {
			return null;
		}

		// Only the takeover claims the catalog. The shortcode-split fix retires
		// two pages and must leave a live Woo catalog serving.
		if ( self::SCOPE_ALL === self::redirect_scope() ) {
			if ( is_post_type_archive( 'product' ) || is_tax( array( 'product_cat', 'product_tag' ) ) ) {
				return self::storefront_url();
			}
			// A *published* product under a takeover is one the merchant put
			// back, or added afterwards. Either way they published it on
			// purpose; the ones this plugin hid are drafts, and those reach the
			// retired-path branch instead.
			if ( is_singular( 'product' ) ) {
				return self::claims_post( get_queried_object_id() ) ? self::storefront_url() : null;
			}
		}

		$pages = self::woo_page_ids();

		foreach ( self::WOO_PAGES as $key ) {
			if ( ! isset( $pages[ $key ] ) || ! is_page( $pages[ $key ] ) ) {
				continue;
			}
			return self::claims_post( $pages[ $key ] ) ? self::target_for_page_key( $key ) : null;
		}

		return null;
	}

	/**
	 * Is this post one the redirects are entitled to claim?
	 *
	 * The marker this plugin writes when it hides something is the whole answer,
	 * in both scopes. Two consequences worth stating:
	 *
	 * - The split fix claims only what it actually retired, so a merchant who
	 *   clicked it to rescue their checkout does not find `/my-account/`
	 *   redirecting too.
	 * - A merchant who republishes a hidden page or product by hand has taken it
	 *   back: `forget_republished_post()` drops the marker and the redirect
	 *   stops. A live page that still 301s away, no longer counted as hidden and
	 *   so no longer restorable by Undo, is the worst of both states.
	 *
	 * Posts only — the product archive and the product permalink base have no
	 * post to mark, so those stay governed by the scope alone.
	 *
	 * @param int $id Post id.
	 * @return bool
	 */
	private static function claims_post( $id ) {
		return '1' === (string) get_post_meta( (int) $id, self::META_HIDDEN, true );
	}

	/**
	 * The target for a URL the old storefront used to serve and no longer does.
	 *
	 * @return string|null
	 */
	private function target_for_retired_path() {
		$path = self::path_of( self::request_uri() );
		if ( '' === $path ) {
			return null;
		}

		$map = self::url_map();

		if ( isset( $map['pages'][ $path ] ) ) {
			$key   = (string) $map['pages'][ $path ];
			$pages = self::woo_page_ids();

			// Fail closed when the page can no longer be resolved — WooCommerce
			// deactivated, or the page deleted outright. Under the page scope
			// the only evidence that this URL was ours to claim is the marker on
			// the post, and without the post there is no evidence.
			if ( ! isset( $pages[ $key ] ) ) {
				return self::SCOPE_ALL === self::redirect_scope() ? self::target_for_page_key( $key ) : null;
			}

			return self::claims_post( $pages[ $key ] ) ? self::target_for_page_key( $key ) : null;
		}

		if ( self::SCOPE_ALL !== self::redirect_scope() ) {
			return null;
		}

		if ( '' !== $map['archive'] && $path === $map['archive'] ) {
			return self::storefront_url();
		}

		// Everything under the product permalink base — one entry covering a
		// catalog of any size.
		$base = $map['product_base'];
		if ( '' !== $base && 0 === strpos( $path . '/', $base ) ) {
			return self::storefront_url();
		}

		return null;
	}

	/**
	 * The DeloPay page that replaces a given WooCommerce page.
	 *
	 * `myaccount` has no DeloPay counterpart — this plugin has no account area —
	 * so it lands on the storefront rather than nowhere.
	 *
	 * @param string $key WooCommerce page key.
	 * @return string|null
	 */
	private static function target_for_page_key( $key ) {
		switch ( $key ) {
			case 'cart':
				return self::page_url( 'delopay_cart' );
			case 'checkout':
				return self::page_url( 'delopay_checkout' );
			default:
				return self::storefront_url();
		}
	}

	/**
	 * The path of the request being served.
	 *
	 * @return string
	 */
	private static function request_uri() {
		// Deliberately not `sanitize_text_field()`: it strips every `%xx`
		// sequence, so `/warenkorb-%C3%BCbersicht/` arrives here as
		// `/warenkorb-bersicht` — no map entry matches, the retired page serves
		// a bare 404 instead of redirecting, and the loop guard can compare two
		// different manglings of the same URL and conclude they differ. The
		// value is only ever parsed as a URL and compared to permalinks this
		// site generated; it is never echoed or used in a query.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the current request path, not input.
		return isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * The DeloPay page carrying a shortcode, or `null` when there is none.
	 *
	 * Deliberately not "the site root as a last resort". `apply_takeover()`
	 * refuses to claim a URL whose replacement does not exist, on the grounds
	 * that 301-ing it to the homepage is a mistake nobody can take back — and
	 * the page can just as easily be deleted the week *after* the takeover. A
	 * claimed URL with nowhere to point serves its 404, which the merchant can
	 * still fix; a permanent redirect to the wrong page is cached by every
	 * browser and CDN that sees it.
	 *
	 * @param string $shortcode Shortcode tag.
	 * @return string|null
	 */
	private static function page_url( $shortcode ) {
		$page = self::preferred_page_with_shortcode( $shortcode );
		return $page ? (string) get_permalink( $page ) : null;
	}

	/**
	 * The page a shortcode should be served from, preferring a non-Woo one.
	 *
	 * When the same shortcode sits on both a DeloPay page and WooCommerce's own
	 * page, `get_pages()` order decides which one wins — and both are titled
	 * "Checkout", so the tie is resolved by nothing meaningful. Picking Woo's
	 * page is the worst outcome available: it is the page Woo redirects away
	 * from, so every link built from it lands the buyer back on a cart. The
	 * DeloPay-owned page wins whenever there is one.
	 *
	 * @param string $shortcode Shortcode tag.
	 * @return WP_Post|null
	 */
	public static function preferred_page_with_shortcode( $shortcode ) {
		$ours = self::non_woo_page_with_shortcode( $shortcode );
		if ( $ours ) {
			return $ours;
		}

		foreach ( get_pages() as $page ) {
			if ( has_shortcode( (string) $page->post_content, $shortcode ) ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * The first page carrying a shortcode that WooCommerce does not own, in any
	 * status it could still serve or be published from.
	 *
	 * Used to decide whether to *create* a replacement. An unpublished one still
	 * counts: it means a previous click already made the page, and making
	 * another would leave the merchant with a pile of duplicates.
	 *
	 * @param string $shortcode Shortcode tag.
	 * @return WP_Post|null
	 */
	private static function any_status_page_with_shortcode( $shortcode ) {
		$woo_ids = array_map( 'intval', array_values( self::woo_page_ids() ) );

		// Every status a page can actually serve from, or be published from —
		// which is to say, not `trash` and not `auto-draft`. Trashing keeps
		// `post_content` intact, so counting a trashed page as "already made"
		// would block creation *and* block drafting (nothing published carries
		// the shortcode), leaving the button reporting "0 created" on every
		// press with the conflict still live and no way out from the UI.
		$statuses = array_values(
			array_diff( array_keys( get_post_stati() ), array( 'trash', 'auto-draft' ) )
		);

		$pages = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => $statuses,
				'numberposts'      => -1,
				'suppress_filters' => false,
			)
		);

		foreach ( (array) $pages as $page ) {
			if ( in_array( (int) $page->ID, $woo_ids, true ) ) {
				continue;
			}
			if ( has_shortcode( (string) $page->post_content, $shortcode ) ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * The first *published* page carrying a shortcode that WooCommerce does not own.
	 *
	 * @param string $shortcode Shortcode tag.
	 * @return WP_Post|null
	 */
	private static function non_woo_page_with_shortcode( $shortcode ) {
		$woo_ids = array_map( 'intval', array_values( self::woo_page_ids() ) );

		foreach ( get_pages() as $page ) {
			if ( in_array( (int) $page->ID, $woo_ids, true ) ) {
				continue;
			}
			if ( has_shortcode( (string) $page->post_content, $shortcode ) ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * The DeloPay catalog page, or `null` when there is none.
	 *
	 * @return string|null
	 */
	private static function storefront_url() {
		return self::page_url( 'delopay_products' );
	}

	/**
	 * Is this URL the one currently being served?
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	private function is_current_url( $url ) {
		if ( null === $url || '' === $url ) {
			return false;
		}

		// Only consult `get_permalink()` where the queried object is genuinely a
		// post. On a taxonomy archive the queried id is a *term* id, and
		// `get_permalink()` would happily resolve whatever post happens to carry
		// that number — a guard answering about the wrong object.
		if ( is_singular() || is_page() ) {
			$queried = get_queried_object_id();
			if ( $queried && self::path_of( (string) get_permalink( $queried ) ) === self::path_of( $url ) ) {
				return true;
			}
		}

		$request = self::request_uri();
		if ( '' === $request ) {
			return false;
		}

		$target_path = self::path_of( $url );

		return '' !== $target_path && self::path_of( $request ) === $target_path;
	}

	/* ----------------------------- Notices ---------------------------- */

	/**
	 * Warn about a DeloPay shortcode living on a WooCommerce page.
	 *
	 * Shown on every admin screen, like the test-mode notice: a checkout that
	 * redirects away is not something to discover only after opening the
	 * DeloPay menu.
	 */
	public function conflict_notice(): void {
		if ( ! current_user_can( Delopay_Admin_UI::CAP ) ) {
			return;
		}

		$conflicts = self::shortcode_conflicts();
		if ( empty( $conflicts ) ) {
			return;
		}

		// Two kinds of conflict, and only one of them has a button.
		//
		// A page this action cannot draft — the homepage, or a page that also
		// serves another DeloPay shortcode such as the catalog — would stay
		// conflicted after the click, leaving a notice that never clears and a
		// button that does nothing on every press. Those are named instead, with
		// what has to be moved.
		$fixable   = array();
		$manual    = array();
		$unpublish = array();

		// Bucketed per *page*, not per conflict entry. One Woo page can carry
		// both shortcodes, and the blocker belongs to the page: if either
		// shortcode is waiting on an unpublished replacement, the page cannot
		// come down, and telling the merchant to hand-edit one shortcode while
		// telling them to publish a page for the other is two instructions for
		// one problem — one of which is false.
		$pending_by_page = array();
		foreach ( $conflicts as $conflict ) {
			if ( ! self::blocked_only_by_unpublished_replacement( $conflict ) ) {
				continue;
			}
			$replacement = self::unpublished_replacement_for( $conflict['shortcode'] );
			if ( $replacement ) {
				$pending_by_page[ (int) $conflict['id'] ][ $conflict['shortcode'] ] = $replacement;
			}
		}

		foreach ( $conflicts as $conflict ) {
			if ( self::can_relocate_from( $conflict['id'] ) ) {
				$fixable[] = $conflict;
				continue;
			}
			// Three buckets, not two: "your replacement page exists but is not
			// published" needs its own instruction. Telling that merchant to
			// move the shortcode onto a page of its own — the manual copy —
			// sends them to build a second copy of a page they already have.
			$page_pending = isset( $pending_by_page[ (int) $conflict['id'] ] )
				? $pending_by_page[ (int) $conflict['id'] ]
				: array();
			if ( ! empty( $page_pending ) ) {
				foreach ( $page_pending as $tag => $replacement ) {
					$unpublish[] = array(
						'id'          => $conflict['id'],
						'shortcode'   => $tag,
						'replacement' => $replacement,
					);
				}
				unset( $pending_by_page[ (int) $conflict['id'] ] );
				continue;
			}
			// Already emitted above for this page, or genuinely manual.
			if ( ! isset( $pending_by_page[ (int) $conflict['id'] ] ) && self::page_had_pending( $unpublish, $conflict['id'] ) ) {
				continue;
			}
			$manual[] = $conflict;
		}

		$has_checkout = false;
		foreach ( $conflicts as $conflict ) {
			if ( 'checkout' === $conflict['woo_page'] ) {
				$has_checkout = true;
				break;
			}
		}

		$fix_url = wp_nonce_url(
			add_query_arg( 'action', 'delopay_woo_split', Delopay_Admin_UI::admin_post_url() ),
			'delopay_woo_split'
		);
		?>
		<div class="notice notice-<?php echo esc_attr( $has_checkout ? 'error' : 'warning' ); ?>">
			<p>
				<strong><?php esc_html_e( 'DeloPay shortcodes are on WooCommerce pages.', 'delopay' ); ?></strong>
				<?php if ( $has_checkout ) : ?>
					<?php esc_html_e( 'WooCommerce redirects its checkout page to its cart page whenever its own cart is empty — and it is always empty, because the DeloPay cart lives in the browser and WooCommerce cannot see it. Your checkout is unreachable.', 'delopay' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'WooCommerce controls these pages and can redirect them out from under the shortcode.', 'delopay' ); ?>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $fixable ) ) : ?>
				<ul class="ul-disc">
					<?php foreach ( $fixable as $conflict ) : ?>
						<li>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $conflict['id'] ) ); ?>">
								<?php echo esc_html( $conflict['title'] ); ?>
							</a>
							<code>[<?php echo esc_html( $conflict['shortcode'] ); ?>]</code>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $fix_url ); ?>">
						<?php esc_html_e( 'Give each shortcode its own page', 'delopay' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $unpublish ) ) : ?>
				<p>
					<?php esc_html_e( 'These already have a DeloPay page waiting — it just is not published yet. Publish it, then press "Give each shortcode its own page" so DeloPay can take the WooCommerce page down:', 'delopay' ); ?>
				</p>
				<ul class="ul-disc">
					<?php foreach ( $unpublish as $conflict ) : ?>
						<li>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $conflict['replacement']->ID ) ); ?>">
								<?php echo esc_html( get_the_title( $conflict['replacement'] ) ); ?>
							</a>
							<?php
							printf(
								/* translators: 1: post status, e.g. pending; 2: shortcode tag */
								esc_html__( '— currently %1$s, and it is where %2$s should live.', 'delopay' ),
								'<code>' . esc_html( (string) get_post_status( $conflict['replacement'] ) ) . '</code>',
								'<code>[' . esc_html( $conflict['shortcode'] ) . ']</code>'
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $manual ) ) : ?>
				<p>
					<?php esc_html_e( 'These have to be moved by hand — DeloPay will not take the page down, because it is either your homepage or it also serves part of your DeloPay storefront:', 'delopay' ); ?>
				</p>
				<ul class="ul-disc">
					<?php foreach ( $manual as $conflict ) : ?>
						<li>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $conflict['id'] ) ); ?>">
								<?php echo esc_html( $conflict['title'] ); ?>
							</a>
							<?php
							printf(
								/* translators: %s: shortcode tag, e.g. delopay_checkout */
								esc_html__( '— move %s onto a page of its own.', 'delopay' ),
								'<code>[' . esc_html( $conflict['shortcode'] ) . ']</code>'
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
