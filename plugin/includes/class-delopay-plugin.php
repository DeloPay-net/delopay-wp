<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Plugin {

	const RECONCILE_HOOK             = 'delopay_reconcile_refunds';
	const RECONCILE_SCHEDULE         = 'delopay_fifteen_minutes';
	const RECONCILE_INTERVAL_MINUTES = 15;
	const RECONCILE_INITIAL_DELAY    = 600;

	const HOME_CATEGORY_SLUG = 'home';
	const COMPLETE_PAGE_SLUG = 'delopay-complete';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		Delopay_Settings::instance();
		Delopay_Categories::instance();
		Delopay_Products::instance();
		Delopay_Orders::instance();
		Delopay_REST::instance();
		Delopay_Webhook::instance();
		Delopay_Connect::instance();
		Delopay_Admin::instance();
		Delopay_Shortcodes::instance();
		Delopay_Plugin_Details::instance();

		// Translations load automatically by plugin slug on WordPress.org-hosted plugins (WP 4.6+); no manual loader call needed.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// The interval is RECONCILE_INTERVAL_MINUTES * MINUTE_IN_SECONDS (15 min); the sniff can't trace the constants.
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::RECONCILE_HOOK, array( 'Delopay_Orders', 'reconcile_pending_refunds' ) );
		add_action( 'init', array( $this, 'maybe_schedule_reconciliation' ) );
	}

	public function register_cron_schedule( $schedules ) {
		if ( ! isset( $schedules[ self::RECONCILE_SCHEDULE ] ) ) {
			$schedules[ self::RECONCILE_SCHEDULE ] = array(
				'interval' => self::RECONCILE_INTERVAL_MINUTES * MINUTE_IN_SECONDS,
				'display'  => __( 'DeloPay every 15 minutes', 'delopay' ),
			);
		}
		return $schedules;
	}

	public function maybe_schedule_reconciliation() {
		if ( ! wp_next_scheduled( self::RECONCILE_HOOK ) ) {
			wp_schedule_event( time() + self::RECONCILE_INITIAL_DELAY, self::RECONCILE_SCHEDULE, self::RECONCILE_HOOK );
		}
	}

	public static function asset_version( $relative_path ) {
		$abs = DELOPAY_DIR . ltrim( $relative_path, '/' );
		if ( file_exists( $abs ) ) {
			return DELOPAY_VERSION . '.' . filemtime( $abs );
		}
		return DELOPAY_VERSION;
	}

	public static function design_css() {
		$mode  = Delopay_Settings::color_mode();
		$light = Delopay_Settings::palette( 'light' );
		$dark  = Delopay_Settings::palette( 'dark' );

		$active = Delopay_Settings::COLOR_MODE_DARK === $mode ? $dark : $light;

		$css = ':root{' . self::palette_decls( $active ) . '}';

		if ( Delopay_Settings::COLOR_MODE_AUTO === $mode ) {
			$css .= '@media(prefers-color-scheme:dark){:root{' . self::palette_decls( $dark ) . '}}';
		}

		return $css;
	}

	private static function palette_decls( array $palette ) {
		$out = '';
		foreach ( $palette as $key => $hex ) {
			$out .= '--dp-' . str_replace( '_', '-', $key ) . ':' . $hex . ';';
		}
		return $out;
	}

	public function enqueue_frontend_assets() {
		wp_register_style(
			'delopay-tokens',
			DELOPAY_URL . 'assets/css/delopay-tokens.css',
			array(),
			self::asset_version( 'assets/css/delopay-tokens.css' )
		);
		wp_register_style(
			'delopay-frontend',
			DELOPAY_URL . 'assets/css/delopay-frontend.css',
			array( 'delopay-tokens' ),
			self::asset_version( 'assets/css/delopay-frontend.css' )
		);
		wp_add_inline_style( 'delopay-frontend', self::design_css() );
		wp_register_script(
			'delopay-frontend',
			DELOPAY_URL . 'assets/js/delopay-frontend.js',
			array(),
			self::asset_version( 'assets/js/delopay-frontend.js' ),
			true
		);
		wp_localize_script(
			'delopay-frontend',
			'Delopay',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'delopay/v1/' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'checkoutBase' => Delopay_Settings::get( 'checkout_base_url' ),
				'currency'     => Delopay_Settings::get( 'currency' ),
				'completeUrl'  => Delopay_Settings::get_complete_url(),
				'i18n'         => array(
					'addToCart'       => __( 'Add to cart', 'delopay' ),
					'added'           => __( 'Added ✓', 'delopay' ),
					'cartEmpty'       => __( 'Your cart is empty.', 'delopay' ),
					'cartFetchFailed' => __( 'Could not load your cart.', 'delopay' ),
					'noCheckoutPage'  => __( 'No checkout page configured.', 'delopay' ),
					'preparing'       => __( 'Preparing secure payment…', 'delopay' ),
					'failed'          => __( 'Could not start payment.', 'delopay' ),
					'total'           => __( 'Total', 'delopay' ),
					'success'         => __( 'Payment received — thank you.', 'delopay' ),
					'failure'         => __( 'Payment failed.', 'delopay' ),
					'pending'         => __( 'Waiting for payment confirmation…', 'delopay' ),
					'willUpdate'      => __( "We'll update this page automatically when the payment confirms.", 'delopay' ),
				),
			)
		);
	}

	public static function activate() {
		require_once DELOPAY_DIR . 'includes/class-delopay-orders.php';
		require_once DELOPAY_DIR . 'includes/class-delopay-settings.php';
		require_once DELOPAY_DIR . 'includes/class-delopay-categories.php';

		Delopay_Orders::install_schema();
		Delopay_Settings::seed_defaults();
		self::ensure_home_category();
		self::ensure_complete_page();
		self::ensure_storefront_pages();

		flush_rewrite_rules();
	}

	private static function ensure_storefront_pages() {
		$pages = array(
			'cart'     => array( __( 'Cart', 'delopay' ), '[delopay_cart]' ),
			'checkout' => array( __( 'Checkout', 'delopay' ), '[delopay_checkout]' ),
		);
		foreach ( $pages as $slug => $data ) {
			list( $title, $shortcode ) = $data;
			$existing                  = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $existing && 'page' === $existing->post_type ) {
				continue;
			}
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => self::shortcode_block( $shortcode ),
				),
				false
			);
		}
	}

	private static function ensure_home_category() {
		$existing = Delopay_Categories::find( self::HOME_CATEGORY_SLUG, false );
		if ( ! $existing ) {
			$existing = Delopay_Categories::create(
				array(
					'slug'          => self::HOME_CATEGORY_SLUG,
					'name'          => __( 'Home', 'delopay' ),
					'sort_order'    => 0,
					'status'        => 'active',
					'hero_eyebrow'  => __( 'Stone-ground · Uji-sourced', 'delopay' ),
					'hero_title'    => __( 'Matcha & ceremony tools', 'delopay' ),
					'hero_subtitle' => __( 'A small, calm catalog to exercise the DeloPay checkout end-to-end.', 'delopay' ),
				)
			);
		}
		if ( $existing && ! is_wp_error( $existing ) ) {
			self::ensure_category_page( $existing );
			self::maybe_set_home_as_front_page();
		}
	}

	/**
	 * Promote the Home category page to the site's front page, but only if the
	 * site is still on the WordPress default ("Latest posts"). If the admin
	 * has already chosen a static front page we leave it alone.
	 */
	private static function maybe_set_home_as_front_page() {
		if ( 'posts' !== get_option( 'show_on_front', 'posts' ) ) {
			return;
		}
		$home_page = get_page_by_path( self::HOME_CATEGORY_SLUG, OBJECT, 'page' );
		if ( ! $home_page || 'page' !== $home_page->post_type ) {
			return;
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $home_page->ID );
	}

	public static function ensure_category_page( $category ) {
		if ( ! is_array( $category ) || empty( $category['slug'] ) ) {
			return null;
		}
		$existing = Delopay_Categories::page_url_for_slug( $category['slug'] );
		if ( $existing ) {
			return $existing;
		}

		$content = self::shortcode_block( '[delopay_category_hero category="' . $category['slug'] . '"]' )
			. "\n\n"
			. self::shortcode_block( '[delopay_products category="' . $category['slug'] . '"]' );

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $category['name'],
				'post_name'    => $category['slug'],
				'post_content' => $content,
			),
			false
		);
		if ( ! $page_id || is_wp_error( $page_id ) ) {
			return null;
		}
		return get_permalink( $page_id );
	}

	private static function shortcode_block( $shortcode ) {
		return "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->";
	}

	private static function ensure_complete_page() {
		$current_id = (int) Delopay_Settings::get( 'complete_page_id' );
		if ( $current_id > 0 && 'page' === get_post_type( $current_id ) && 'publish' === get_post_status( $current_id ) ) {
			return;
		}

		foreach ( (array) get_pages( array( 'post_status' => 'publish' ) ) as $page ) {
			if ( has_shortcode( $page->post_content, 'delopay_complete' ) ) {
				self::save_complete_page_id( (int) $page->ID );
				return;
			}
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Order complete', 'delopay' ),
				'post_name'    => self::COMPLETE_PAGE_SLUG,
				'post_content' => self::shortcode_block( '[delopay_complete]' ),
			),
			false
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			self::save_complete_page_id( (int) $page_id );
		}
	}

	private static function save_complete_page_id( $page_id ) {
		$opts = get_option( Delopay_Settings::OPTION_KEY, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		$opts['complete_page_id'] = (int) $page_id;
		update_option( Delopay_Settings::OPTION_KEY, $opts, false );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::RECONCILE_HOOK );
		flush_rewrite_rules();
	}
}
