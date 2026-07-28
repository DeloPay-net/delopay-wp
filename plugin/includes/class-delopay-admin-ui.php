<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tiny shared helpers for DeloPay admin pages.
 *
 * Methods that build HTML print it directly (echo / printf) — that way
 * callers don't need to escape the return value, and PHPCS is happy.
 * Methods that return URLs or scalar values are clearly marked.
 */
class Delopay_Admin_UI {

	const CAP = 'manage_options';

	public static function require_cap() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'delopay' ), 403 );
		}
	}

	/* ----------------------- URL / scalar helpers -------------------------- */

	public static function page_url( $slug, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	public static function delete_url( $action, $key, $id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . $action . '&' . $key . '=' . (int) $id ),
			$action . '_' . (int) $id
		);
	}

	public static function admin_post_url() {
		return admin_url( 'admin-post.php' );
	}

	public static function redirect( array $args ) {
		$filtered = array_filter(
			$args,
			static function ( $v ) {
				return null !== $v;
			}
		);
		wp_safe_redirect( add_query_arg( $filtered, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function format_money( $minor, $currency ) {
		return number_format( ( (int) $minor ) / 100, 2 ) . ' ' . $currency;
	}

	/* ---------------------- HTML output helpers (echo) --------------------- */

	public static function status_badge( $status, $variant = null ) {
		$class = $variant ? $variant : $status;
		printf(
			'<span class="delopay-status delopay-status-%s">%s</span>',
			esc_attr( $class ),
			esc_html( $status )
		);
	}

	public static function active_status_badge( $status ) {
		$variant = 'active' === $status ? 'succeeded' : 'requires_payment_method';
		self::status_badge( $status, $variant );
	}

	/**
	 * Render row action links for an admin list table row.
	 *
	 * @param array $actions Array of [ key, url, opts ] where opts = [ 'label' => str, 'class' => str ].
	 */
	public static function row_actions( array $actions ) {
		$last = count( $actions ) - 1;
		echo '<div class="row-actions">';
		foreach ( array_values( $actions ) as $i => $action ) {
			[ $key, $url, $opts ] = $action + array( 2 => array() );
			$label                = (string) ( $opts['label'] ?? '' );
			$class                = (string) ( $opts['class'] ?? '' );
			$sep                  = $i < $last ? ' | ' : '';
			echo '<span class="' . esc_attr( $key ) . '"><a href="' . esc_url( $url ) . '"';
			if ( '' !== $class ) {
				echo ' class="' . esc_attr( $class ) . '"';
			}
			echo '>' . esc_html( $label ) . '</a>' . esc_html( $sep ) . '</span>';
		}
		echo '</div>';
	}

	public static function empty_state( $message, $cta_url = '', $cta_label = '' ) {
		?>
		<div class="delopay-products-empty">
			<p><?php echo esc_html( $message ); ?></p>
			<?php if ( '' !== $cta_url && '' !== $cta_label ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $cta_url ); ?>">
						<?php echo esc_html( $cta_label ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function not_found( $title, $back_slug, $back_label ) {
		echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
		echo '<p><a href="' . esc_url( self::page_url( $back_slug ) ) . '">← ' . esc_html( $back_label ) . '</a></p></div>';
	}

	public static function flash_notice( array $messages ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash slug from a same-origin redirect query string.
		if ( ! isset( $_GET['flash'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flash = sanitize_key( wp_unslash( $_GET['flash'] ) );
		if ( ! isset( $messages[ $flash ] ) ) {
			return;
		}
		[ $type, $default_msg ] = $messages[ $flash ];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : $default_msg;
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $msg )
		);
	}

	public static function copy_field( $value, $id_suffix ) {
		$id = 'delopay-copy-' . sanitize_html_class( $id_suffix );
		?>
		<span class="delopay-copy-row">
			<input type="text" id="<?php echo esc_attr( $id ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				readonly class="regular-text code">
			<button type="button" class="button delopay-copy-btn" data-copy-target="#<?php echo esc_attr( $id ); ?>">
				<?php esc_html_e( 'Copy', 'delopay' ); ?>
			</button>
		</span>
		<?php
	}

	public static function dim_cell( $text ) {
		echo '<span class="delopay-cell-dim">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Checkout-metadata repeater, shared by the product and category forms.
	 *
	 * Posts as parallel `metadata_keys[]` / `metadata_values[]` arrays, which
	 * `Delopay_Metadata::from_input()` zips back together. Rows are added and
	 * removed client-side (`delopay-admin.js`); a row with a blank key is
	 * dropped on save, which is also how deletion works without JS.
	 *
	 * @param array<string, string> $metadata Existing pairs.
	 * @param string                $help     Context line under the table.
	 */
	public static function metadata_repeater( array $metadata, $help = '' ): void {
		$rows = array();
		foreach ( $metadata as $key => $value ) {
			$rows[] = array( (string) $key, (string) $value );
		}
		// One empty row so the control is usable on a fresh record.
		$rows[] = array( '', '' );
		?>
		<div class="delopay-metadata" data-delopay-metadata>
			<div class="delopay-metadata-head">
				<span><?php esc_html_e( 'Key', 'delopay' ); ?></span>
				<span><?php esc_html_e( 'Value', 'delopay' ); ?></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Actions', 'delopay' ); ?></span>
			</div>
			<div class="delopay-metadata-rows" data-delopay-metadata-rows>
				<?php foreach ( $rows as $row ) : ?>
					<div class="delopay-metadata-row">
						<input type="text" name="metadata_keys[]" value="<?php echo esc_attr( $row[0] ); ?>"
							placeholder="<?php esc_attr_e( 'product_type', 'delopay' ); ?>"
							aria-label="<?php esc_attr_e( 'Metadata key', 'delopay' ); ?>"
							class="delopay-metadata-key code">
						<input type="text" name="metadata_values[]" value="<?php echo esc_attr( $row[1] ); ?>"
							placeholder="<?php esc_attr_e( 'spotify', 'delopay' ); ?>"
							aria-label="<?php esc_attr_e( 'Metadata value', 'delopay' ); ?>"
							class="delopay-metadata-value">
						<button type="button" class="button-link delopay-metadata-remove"
							aria-label="<?php esc_attr_e( 'Remove metadata row', 'delopay' ); ?>">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button delopay-metadata-add">
				<?php esc_html_e( 'Add metadata', 'delopay' ); ?>
			</button>
			<?php if ( '' !== $help ) : ?>
				<p class="delopay-help"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
