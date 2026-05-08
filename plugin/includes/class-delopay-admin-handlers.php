<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Admin_Handlers {

	const IMPORT_MAX_BYTES = 5242880;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		foreach ( self::actions() as $action => $method ) {
			add_action( 'admin_post_' . $action, array( $this, $method ) );
		}
	}

	private static function actions() {
		return array(
			'wp_delopay_save_product'    => 'save_product',
			'wp_delopay_delete_product'  => 'delete_product',
			'wp_delopay_export_products' => 'export_products',
			'wp_delopay_import_products' => 'import_products',
			'wp_delopay_save_category'   => 'save_category',
			'wp_delopay_delete_category' => 'delete_category',
		);
	}

	public function save_product() {
		WP_Delopay_Admin_UI::require_cap();
		check_admin_referer( 'wp_delopay_save_product', 'wp_delopay_product_nonce' );

		$id     = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$action = $id > 0 ? 'edit' : 'new';

		$clean = WP_Delopay_Products::sanitize_input( $_POST, $id );
		if ( is_wp_error( $clean ) ) {
			$this->redirect_form_error( 'product', $clean->get_error_message(), $action, $id );
		}

		$result = $id > 0
			? WP_Delopay_Products::update( $id, $clean )
			: WP_Delopay_Products::create( $clean );

		if ( is_wp_error( $result ) ) {
			$this->redirect_form_error( 'product', $result->get_error_message(), $action, $id );
		}

		WP_Delopay_Admin_UI::redirect(
			array(
				'page'    => WP_Delopay_Admin::SLUG_PRODUCTS,
				'action'  => 'edit',
				'product' => (int) $result['id'],
				'flash'   => $id > 0 ? 'updated' : 'created',
			)
		);
	}

	public function delete_product() {
		WP_Delopay_Admin_UI::require_cap();
		$id = isset( $_GET['product'] ) ? (int) $_GET['product'] : 0;
		check_admin_referer( 'wp_delopay_delete_product_' . $id );

		WP_Delopay_Products::delete( $id );

		WP_Delopay_Admin_UI::redirect(
			array(
				'page'  => WP_Delopay_Admin::SLUG_PRODUCTS,
				'flash' => 'deleted',
			)
		);
	}

	public function export_products() {
		WP_Delopay_Admin_UI::require_cap();
		check_admin_referer( 'wp_delopay_export_products' );

		$payload = array(
			'version'     => 2,
			'exported_at' => gmdate( 'c' ),
			'site_url'    => home_url( '/' ),
			'categories'  => array_map( array( $this, 'shape_category_for_export' ), WP_Delopay_Categories::list_all() ),
			'products'    => array_map( array( $this, 'shape_product_for_export' ), WP_Delopay_Products::list_all( array( 'limit' => 10000 ) ) ),
		);

		$filename = 'delopay-products-' . gmdate( 'Ymd-His' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	private function shape_product_for_export( $p ) {
		return array(
			'name'          => $p['name'],
			'sku'           => $p['sku'],
			'description'   => $p['description'],
			'price_minor'   => (int) $p['price_minor'],
			'currency'      => $p['currency'],
			'status'        => $p['status'],
			'sort_order'    => (int) $p['sort_order'],
			'image_url'     => $p['image_url'],
			'category_slug' => (string) ( $p['category_slug'] ?? '' ),
		);
	}

	private function shape_category_for_export( $c ) {
		return array(
			'slug'        => $c['slug'],
			'name'        => $c['name'],
			'description' => $c['description'],
			'status'      => $c['status'],
			'sort_order'  => (int) $c['sort_order'],
		);
	}

	public function import_products() {
		WP_Delopay_Admin_UI::require_cap();
		check_admin_referer( 'wp_delopay_import_products', 'wp_delopay_import_nonce' );

		$contents = $this->read_uploaded_import_file();
		if ( is_wp_error( $contents ) ) {
			$this->redirect_import_error( $contents->get_error_message() );
		}

		$data = json_decode( $contents, true );
		if ( ! is_array( $data ) ) {
			$this->redirect_import_error( __( 'The file is not valid JSON.', 'wp-delopay' ) );
		}

		$items = isset( $data['products'] ) && is_array( $data['products'] ) ? $data['products'] : $data;
		$cats  = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
		if ( ! is_array( $items ) || empty( $items ) ) {
			$this->redirect_import_error( __( 'No products found in the file.', 'wp-delopay' ) );
		}

		$cat_stats  = $this->import_categories( $cats );
		$prod_stats = $this->import_product_rows( $items );

		$msg = ! empty( $cats )
			? sprintf(
				/* translators: 1: products created, 2: skipped, 3: products failed, 4: categories created, 5: categories updated, 6: categories failed */
				__( 'Import complete: %1$d products created, %2$d skipped (existing SKU), %3$d failed; %4$d categories created, %5$d updated, %6$d failed.', 'wp-delopay' ),
				$prod_stats['created'],
				$prod_stats['skipped'],
				$prod_stats['failed'],
				$cat_stats['created'],
				$cat_stats['updated'],
				$cat_stats['failed']
			)
			: sprintf(
				/* translators: 1: created count, 2: skipped count, 3: failed count */
				__( 'Import complete: %1$d created, %2$d skipped (existing SKU), %3$d failed.', 'wp-delopay' ),
				$prod_stats['created'],
				$prod_stats['skipped'],
				$prod_stats['failed']
			);

		$any_created = $prod_stats['created'] + $cat_stats['created'] + $cat_stats['updated'];
		$any_failed  = $prod_stats['failed'] + $cat_stats['failed'];
		$flash       = $any_failed > 0 && 0 === $any_created ? 'import_error' : 'imported';

		WP_Delopay_Admin_UI::redirect(
			array(
				'page'  => WP_Delopay_Admin::SLUG_PRODUCTS,
				'flash' => $flash,
				'msg'   => rawurlencode( $msg ),
			)
		);
	}

	/*
	 * Nonce + capability checks live in the import_products() entry point that
	 * calls this helper (see check_admin_referer above). The $_FILES superglobal
	 * fields used here are populated by PHP itself (server-controlled) so the
	 * sanitize/validate sniffs do not apply.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	 */
	private function read_uploaded_import_file() {
		if ( empty( $_FILES['import_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['import_file']['error'] ) {
			return new WP_Error( 'no_file', __( 'No file uploaded or upload failed.', 'wp-delopay' ) );
		}

		$size = isset( $_FILES['import_file']['size'] ) ? (int) $_FILES['import_file']['size'] : 0;
		if ( $size <= 0 || $size > self::IMPORT_MAX_BYTES ) {
			return new WP_Error(
				'bad_size',
				sprintf(
				/* translators: %s: max file size like "5 MB" */
					__( 'File is too large or empty (max %s).', 'wp-delopay' ),
					size_format( self::IMPORT_MAX_BYTES )
				)
			);
		}

		$tmp_path     = $_FILES['import_file']['tmp_name'];
		$orig_name    = isset( $_FILES['import_file']['name'] ) ? sanitize_file_name( (string) $_FILES['import_file']['name'] ) : 'upload.json';
		$check        = wp_check_filetype_and_ext( $tmp_path, $orig_name, array( 'json' => 'application/json' ) );
		$ext_ok       = ! empty( $check['ext'] ) && 'json' === $check['ext'];
		$ext_fallback = strtolower( (string) pathinfo( $orig_name, PATHINFO_EXTENSION ) ) === 'json';
		if ( ! $ext_ok && ! $ext_fallback ) {
			return new WP_Error( 'bad_ext', __( 'File must be a .json export.', 'wp-delopay' ) );
		}

		// Reading a local uploaded temp file, not a remote URL — wp_remote_get() does not apply.
		$contents = file_get_contents( $tmp_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents || '' === $contents ) {
			return new WP_Error( 'empty', __( 'The uploaded file is empty.', 'wp-delopay' ) );
		}

		return $contents;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	private function import_categories( array $cats ) {
		$stats = array(
			'created' => 0,
			'updated' => 0,
			'failed'  => 0,
		);
		foreach ( $cats as $cat_item ) {
			if ( ! is_array( $cat_item ) ) {
				++$stats['failed'];
				continue;
			}
			unset( $cat_item['image_id'], $cat_item['id'], $cat_item['created_at'], $cat_item['updated_at'] );
			$existing_before = WP_Delopay_Categories::find( (string) ( $cat_item['slug'] ?? '' ), false );
			$result          = WP_Delopay_Categories::upsert_by_slug( $cat_item );
			if ( is_wp_error( $result ) || null === $result ) {
				++$stats['failed'];
			} elseif ( $existing_before ) {
				++$stats['updated'];
			} else {
				++$stats['created'];
			}
		}
		return $stats;
	}

	private function import_product_rows( array $items ) {
		$stats = array(
			'created' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				++$stats['failed'];
				continue;
			}
			unset( $item['image_id'], $item['id'], $item['created_at'], $item['updated_at'] );

			$clean = WP_Delopay_Products::sanitize_input( $item, 0 );
			if ( is_wp_error( $clean ) ) {
				++$stats[ 'sku_taken' === $clean->get_error_code() ? 'skipped' : 'failed' ];
				continue;
			}
			$result = WP_Delopay_Products::create( $clean );
			++$stats[ is_wp_error( $result ) ? 'failed' : 'created' ];
		}
		return $stats;
	}

	public function save_category() {
		WP_Delopay_Admin_UI::require_cap();
		check_admin_referer( 'wp_delopay_save_category', 'wp_delopay_category_nonce' );

		$id     = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
		$action = $id > 0 ? 'edit' : 'new';

		$clean = WP_Delopay_Categories::sanitize_input( $_POST, $id );
		if ( is_wp_error( $clean ) ) {
			$this->redirect_form_error( 'category', $clean->get_error_message(), $action, $id );
		}

		$result = $id > 0
			? WP_Delopay_Categories::update( $id, $clean )
			: WP_Delopay_Categories::create( $clean );

		if ( is_wp_error( $result ) ) {
			$this->redirect_form_error( 'category', $result->get_error_message(), $action, $id );
		}

		WP_Delopay_Plugin::ensure_category_page( $result );
		do_action( 'wp_delopay_category_saved', $result, $id > 0 ? 'updated' : 'created' );

		WP_Delopay_Admin_UI::redirect(
			array(
				'page'     => WP_Delopay_Admin::SLUG_CATEGORIES,
				'action'   => 'edit',
				'category' => (int) $result['id'],
				'flash'    => $id > 0 ? 'cat_updated' : 'cat_created',
			)
		);
	}

	public function delete_category() {
		WP_Delopay_Admin_UI::require_cap();
		$id = isset( $_GET['category'] ) ? (int) $_GET['category'] : 0;
		check_admin_referer( 'wp_delopay_delete_category_' . $id );

		$result = WP_Delopay_Categories::delete( $id, true );
		if ( is_wp_error( $result ) ) {
			WP_Delopay_Admin_UI::redirect(
				array(
					'page'  => WP_Delopay_Admin::SLUG_CATEGORIES,
					'flash' => 'cat_error',
					'msg'   => rawurlencode( $result->get_error_message() ),
				)
			);
		}

		WP_Delopay_Admin_UI::redirect(
			array(
				'page'  => WP_Delopay_Admin::SLUG_CATEGORIES,
				'flash' => 'cat_deleted',
			)
		);
	}

	private function redirect_form_error( $kind, $message, $action, $id ) {
		$config = array(
			'product'  => array(
				'page'     => WP_Delopay_Admin::SLUG_PRODUCTS,
				'id_param' => 'product',
				'flash'    => 'error',
			),
			'category' => array(
				'page'     => WP_Delopay_Admin::SLUG_CATEGORIES,
				'id_param' => 'category',
				'flash'    => 'cat_error',
			),
		)[ $kind ];

		WP_Delopay_Admin_UI::redirect(
			array(
				'page'              => $config['page'],
				'action'            => $action,
				$config['id_param'] => $id > 0 ? $id : null,
				'flash'             => $config['flash'],
				'msg'               => rawurlencode( $message ),
			)
		);
	}

	private function redirect_import_error( $message ) {
		WP_Delopay_Admin_UI::redirect(
			array(
				'page'  => WP_Delopay_Admin::SLUG_PRODUCTS,
				'flash' => 'import_error',
				'msg'   => rawurlencode( $message ),
			)
		);
	}
}
