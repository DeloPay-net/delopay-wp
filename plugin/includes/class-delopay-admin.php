<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Admin {

	const MENU_SLUG       = 'wp-delopay';
	const SLUG_PRODUCTS   = 'wp-delopay-products';
	const SLUG_CATEGORIES = 'wp-delopay-categories';
	const SLUG_ORDERS     = 'wp-delopay-orders';
	const SLUG_BUSINESS   = 'wp-delopay-business';
	const SLUG_BRANDING   = 'wp-delopay-branding';
	const SLUG_SETTINGS   = 'wp-delopay-settings';
	const CAP             = WP_Delopay_Admin_UI::CAP;
	const SETTINGS_GROUP  = 'wp_delopay_settings_group';

	private static $instance = null;

	/**
	 * Registered admin pages, keyed by slug.
	 *
	 * @var WP_Delopay_Admin_Page[]
	 */
	private $pages = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->pages = array(
			self::MENU_SLUG       => new WP_Delopay_Admin_Page_Dashboard(),
			self::SLUG_PRODUCTS   => new WP_Delopay_Admin_Page_Products(),
			self::SLUG_CATEGORIES => new WP_Delopay_Admin_Page_Categories(),
			self::SLUG_ORDERS     => new WP_Delopay_Admin_Page_Orders(),
			self::SLUG_BUSINESS   => new WP_Delopay_Admin_Page_Business(),
			self::SLUG_BRANDING   => new WP_Delopay_Admin_Page_Branding(),
			self::SLUG_SETTINGS   => new WP_Delopay_Admin_Page_Settings(),
		);

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'config_warning' ) );

		WP_Delopay_Admin_Handlers::instance();
	}

	public function register_menu() {
		$dashboard = $this->pages[ self::MENU_SLUG ];
		add_menu_page(
			__( 'DeloPay', 'wp-delopay' ),
			__( 'DeloPay', 'wp-delopay' ),
			self::CAP,
			self::MENU_SLUG,
			array( $dashboard, 'render' ),
			WP_DELOPAY_URL . 'assets/img/menu-icon.svg',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			$dashboard->label(),
			$dashboard->label(),
			self::CAP,
			self::MENU_SLUG,
			array( $dashboard, 'render' )
		);

		foreach ( $this->pages as $slug => $page ) {
			if ( self::MENU_SLUG === $slug ) {
				continue;
			}
			add_submenu_page( self::MENU_SLUG, $page->label(), $page->label(), self::CAP, $slug, array( $page, 'render' ) );
		}
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'wp-delopay-tokens', WP_DELOPAY_URL . 'assets/css/delopay-tokens.css', array(), WP_Delopay_Plugin::asset_version( 'assets/css/delopay-tokens.css' ) );
		wp_enqueue_style( 'wp-delopay-admin', WP_DELOPAY_URL . 'assets/css/delopay-admin.css', array( 'wp-delopay-tokens' ), WP_Delopay_Plugin::asset_version( 'assets/css/delopay-admin.css' ) );

		if ( false !== strpos( (string) $hook, self::SLUG_PRODUCTS ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_script( 'wp-delopay-admin', WP_DELOPAY_URL . 'assets/js/delopay-admin.js', array( 'jquery' ), WP_Delopay_Plugin::asset_version( 'assets/js/delopay-admin.js' ), true );
		wp_localize_script(
			'wp-delopay-admin',
			'WPDelopayAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'delopay/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => self::admin_i18n(),
			)
		);
	}

	private static function admin_i18n() {
		return array(
			'confirmRefund'     => __( 'Refund this order? This cannot be undone.', 'wp-delopay' ),
			'refunding'         => __( 'Refunding…', 'wp-delopay' ),
			'refundOk'          => __( 'Refund submitted.', 'wp-delopay' ),
			'refundFail'        => __( 'Refund failed: ', 'wp-delopay' ),
			'pickImage'         => __( 'Choose product image', 'wp-delopay' ),
			'useImage'          => __( 'Use this image', 'wp-delopay' ),
			'imageLoadFailed'   => __( 'Could not load image from that URL.', 'wp-delopay' ),
			'confirmDelete'     => __( 'Delete this product? This cannot be undone.', 'wp-delopay' ),
			'connectOpening'    => __( 'Opening DeloPay control center…', 'wp-delopay' ),
			'connectWaiting'    => __( 'Waiting for you to finish in the control center…', 'wp-delopay' ),
			'connectPopupBlock' => __( 'Popup blocked. Allow popups for this site and try again.', 'wp-delopay' ),
			'connectFailed'     => __( 'Could not start the connect flow: ', 'wp-delopay' ),
			'connectError'      => __( 'Connection failed: ', 'wp-delopay' ),
			'connectOk'         => __( 'Connected. Reloading…', 'wp-delopay' ),
			'connectCancelled'  => __( 'Connection cancelled.', 'wp-delopay' ),
			'confirmDisconnect' => __( 'Disconnect this site from DeloPay? The API key will be revoked. You can reconnect at any time.', 'wp-delopay' ),
			'disconnecting'     => __( 'Disconnecting…', 'wp-delopay' ),
			'disconnectFailed'  => __( 'Could not disconnect: ', 'wp-delopay' ),
		);
	}

	public function config_warning() {
		if ( WP_Delopay_Settings::is_configured() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, self::MENU_SLUG ) ) {
			return;
		}
		$settings_url = WP_Delopay_Admin_UI::page_url( self::SLUG_SETTINGS );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'DeloPay is not connected yet.', 'wp-delopay' ); ?></strong>
				<?php
				printf(
					/* translators: %s = link to settings */
					esc_html__( 'Click %s to link this site to your DeloPay account in one step.', 'wp-delopay' ),
					'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Connect to DeloPay', 'wp-delopay' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
