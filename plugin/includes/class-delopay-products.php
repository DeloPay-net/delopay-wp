<?php
/**
 * Products data layer.
 *
 * Operates directly against the plugin's own custom tables. The DB sniffs are
 * disabled file-wide because we own the schema (wp_cache_* would just shadow
 * data we already control) and $wpdb->prepare() does not accept table names
 * as placeholders, forcing {$table} interpolation. SchemaChange fires on the
 * dbDelta() call site that creates/upgrades our tables.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Products {

	const STATUS_ACTIVE      = 'active';
	const STATUS_DRAFT       = 'draft';
	const SLUG_UNCATEGORIZED = '-';
	const MAX_REF_LENGTH     = 255;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public static function find( $product_ref, $only_active = true ) {
		global $wpdb;
		$table = WP_Delopay_Orders::table_products();

		if ( is_numeric( $product_ref ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", (int) $product_ref ),
				ARRAY_A
			);
		} else {
			$ref = (string) $product_ref;
			if ( '' === $ref || strlen( $ref ) > self::MAX_REF_LENGTH ) {
				return null;
			}
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE sku = %s LIMIT 1", $ref ),
				ARRAY_A
			);
		}

		if ( ! $row ) {
			return null;
		}
		if ( $only_active && self::STATUS_ACTIVE !== $row['status'] ) {
			return null;
		}
		return self::hydrate( $row );
	}

	public static function find_for_admin( $id ) {
		return self::find( $id, false );
	}

	public static function list_published( $limit = 50, $category_filter = null ) {
		return self::query_products(
			array(
				'only_active' => true,
				'category'    => $category_filter,
				'limit'       => $limit,
			)
		);
	}

	public static function list_all( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'category' => '',
				'limit'    => 100,
				'offset'   => 0,
			)
		);

		return self::query_products(
			array(
				'search'   => (string) $args['search'],
				'status'   => (string) $args['status'],
				'category' => (string) $args['category'],
				'limit'    => (int) $args['limit'],
				'offset'   => (int) $args['offset'],
			)
		);
	}

	private static function query_products( array $opts ) {
		global $wpdb;
		$table = WP_Delopay_Orders::table_products();

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $opts['only_active'] ) ) {
			$where[]  = 'status = %s';
			$params[] = self::STATUS_ACTIVE;
		} elseif ( in_array( $opts['status'] ?? '', array( self::STATUS_ACTIVE, self::STATUS_DRAFT ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $opts['status'];
		}

		if ( ! empty( $opts['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $opts['search'] ) . '%';
			$where[]  = '(name LIKE %s OR sku LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$category_clause = self::resolve_category_filter( $opts['category'] ?? null );
		if ( false === $category_clause ) {
			return array();
		}
		if ( null !== $category_clause ) {
			$where[] = $category_clause['sql'];
			if ( null !== $category_clause['param'] ) {
				$params[] = $category_clause['param'];
			}
		}

		// $table is $wpdb->prefix . 'delopay_products'; $where entries are static SQL fragments with %s/%d/%i placeholders bound via $params.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql      = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) .
			' ORDER BY sort_order ASC, created_at DESC LIMIT %d';
		$params[] = max( 1, (int) ( $opts['limit'] ?? 50 ) );

		if ( isset( $opts['offset'] ) ) {
			$sql     .= ' OFFSET %d';
			$params[] = (int) $opts['offset'];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return self::hydrate_many( (array) $rows );
	}

	private static function hydrate_many( array $rows ) {
		$category_ids = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['category_id'] ) ) {
				$category_ids[] = (int) $row['category_id'];
			}
		}
		$category_map = WP_Delopay_Categories::find_many( $category_ids );
		$out          = array();
		foreach ( $rows as $row ) {
			$out[] = self::hydrate( $row, $category_map );
		}
		return $out;
	}

	private static function resolve_category_filter( $category ) {
		if ( null === $category || '' === (string) $category ) {
			return null;
		}
		if ( '-' === $category || 'uncategorized' === $category ) {
			return array(
				'sql'   => 'category_id IS NULL',
				'param' => null,
			);
		}
		if ( is_numeric( $category ) ) {
			return array(
				'sql'   => 'category_id = %d',
				'param' => (int) $category,
			);
		}
		$cat = WP_Delopay_Categories::find( (string) $category, false );
		if ( ! $cat ) {
			return false;
		}
		return array(
			'sql'   => 'category_id = %d',
			'param' => (int) $cat['id'],
		);
	}

	public static function count_all( $status = '' ) {
		global $wpdb;
		$table = WP_Delopay_Orders::table_products();
		if ( in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_DRAFT ), true ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function sanitize_input( $input, $existing_id = 0 ) {
		$name = isset( $input['name'] ) ? trim( wp_unslash( (string) $input['name'] ) ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'name_required', __( 'Name is required.', 'wp-delopay' ) );
		}

		$sku = self::clean_sku( $input );
		if ( is_wp_error( $sku ) ) {
			return $sku;
		}
		if ( '' !== $sku && self::sku_in_use( $sku, $existing_id ) ) {
			/* translators: %s: the SKU that conflicts with an existing product. */
			return new WP_Error( 'sku_taken', sprintf( __( 'SKU "%s" is already used by another product.', 'wp-delopay' ), $sku ) );
		}

		$price_minor = self::clean_price( $input );
		if ( is_wp_error( $price_minor ) ) {
			return $price_minor;
		}

		$currency = self::clean_currency( $input );
		if ( is_wp_error( $currency ) ) {
			return $currency;
		}

		$image = self::clean_image( $input );
		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$category_id = self::resolve_category_input( $input );

		return array(
			'name'        => sanitize_text_field( $name ),
			'sku'         => '' === $sku ? null : $sku,
			'description' => isset( $input['description'] ) ? wp_kses_post( wp_unslash( $input['description'] ) ) : '',
			'price_minor' => $price_minor,
			'currency'    => $currency,
			'image_id'    => $image['id'] ? $image['id'] : null,
			'image_url'   => '' === $image['url'] ? null : $image['url'],
			'status'      => isset( $input['status'] ) && self::STATUS_DRAFT === $input['status'] ? self::STATUS_DRAFT : self::STATUS_ACTIVE,
			'sort_order'  => isset( $input['sort_order'] ) ? (int) $input['sort_order'] : 0,
			'category_id' => $category_id,
		);
	}

	private static function clean_sku( $input ) {
		return isset( $input['sku'] ) ? trim( sanitize_text_field( wp_unslash( $input['sku'] ) ) ) : '';
	}

	private static function clean_price( $input ) {
		$price_decimal = isset( $input['price'] )
			? (float) wp_unslash( $input['price'] )
			: ( isset( $input['price_minor'] ) ? ( (int) $input['price_minor'] ) / 100 : 0 );
		if ( $price_decimal < 0 || ! is_finite( $price_decimal ) ) {
			return new WP_Error( 'price_invalid', __( 'Price must be a non-negative number.', 'wp-delopay' ) );
		}
		return (int) round( $price_decimal * 100 );
	}

	private static function clean_currency( $input ) {
		$currency = isset( $input['currency'] )
			? strtoupper( substr( sanitize_text_field( wp_unslash( $input['currency'] ) ), 0, 3 ) )
			: '';
		if ( '' === $currency ) {
			$currency = strtoupper( (string) WP_Delopay_Settings::get( 'currency' ) );
		}
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			return new WP_Error( 'currency_invalid', __( 'Currency must be a 3-letter ISO 4217 code.', 'wp-delopay' ) );
		}
		return $currency;
	}

	private static function clean_image( $input ) {
		$image_id = isset( $input['image_id'] ) ? max( 0, (int) $input['image_id'] ) : 0;

		$image_url_raw = isset( $input['image_url'] ) ? trim( wp_unslash( (string) $input['image_url'] ) ) : '';
		$image_url     = '';
		if ( '' !== $image_url_raw ) {
			$image_url = esc_url_raw( $image_url_raw, array( 'http', 'https' ) );
			if ( '' === $image_url ) {
				return new WP_Error( 'image_url_invalid', __( 'Image URL must be a valid http(s) URL.', 'wp-delopay' ) );
			}
		}
		if ( $image_id > 0 ) {
			$image_url = '';
		}
		return array(
			'id'  => $image_id,
			'url' => $image_url,
		);
	}

	private static function resolve_category_input( $input ) {
		$ref = '';
		foreach ( array( 'category_id', 'category_slug', 'category' ) as $key ) {
			if ( isset( $input[ $key ] ) && '' !== (string) $input[ $key ] ) {
				$ref = (string) $input[ $key ];
				break;
			}
		}

		$explicit_uncat = in_array( $ref, array( '-', 'uncategorized', '0' ), true );
		if ( $explicit_uncat ) {
			return null;
		}

		if ( '' !== $ref ) {
			$cat = is_numeric( $ref )
				? WP_Delopay_Categories::find_for_admin( (int) $ref )
				: WP_Delopay_Categories::find( $ref, false );
			if ( $cat ) {
				return (int) $cat['id'];
			}
		}

		$home = WP_Delopay_Categories::find( 'home', false );
		return $home ? (int) $home['id'] : null;
	}

	public static function create( $clean ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$row = array_merge(
			$clean,
			array(
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		if ( false === $wpdb->insert( WP_Delopay_Orders::table_products(), $row ) ) {
			return new WP_Error( 'db_error', __( 'Could not create product.', 'wp-delopay' ) );
		}
		return self::find_for_admin( (int) $wpdb->insert_id );
	}

	public static function update( $id, $clean ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! self::find_for_admin( $id ) ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'wp-delopay' ) );
		}
		$row               = $clean;
		$row['updated_at'] = current_time( 'mysql', true );

		if ( false === $wpdb->update( WP_Delopay_Orders::table_products(), $row, array( 'id' => $id ) ) ) {
			return new WP_Error( 'db_error', __( 'Could not update product.', 'wp-delopay' ) );
		}
		return self::find_for_admin( $id );
	}

	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( WP_Delopay_Orders::table_products(), array( 'id' => (int) $id ) );
	}

	public static function set_status( $id, $status ) {
		if ( ! in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_DRAFT ), true ) ) {
			return false;
		}
		global $wpdb;
		$wpdb->update(
			WP_Delopay_Orders::table_products(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
		return true;
	}

	private static function sku_in_use( $sku, $exclude_id = 0 ) {
		global $wpdb;
		$table = WP_Delopay_Orders::table_products();
		$found = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE sku = %s AND id <> %d LIMIT 1", $sku, (int) $exclude_id )
		);
		return $found > 0;
	}

	public static function hydrate( $row, ?array $category_map = null ) {
		$row['id']          = (int) $row['id'];
		$row['price_minor'] = (int) $row['price_minor'];
		$row['image_id']    = $row['image_id'] ? (int) $row['image_id'] : 0;
		$row['sort_order']  = isset( $row['sort_order'] ) ? (int) $row['sort_order'] : 0;

		$external_url              = isset( $row['image_url'] ) ? (string) $row['image_url'] : '';
		$row['image_url_external'] = $external_url;
		if ( $row['image_id'] ) {
			$row['image_url']     = (string) wp_get_attachment_image_url( $row['image_id'], 'large' );
			$row['thumbnail_url'] = (string) wp_get_attachment_image_url( $row['image_id'], 'thumbnail' );
		} else {
			$row['image_url']     = $external_url;
			$row['thumbnail_url'] = $external_url;
		}

		$row['description'] = (string) $row['description'];
		$row['excerpt']     = wp_trim_words( wp_strip_all_tags( $row['description'] ), 30, '…' );
		$row['permalink']   = home_url( '/' );
		$row['sku']         = isset( $row['sku'] ) ? (string) $row['sku'] : '';
		$row['currency']    = strtoupper( (string) $row['currency'] );
		$row['status']      = (string) $row['status'];

		$row['category_id']   = isset( $row['category_id'] ) && $row['category_id'] ? (int) $row['category_id'] : 0;
		$row['category_slug'] = '';
		$row['category_name'] = '';
		if ( $row['category_id'] > 0 && class_exists( 'WP_Delopay_Categories' ) ) {
			$cat = null !== $category_map
				? ( $category_map[ $row['category_id'] ] ?? null )
				: WP_Delopay_Categories::find_for_admin( $row['category_id'] );
			if ( $cat ) {
				$row['category_slug'] = (string) $cat['slug'];
				$row['category_name'] = (string) $cat['name'];
			}
		}
		return $row;
	}
}
