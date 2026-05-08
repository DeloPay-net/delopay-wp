<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Admin_Page_Products extends WP_Delopay_Admin_Page {

	public function slug() {
		return WP_Delopay_Admin::SLUG_PRODUCTS;
	}

	public function label() {
		return __( 'Products', 'wp-delopay' );
	}

	public function render() {
		$action = $this->get_key( 'action' );
		if ( 'new' === $action ) {
			$this->render_form( null );
			return;
		}
		if ( 'edit' === $action ) {
			$id      = $this->get_int( 'product' );
			$product = WP_Delopay_Products::find_for_admin( $id );
			if ( ! $product ) {
				WP_Delopay_Admin_UI::not_found( __( 'Product not found', 'wp-delopay' ), $this->slug(), __( 'All products', 'wp-delopay' ) );
				return;
			}
			$this->render_form( $product );
			return;
		}
		$this->render_list();
	}

	private function render_list() {
		$search   = $this->get( 's' );
		$status   = $this->get_key( 'status' );
		$category = $this->get( 'category' );

		$products      = WP_Delopay_Products::list_all(
			array(
				'search'   => $search,
				'status'   => $status,
				'category' => $category,
				'limit'    => 200,
			)
		);
		$add_url       = WP_Delopay_Admin_UI::page_url( $this->slug(), array( 'action' => 'new' ) );
		$base_list     = WP_Delopay_Admin_UI::page_url( $this->slug() );
		$export_url    = wp_nonce_url(
			admin_url( 'admin-post.php?action=wp_delopay_export_products' ),
			'wp_delopay_export_products'
		);
		$import_action = WP_Delopay_Admin_UI::admin_post_url();
		// Read-only redirect flash flag — no state change, capability gated by the page being admin-only.
		$import_open = isset( $_GET['flash'] ) && in_array( $_GET['flash'], array( 'imported', 'import_error' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap wp-delopay-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Products', 'wp-delopay' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add New', 'wp-delopay' ); ?></a>
			<a class="page-title-action" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export JSON', 'wp-delopay' ); ?></a>
			<a class="page-title-action wp-delopay-import-toggle" href="#wp-delopay-import" aria-controls="wp-delopay-import" aria-expanded="<?php echo $import_open ? 'true' : 'false'; ?>"><?php esc_html_e( 'Import JSON', 'wp-delopay' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_flash(); ?>
			<?php $this->render_import_form( $import_action, $import_open ); ?>
			<?php $this->render_status_tabs( $base_list, $status ); ?>
			<?php $this->render_filter_toolbar( $status, $category, $search ); ?>

			<?php if ( empty( $products ) ) : ?>
				<?php
				$message = '' !== $search
					? __( 'No products match that search.', 'wp-delopay' )
					: __( 'No products yet. Add your first one to start selling.', 'wp-delopay' );
				WP_Delopay_Admin_UI::empty_state( $message, $add_url, __( 'Add a product', 'wp-delopay' ) );
				?>
			<?php else : ?>
				<?php $this->render_table( $products ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_import_form( $import_action, $import_open ) {
		?>
		<div id="wp-delopay-import" class="wp-delopay-import" <?php echo esc_attr( $import_open ? '' : 'hidden' ); ?>>
			<form method="post" action="<?php echo esc_url( $import_action ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wp_delopay_import_products', 'wp_delopay_import_nonce' ); ?>
				<input type="hidden" name="action" value="wp_delopay_import_products">
				<p>
					<input type="file" name="import_file" accept="application/json,.json" required>
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Import products', 'wp-delopay' ); ?>">
				</p>
				<p class="description">
					<?php esc_html_e( 'Accepts a file previously exported from this screen. Existing SKUs are skipped.', 'wp-delopay' ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	private function render_status_tabs( $base_list, $status ) {
		$tabs     = array(
			array( '', __( 'All', 'wp-delopay' ), WP_Delopay_Products::count_all() ),
			array( 'active', __( 'Active', 'wp-delopay' ), WP_Delopay_Products::count_all( 'active' ) ),
			array( 'draft', __( 'Drafts', 'wp-delopay' ), WP_Delopay_Products::count_all( 'draft' ) ),
		);
		$last_idx = count( $tabs ) - 1;
		?>
		<ul class="subsubsub wp-delopay-subsubsub">
			<?php
			foreach ( $tabs as $idx => [ $tab_status, $label, $count ] ) :
				$tab_url = '' === $tab_status ? $base_list : add_query_arg( 'status', $tab_status, $base_list );
				$current = $tab_status === $status ? 'current' : '';
				?>
				<li>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="<?php echo esc_attr( $current ); ?>">
						<?php echo esc_html( $label ); ?>
						<span class="count">(<?php echo esc_html( $count ); ?>)</span>
					</a><?php echo esc_html( $idx === $last_idx ? '' : ' |' ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private function render_filter_toolbar( $status, $category, $search ) {
		$list_cats = WP_Delopay_Categories::list_all();
		?>
		<form method="get" class="wp-delopay-list-toolbar">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->slug() ); ?>">
			<?php if ( '' !== $status ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
			<?php endif; ?>
			<div class="alignleft actions">
				<label class="screen-reader-text" for="wp-delopay-product-cat-filter"><?php esc_html_e( 'Filter by category', 'wp-delopay' ); ?></label>
				<select id="wp-delopay-product-cat-filter" name="category">
					<option value=""><?php esc_html_e( 'All categories', 'wp-delopay' ); ?></option>
					<option value="-" <?php selected( '-', $category ); ?>><?php esc_html_e( 'Uncategorized', 'wp-delopay' ); ?></option>
					<?php foreach ( $list_cats as $list_cat ) : ?>
						<option value="<?php echo esc_attr( $list_cat['slug'] ); ?>" <?php selected( $list_cat['slug'], $category ); ?>>
							<?php echo esc_html( $list_cat['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'wp-delopay' ); ?>">
			</div>
			<p class="search-box">
				<label class="screen-reader-text" for="wp-delopay-product-search"><?php esc_html_e( 'Search products', 'wp-delopay' ); ?></label>
				<input type="search" id="wp-delopay-product-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search products', 'wp-delopay' ); ?>">
				<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'wp-delopay' ); ?>">
			</p>
		</form>
		<?php
	}

	private function render_table( $products ) {
		?>
		<table class="widefat striped wp-delopay-products-table">
			<thead>
				<tr>
					<th class="column-thumbnail"></th>
					<th><?php esc_html_e( 'Name', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Category', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Price', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'wp-delopay' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $products as $p ) :
					$edit_url   = WP_Delopay_Admin_UI::page_url(
						$this->slug(),
						array(
							'action'  => 'edit',
							'product' => $p['id'],
						)
					);
					$delete_url = WP_Delopay_Admin_UI::delete_url( 'wp_delopay_delete_product', 'product', $p['id'] );
					?>
					<tr>
						<td class="column-thumbnail">
							<?php if ( $p['thumbnail_url'] ) : ?>
								<img class="wp-delopay-thumb" src="<?php echo esc_url( $p['thumbnail_url'] ); ?>" alt="">
							<?php else : ?>
								<span class="wp-delopay-thumb-empty dashicons dashicons-format-image"></span>
							<?php endif; ?>
						</td>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $p['name'] ); ?></a></strong>
							<?php
							WP_Delopay_Admin_UI::row_actions(
								array(
									array( 'edit', $edit_url, array( 'label' => __( 'Edit', 'wp-delopay' ) ) ),
									array(
										'trash',
										$delete_url,
										array(
											'label' => __( 'Delete', 'wp-delopay' ),
											'class' => 'wp-delopay-delete-product',
										),
									),
								)
							);
							?>
						</td>
						<td>
							<?php if ( $p['sku'] ) : ?>
								<code><?php echo esc_html( $p['sku'] ); ?></code>
							<?php else : ?>
								<?php WP_Delopay_Admin_UI::dim_cell( '—' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( ! empty( $p['category_slug'] ) ) :
								$cat_filter_url = WP_Delopay_Admin_UI::page_url( $this->slug(), array( 'category' => $p['category_slug'] ) );
								?>
								<a href="<?php echo esc_url( $cat_filter_url ); ?>"><?php echo esc_html( $p['category_name'] ); ?></a>
							<?php else : ?>
								<?php WP_Delopay_Admin_UI::dim_cell( __( 'Uncategorized', 'wp-delopay' ) ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( WP_Delopay_Admin_UI::format_money( $p['price_minor'], $p['currency'] ) ); ?></td>
						<td><?php WP_Delopay_Admin_UI::active_status_badge( $p['status'] ); ?></td>
						<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $p['updated_at'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_form( $product ) {
		$is_new = empty( $product );
		$data   = $this->form_data( $product );
		?>
		<div class="wrap wp-delopay-wrap">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( $is_new ? __( 'Add product', 'wp-delopay' ) : __( 'Edit product', 'wp-delopay' ) ); ?>
			</h1>
			<a class="page-title-action" href="<?php echo esc_url( WP_Delopay_Admin_UI::page_url( $this->slug() ) ); ?>"><?php esc_html_e( 'All products', 'wp-delopay' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_flash(); ?>

			<form method="post" action="<?php echo esc_url( WP_Delopay_Admin_UI::admin_post_url() ); ?>" class="wp-delopay-product-form">
				<?php wp_nonce_field( 'wp_delopay_save_product', 'wp_delopay_product_nonce' ); ?>
				<input type="hidden" name="action" value="wp_delopay_save_product">
				<input type="hidden" name="product_id" value="<?php echo esc_attr( $data['id'] ); ?>">

				<div class="wp-delopay-form-grid">
					<div class="wp-delopay-form-main">
						<?php $this->render_form_main( $data ); ?>
					</div>
					<div class="wp-delopay-form-side">
						<?php $this->render_form_side( $data, $is_new ); ?>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	private function form_data( $product ) {
		$is_new = empty( $product );
		return array(
			'is_new'             => $is_new,
			'id'                 => $is_new ? 0 : (int) $product['id'],
			'name'               => $is_new ? '' : $product['name'],
			'sku'                => $is_new ? '' : $product['sku'],
			'description'        => $is_new ? '' : $product['description'],
			'price'              => $is_new ? '' : number_format( $product['price_minor'] / 100, 2, '.', '' ),
			'currency'           => $is_new ? strtoupper( (string) WP_Delopay_Settings::get( 'currency' ) ) : $product['currency'],
			'image_id'           => $is_new ? 0 : (int) $product['image_id'],
			'image_url'          => $is_new ? '' : (string) $product['image_url'],
			'image_url_external' => $is_new ? '' : (string) ( $product['image_url_external'] ?? '' ),
			'status'             => $is_new ? 'active' : $product['status'],
			'sort_order'         => $is_new ? 0 : (int) $product['sort_order'],
			'category_id'        => $is_new ? 0 : (int) ( $product['category_id'] ?? 0 ),
		);
	}

	private function render_form_main( $data ) {
		$all_cats = WP_Delopay_Categories::list_all();
		$cats_url = WP_Delopay_Admin_UI::page_url( WP_Delopay_Admin::SLUG_CATEGORIES );
		?>
		<div class="postbox wp-delopay-postbox">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Product', 'wp-delopay' ); ?></h2></div>
			<div class="inside">
				<div class="wp-delopay-field">
					<label for="wp-delopay-name"><?php esc_html_e( 'Name', 'wp-delopay' ); ?></label>
					<input type="text" id="wp-delopay-name" name="name" required value="<?php echo esc_attr( $data['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Premium subscription', 'wp-delopay' ); ?>">
				</div>
				<div class="wp-delopay-field">
					<label for="wp-delopay-description"><?php esc_html_e( 'Description', 'wp-delopay' ); ?></label>
					<textarea id="wp-delopay-description" name="description" rows="6" aria-describedby="wp-delopay-description-help"><?php echo esc_textarea( $data['description'] ); ?></textarea>
					<p id="wp-delopay-description-help" class="wp-delopay-help">
						<?php esc_html_e( 'HTML allowed. Trimmed to an excerpt on the grid; shown in full on the single-product view.', 'wp-delopay' ); ?>
					</p>
				</div>

				<div class="wp-delopay-section">
					<h3 class="wp-delopay-section-title"><?php esc_html_e( 'Pricing', 'wp-delopay' ); ?></h3>
					<div class="wp-delopay-row">
						<div class="wp-delopay-field wp-delopay-field-grow">
							<label for="wp-delopay-price"><?php esc_html_e( 'Price', 'wp-delopay' ); ?></label>
							<input type="number" step="0.01" min="0" id="wp-delopay-price" name="price" value="<?php echo esc_attr( $data['price'] ); ?>" placeholder="0.00">
						</div>
						<div class="wp-delopay-field wp-delopay-field-currency">
							<label for="wp-delopay-currency"><?php esc_html_e( 'Currency', 'wp-delopay' ); ?></label>
							<input type="text" maxlength="3" id="wp-delopay-currency" name="currency" value="<?php echo esc_attr( $data['currency'] ); ?>" class="wp-delopay-uppercase">
						</div>
					</div>
				</div>

				<div class="wp-delopay-section">
					<h3 class="wp-delopay-section-title"><?php esc_html_e( 'Catalog', 'wp-delopay' ); ?></h3>

					<div class="wp-delopay-field">
						<label for="wp-delopay-category"><?php esc_html_e( 'Category', 'wp-delopay' ); ?></label>
						<select id="wp-delopay-category" name="category_id">
							<option value="0" <?php selected( 0, $data['category_id'] ); ?>><?php esc_html_e( '— Uncategorized —', 'wp-delopay' ); ?></option>
							<?php foreach ( $all_cats as $opt_cat ) : ?>
								<option value="<?php echo esc_attr( $opt_cat['id'] ); ?>" <?php selected( (int) $opt_cat['id'], $data['category_id'] ); ?>>
									<?php echo esc_html( $opt_cat['name'] ); ?>
									<?php if ( 'draft' === $opt_cat['status'] ) : ?>
										— <?php esc_html_e( 'draft', 'wp-delopay' ); ?>
									<?php endif; ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="wp-delopay-help">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: link to the categories admin page */
									__( 'Manage categories at %s. New products land in <em>Home</em> by default.', 'wp-delopay' ),
									'<a href="' . esc_url( $cats_url ) . '">' . esc_html__( 'DeloPay → Categories', 'wp-delopay' ) . '</a>'
								),
								array(
									'a'  => array( 'href' => array() ),
									'em' => array(),
								)
							);
							?>
						</p>
					</div>

					<div class="wp-delopay-row">
						<div class="wp-delopay-field wp-delopay-field-grow">
							<label for="wp-delopay-sku"><?php esc_html_e( 'SKU', 'wp-delopay' ); ?> <span class="wp-delopay-optional"><?php esc_html_e( '(optional)', 'wp-delopay' ); ?></span></label>
							<input type="text" id="wp-delopay-sku" name="sku" value="<?php echo esc_attr( $data['sku'] ); ?>" placeholder="SKU-0001">
						</div>
						<div class="wp-delopay-field wp-delopay-field-sort">
							<label for="wp-delopay-sort-order"><?php esc_html_e( 'Sort order', 'wp-delopay' ); ?></label>
							<input type="number" step="1" id="wp-delopay-sort-order" name="sort_order" value="<?php echo esc_attr( $data['sort_order'] ); ?>">
						</div>
					</div>
					<p class="wp-delopay-help">
						<?php esc_html_e( 'SKU is the unique identifier used by shortcodes and the order API. Lower sort orders appear first in the grid.', 'wp-delopay' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_form_side( $data, $is_new ) {
		$delete_url = $is_new ? '' : WP_Delopay_Admin_UI::delete_url( 'wp_delopay_delete_product', 'product', $data['id'] );
		?>
		<div class="postbox wp-delopay-postbox">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Publish', 'wp-delopay' ); ?></h2></div>
			<div class="inside">
				<div class="wp-delopay-field">
					<label for="wp-delopay-status"><?php esc_html_e( 'Status', 'wp-delopay' ); ?></label>
					<select id="wp-delopay-status" name="status">
						<option value="active" <?php selected( $data['status'], 'active' ); ?>><?php esc_html_e( 'Active (visible)', 'wp-delopay' ); ?></option>
						<option value="draft"  <?php selected( $data['status'], 'draft' ); ?>><?php esc_html_e( 'Draft (hidden)', 'wp-delopay' ); ?></option>
					</select>
				</div>
				<button type="submit" class="button button-primary wp-delopay-save">
					<?php echo esc_html( $is_new ? __( 'Create product', 'wp-delopay' ) : __( 'Save changes', 'wp-delopay' ) ); ?>
				</button>
				<?php if ( ! $is_new ) : ?>
					<a href="<?php echo esc_url( $delete_url ); ?>" class="wp-delopay-delete-product wp-delopay-delete-link">
						<?php esc_html_e( 'Delete product', 'wp-delopay' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="postbox wp-delopay-postbox">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Image', 'wp-delopay' ); ?></h2></div>
			<div class="inside">
				<div class="wp-delopay-image-picker" data-empty-text="<?php esc_attr_e( 'No image selected', 'wp-delopay' ); ?>">
					<div class="wp-delopay-image-preview">
						<?php if ( $data['image_url'] ) : ?>
							<img src="<?php echo esc_url( $data['image_url'] ); ?>" alt="">
						<?php else : ?>
							<div class="wp-delopay-image-empty"><?php esc_html_e( 'No image selected', 'wp-delopay' ); ?></div>
						<?php endif; ?>
					</div>
					<input type="hidden" name="image_id" value="<?php echo esc_attr( $data['image_id'] ); ?>" class="wp-delopay-image-id">

					<div class="wp-delopay-image-actions">
						<button type="button" class="button button-small wp-delopay-image-pick">
							<?php esc_html_e( 'Media library', 'wp-delopay' ); ?>
						</button>
						<button type="button" class="button-link wp-delopay-image-clear" <?php echo esc_attr( $data['image_url'] ? '' : 'hidden' ); ?>>
							<?php esc_html_e( 'Remove', 'wp-delopay' ); ?>
						</button>
					</div>

					<div class="wp-delopay-field wp-delopay-image-url-field">
						<label for="wp-delopay-image-url"><?php esc_html_e( 'or paste image URL', 'wp-delopay' ); ?></label>
						<input type="url" id="wp-delopay-image-url" name="image_url"
							class="wp-delopay-image-url"
							value="<?php echo esc_attr( $data['image_url_external'] ); ?>"
							placeholder="https://example.com/product.jpg"
							inputmode="url" autocomplete="off">
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_flash() {
		WP_Delopay_Admin_UI::flash_notice(
			array(
				'created'      => array( 'success', __( 'Product created.', 'wp-delopay' ) ),
				'updated'      => array( 'success', __( 'Product updated.', 'wp-delopay' ) ),
				'deleted'      => array( 'success', __( 'Product deleted.', 'wp-delopay' ) ),
				'imported'     => array( 'success', __( 'Products imported.', 'wp-delopay' ) ),
				'import_error' => array( 'error', __( 'Import failed.', 'wp-delopay' ) ),
				'error'        => array( 'error', __( 'Something went wrong.', 'wp-delopay' ) ),
			)
		);
	}
}
