<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Admin {

	const MENU_SLUG       = 'delopay';
	const SLUG_PRODUCTS   = 'delopay-products';
	const SLUG_CATEGORIES = 'delopay-categories';
	const SLUG_ORDERS     = 'delopay-orders';
	const SLUG_DISPUTES   = 'delopay-disputes';
	const SLUG_BUSINESS   = 'delopay-business';
	const SLUG_BRANDING   = 'delopay-branding';
	const SLUG_SETTINGS   = 'delopay-settings';
	const CAP             = Delopay_Admin_UI::CAP;
	const SETTINGS_GROUP  = 'delopay_settings_group';

	private static $instance = null;

	/**
	 * Registered admin pages, keyed by slug.
	 *
	 * @var Delopay_Admin_Page[]
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
			self::MENU_SLUG       => new Delopay_Admin_Page_Dashboard(),
			self::SLUG_PRODUCTS   => new Delopay_Admin_Page_Products(),
			self::SLUG_CATEGORIES => new Delopay_Admin_Page_Categories(),
			self::SLUG_ORDERS     => new Delopay_Admin_Page_Orders(),
			self::SLUG_DISPUTES   => new Delopay_Admin_Page_Disputes(),
			self::SLUG_BUSINESS   => new Delopay_Admin_Page_Business(),
			self::SLUG_BRANDING   => new Delopay_Admin_Page_Branding(),
			self::SLUG_SETTINGS   => new Delopay_Admin_Page_Settings(),
		);

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'config_warning' ) );

		Delopay_Admin_Handlers::instance();
	}

	public function register_menu() {
		$dashboard = $this->pages[ self::MENU_SLUG ];
		add_menu_page(
			__( 'DeloPay', 'delopay' ),
			__( 'DeloPay', 'delopay' ),
			self::CAP,
			self::MENU_SLUG,
			array( $dashboard, 'render' ),
			DELOPAY_URL . 'assets/img/menu-icon.svg',
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

		wp_enqueue_style( 'delopay-tokens', DELOPAY_URL . 'assets/css/delopay-tokens.css', array(), Delopay_Plugin::asset_version( 'assets/css/delopay-tokens.css' ) );
		wp_enqueue_style( 'delopay-admin', DELOPAY_URL . 'assets/css/delopay-admin.css', array( 'delopay-tokens' ), Delopay_Plugin::asset_version( 'assets/css/delopay-admin.css' ) );

		if ( false !== strpos( (string) $hook, self::SLUG_PRODUCTS ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_script( 'delopay-admin', DELOPAY_URL . 'assets/js/delopay-admin.js', array( 'jquery' ), Delopay_Plugin::asset_version( 'assets/js/delopay-admin.js' ), true );
		wp_localize_script(
			'delopay-admin',
			'DelopayAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'delopay/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => self::admin_i18n(),
			)
		);
	}

	private static function admin_i18n() {
		return array(
			'confirmRefund'     => __( 'Refund this order? This cannot be undone.', 'delopay' ),
			'refunding'         => __( 'Refunding…', 'delopay' ),
			'refundOk'          => __( 'Refund submitted.', 'delopay' ),
			'refundFail'        => __( 'Refund failed: ', 'delopay' ),
			'confirmCapture'    => __( 'Capture this payment?', 'delopay' ),
			'capturing'         => __( 'Capturing…', 'delopay' ),
			'captureOk'         => __( 'Captured.', 'delopay' ),
			'captureFail'       => __( 'Capture failed: ', 'delopay' ),
			'confirmCancel'     => __( 'Cancel this payment? This releases the authorization.', 'delopay' ),
			'cancelling'        => __( 'Cancelling…', 'delopay' ),
			'cancelOk'          => __( 'Payment cancelled.', 'delopay' ),
			'cancelFail'        => __( 'Cancel failed: ', 'delopay' ),
			'pickImage'         => __( 'Choose product image', 'delopay' ),
			'useImage'          => __( 'Use this image', 'delopay' ),
			'imageLoadFailed'   => __( 'Could not load image from that URL.', 'delopay' ),
			'confirmDelete'     => __( 'Delete this product? This cannot be undone.', 'delopay' ),
			'connectOpening'    => __( 'Opening DeloPay control center…', 'delopay' ),
			'connectWaiting'    => __( 'Waiting for you to finish in the control center…', 'delopay' ),
			'connectPopupBlock' => __( 'Popup blocked. Allow popups for this site and try again.', 'delopay' ),
			'connectFailed'     => __( 'Could not start the connect flow: ', 'delopay' ),
			'connectError'      => __( 'Connection failed: ', 'delopay' ),
			'connectOk'         => __( 'Connected. Reloading…', 'delopay' ),
			'connectCancelled'  => __( 'Connection cancelled.', 'delopay' ),
			'confirmDisconnect' => __( 'Disconnect this site from DeloPay? The API key will be revoked. You can reconnect at any time.', 'delopay' ),
			'disconnecting'     => __( 'Disconnecting…', 'delopay' ),
			'disconnectFailed'  => __( 'Could not disconnect: ', 'delopay' ),
		);
	}

	public function config_warning() {
		if ( Delopay_Settings::is_configured() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, self::MENU_SLUG ) ) {
			return;
		}
		$settings_url = Delopay_Admin_UI::page_url( self::SLUG_SETTINGS );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'DeloPay is not connected yet.', 'delopay' ); ?></strong>
				<?php
				printf(
					/* translators: %s = link to settings */
					esc_html__( 'Click %s to link this site to your DeloPay account in one step.', 'delopay' ),
					'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Connect to DeloPay', 'delopay' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
