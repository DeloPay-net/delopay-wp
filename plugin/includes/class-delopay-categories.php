<?php
/**
 * Categories data layer.
 *
 * Operates directly against the plugin's own custom tables. The DB sniffs are
 * disabled file-wide because we own the schema (wp_cache_* would just shadow
 * data we already control) and $wpdb->prepare() does not accept table names
 * as placeholders, forcing {$table} interpolation.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Categories {

	const STATUS_ACTIVE   = 'active';
	const STATUS_DRAFT    = 'draft';
	const MAX_SLUG_LENGTH = 64;
	const RESERVED_SLUGS  = array( '-', 'uncategorized' );

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public static function find( $ref, $only_active = true ) {
		global $wpdb;
		$table = WP_Delopay_Orders::table_categories();

		if ( is_numeric( $ref ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", (int) $ref ),
				ARRAY_A
			);
		} else {
			$slug = (string) $ref;
			if ( '' === $slug || strlen( $slug ) > self::MAX_SLUG_LENGTH ) {
				return null;
			}
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug ),
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

	public static function find_many( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$table        = WP_Delopay_Orders::table_categories();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$placeholders})", $ids ),
			ARRAY_A
		);
		$out          = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['id'] ] = self::hydrate( $row );
		}
		return $out;
	}

	public static function list_published() {
		return self::query_all( self::STATUS_ACTIVE );
	}

	public static function list_all() {
		return self::query_all( null );
	}

	private static function query_all( $status ) {
		global $wpdb;
		$table = WP_Delopay_Orders::table_categories();
		if ( null === $status ) {
			$rows = $wpdb->get_results(
				"SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC",
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY sort_order ASC, name ASC", $status ),
				ARRAY_A
			);
		}
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	public static function count_all( $status = '' ) {
		global $wpdb;
		$table = WP_Delopay_Orders::table_categories();
		if ( in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_DRAFT ), true ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function product_counts() {
		global $wpdb;
		$products = WP_Delopay_Orders::table_products();
		$rows     = $wpdb->get_results(
			"SELECT COALESCE(category_id, 0) AS cid, COUNT(*) AS n FROM {$products} GROUP BY category_id",
			ARRAY_A
		);
		$out      = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['cid'] ] = (int) $r['n'];
		}
		return $out;
	}

	public static function sanitize_input( $input, $existing_id = 0 ) {
		$name = isset( $input['name'] ) ? trim( wp_unslash( (string) $input['name'] ) ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'name_required', __( 'Name is required.', 'wp-delopay' ) );
		}

		$slug = self::clean_slug( $input, $name );
		if ( is_wp_error( $slug ) ) {
			return $slug;
		}
		if ( self::slug_in_use( $slug, $existing_id ) ) {
			/* translators: %s: the slug that conflicts with an existing category. */
			return new WP_Error( 'slug_taken', sprintf( __( 'Slug "%s" is already used by another category.', 'wp-delopay' ), $slug ) );
		}

		$hero_eyebrow  = isset( $input['hero_eyebrow'] ) ? sanitize_text_field( wp_unslash( (string) $input['hero_eyebrow'] ) ) : '';
		$hero_title    = isset( $input['hero_title'] ) ? sanitize_text_field( wp_unslash( (string) $input['hero_title'] ) ) : '';
		$hero_subtitle = isset( $input['hero_subtitle'] ) ? wp_kses_post( wp_unslash( (string) $input['hero_subtitle'] ) ) : '';

		return array(
			'slug'          => $slug,
			'name'          => sanitize_text_field( $name ),
			'description'   => isset( $input['description'] ) ? wp_kses_post( wp_unslash( $input['description'] ) ) : '',
			'hero_eyebrow'  => '' === $hero_eyebrow ? null : $hero_eyebrow,
			'hero_title'    => '' === $hero_title ? null : $hero_title,
			'hero_subtitle' => '' === trim( $hero_subtitle ) ? null : $hero_subtitle,
			'status'        => isset( $input['status'] ) && self::STATUS_DRAFT === $input['status'] ? self::STATUS_DRAFT : self::STATUS_ACTIVE,
			'sort_order'    => isset( $input['sort_order'] ) ? (int) $input['sort_order'] : 0,
		);
	}

	private static function clean_slug( $input, $name ) {
		$slug_raw = isset( $input['slug'] ) ? trim( wp_unslash( (string) $input['slug'] ) ) : '';
		$slug     = sanitize_title( '' !== $slug_raw ? $slug_raw : $name );
		if ( '' === $slug ) {
			return new WP_Error( 'slug_invalid', __( 'Slug must contain at least one URL-safe character.', 'wp-delopay' ) );
		}
		if ( strlen( $slug ) > self::MAX_SLUG_LENGTH ) {
			$slug = substr( $slug, 0, self::MAX_SLUG_LENGTH );
		}
		if ( in_array( $slug, self::RESERVED_SLUGS, true ) ) {
			return new WP_Error( 'slug_reserved', __( 'That slug is reserved.', 'wp-delopay' ) );
		}
		return $slug;
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
		if ( false === $wpdb->insert( WP_Delopay_Orders::table_categories(), $row ) ) {
			return new WP_Error( 'db_error', __( 'Could not create category.', 'wp-delopay' ) );
		}
		return self::find_for_admin( (int) $wpdb->insert_id );
	}

	public static function update( $id, $clean ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! self::find_for_admin( $id ) ) {
			return new WP_Error( 'not_found', __( 'Category not found.', 'wp-delopay' ) );
		}
		$row               = $clean;
		$row['updated_at'] = current_time( 'mysql', true );

		if ( false === $wpdb->update( WP_Delopay_Orders::table_categories(), $row, array( 'id' => $id ) ) ) {
			return new WP_Error( 'db_error', __( 'Could not update category.', 'wp-delopay' ) );
		}
		return self::find_for_admin( $id );
	}

	public static function delete( $id, $force = false ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}

		$products = WP_Delopay_Orders::table_products();
		$in_use   = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$products} WHERE category_id = %d",
				$id
			)
		);

		if ( $in_use > 0 && ! $force ) {
			return new WP_Error(
				'category_in_use',
				sprintf(
					/* translators: %d: number of products still in this category */
					_n(
						'Cannot delete: %d product still uses this category. Reassign it first or delete with force.',
						'Cannot delete: %d products still use this category. Reassign them first or delete with force.',
						$in_use,
						'wp-delopay'
					),
					$in_use
				)
			);
		}

		if ( $in_use > 0 ) {
			$wpdb->update( $products, array( 'category_id' => null ), array( 'category_id' => $id ) );
		}

		$wpdb->delete( WP_Delopay_Orders::table_categories(), array( 'id' => $id ) );
		return true;
	}

	public static function upsert_by_slug( $data ) {
		$slug = isset( $data['slug'] ) ? sanitize_title( (string) $data['slug'] ) : '';
		if ( '' === $slug ) {
			return null;
		}
		$existing = self::find( $slug, false );
		$clean    = self::sanitize_input( $data, $existing ? (int) $existing['id'] : 0 );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		return $existing
			? self::update( (int) $existing['id'], $clean )
			: self::create( $clean );
	}

	private static function slug_in_use( $slug, $exclude_id = 0 ) {
		global $wpdb;
		$table = WP_Delopay_Orders::table_categories();
		$found = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id <> %d LIMIT 1", $slug, (int) $exclude_id )
		);
		return $found > 0;
	}

	public static function hydrate( $row ) {
		$row['id']         = (int) $row['id'];
		$row['sort_order'] = isset( $row['sort_order'] ) ? (int) $row['sort_order'] : 0;
		$row['slug']       = (string) $row['slug'];
		$row['name']       = (string) $row['name'];
		$row['status']     = (string) $row['status'];

		$row['description'] = (string) $row['description'];
		$row['excerpt']     = wp_trim_words( wp_strip_all_tags( $row['description'] ), 24, '…' );

		$row['hero_eyebrow']  = isset( $row['hero_eyebrow'] ) ? (string) $row['hero_eyebrow'] : '';
		$row['hero_title']    = isset( $row['hero_title'] ) ? (string) $row['hero_title'] : '';
		$row['hero_subtitle'] = isset( $row['hero_subtitle'] ) ? (string) $row['hero_subtitle'] : '';
		$row['hero_active']   = '' !== trim( $row['hero_eyebrow'] )
			|| '' !== trim( $row['hero_title'] )
			|| '' !== trim( $row['hero_subtitle'] );

		return $row;
	}

	public static function page_url_for_slug( $slug ) {
		$slug = (string) $slug;
		if ( '' === $slug ) {
			return null;
		}
		$pages = get_pages( array( 'post_status' => 'publish' ) );
		foreach ( (array) $pages as $page ) {
			if ( has_shortcode( $page->post_content, 'delopay_products' )
				&& false !== stripos( $page->post_content, 'category="' . $slug . '"' )
			) {
				return get_permalink( $page );
			}
		}
		return null;
	}
}
