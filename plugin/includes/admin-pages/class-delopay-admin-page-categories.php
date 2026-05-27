<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Admin_Page_Categories extends WP_Delopay_Admin_Page {

	public function slug() {
		return WP_Delopay_Admin::SLUG_CATEGORIES;
	}

	public function label() {
		return __( 'Categories', 'wp-delopay' );
	}

	public function render() {
		$action = $this->get_key( 'action' );
		if ( 'new' === $action ) {
			$this->render_form( null );
			return;
		}
		if ( 'edit' === $action ) {
			$id  = $this->get_int( 'category' );
			$cat = WP_Delopay_Categories::find_for_admin( $id );
			if ( ! $cat ) {
				WP_Delopay_Admin_UI::not_found( __( 'Category not found', 'wp-delopay' ), $this->slug(), __( 'All categories', 'wp-delopay' ) );
				return;
			}
			$this->render_form( $cat );
			return;
		}
		$this->render_list();
	}

	private function render_list() {
		$categories = WP_Delopay_Categories::list_all();
		$counts     = WP_Delopay_Categories::product_counts();
		$add_url    = WP_Delopay_Admin_UI::page_url( $this->slug(), array( 'action' => 'new' ) );
		?>
		<div class="wrap wp-delopay-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Categories', 'wp-delopay' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add New', 'wp-delopay' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_flash(); ?>

			<p class="description wp-delopay-section-description">
				<?php
				echo wp_kses(
					__( 'Creating a category automatically publishes a matching page with the <code>[delopay_products category="slug"]</code> shortcode.', 'wp-delopay' ),
					array( 'code' => array() )
				);
				?>
			</p>

			<?php if ( empty( $categories ) ) : ?>
				<?php
				WP_Delopay_Admin_UI::empty_state(
					__( 'No categories yet. Add your first one to start grouping products.', 'wp-delopay' ),
					$add_url,
					__( 'Add a category', 'wp-delopay' )
				);
				?>
			<?php else : ?>
				<?php $this->render_table( $categories, $counts ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_table( $categories, $counts ) {
		?>
		<table class="widefat striped wp-delopay-products-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Products', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Page', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'wp-delopay' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $categories as $c ) :
					$edit_url   = WP_Delopay_Admin_UI::page_url(
						$this->slug(),
						array(
							'action'   => 'edit',
							'category' => $c['id'],
						)
					);
					$delete_url = WP_Delopay_Admin_UI::delete_url( 'wp_delopay_delete_category', 'category', $c['id'] );
					$page_url   = WP_Delopay_Categories::page_url_for_slug( $c['slug'] );
					$count      = isset( $counts[ (int) $c['id'] ] ) ? (int) $counts[ (int) $c['id'] ] : 0;
					$filter_url = WP_Delopay_Admin_UI::page_url( WP_Delopay_Admin::SLUG_PRODUCTS, array( 'category' => $c['slug'] ) );
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $c['name'] ); ?></a></strong>
							<?php
							WP_Delopay_Admin_UI::row_actions(
								array(
									array( 'edit', $edit_url, array( 'label' => __( 'Edit', 'wp-delopay' ) ) ),
									array(
										'trash',
										$delete_url,
										array(
											'label' => __( 'Delete', 'wp-delopay' ),
											'class' => 'wp-delopay-delete-category',
										),
									),
								)
							);
							?>
						</td>
						<td><code><?php echo esc_html( $c['slug'] ); ?></code></td>
						<td>
							<?php if ( $count > 0 ) : ?>
								<a href="<?php echo esc_url( $filter_url ); ?>"><?php echo esc_html( (string) $count ); ?></a>
							<?php else : ?>
								<?php WP_Delopay_Admin_UI::dim_cell( '0' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $page_url ) : ?>
								<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open page', 'wp-delopay' ); ?> ↗</a>
							<?php else : ?>
								<?php WP_Delopay_Admin_UI::dim_cell( __( '(missing — saving recreates)', 'wp-delopay' ) ); ?>
							<?php endif; ?>
						</td>
						<td><?php WP_Delopay_Admin_UI::active_status_badge( $c['status'] ); ?></td>
						<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $c['updated_at'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_form( $category ) {
		$is_new = empty( $category );
		$data   = $this->form_data( $category );
		?>
		<div class="wrap wp-delopay-wrap">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( $is_new ? __( 'Add category', 'wp-delopay' ) : __( 'Edit category', 'wp-delopay' ) ); ?>
			</h1>
			<a class="page-title-action" href="<?php echo esc_url( WP_Delopay_Admin_UI::page_url( $this->slug() ) ); ?>"><?php esc_html_e( 'All categories', 'wp-delopay' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_flash(); ?>

			<form method="post" action="<?php echo esc_url( WP_Delopay_Admin_UI::admin_post_url() ); ?>" class="wp-delopay-product-form">
				<?php wp_nonce_field( 'wp_delopay_save_category', 'wp_delopay_category_nonce' ); ?>
				<input type="hidden" name="action" value="wp_delopay_save_category">
				<input type="hidden" name="category_id" value="<?php echo esc_attr( $data['id'] ); ?>">

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

	private function form_data( $category ) {
		$is_new = empty( $category );
		return array(
			'is_new'        => $is_new,
			'id'            => $is_new ? 0 : (int) $category['id'],
			'name'          => $is_new ? '' : $category['name'],
			'slug'          => $is_new ? '' : $category['slug'],
			'description'   => $is_new ? '' : $category['description'],
			'hero_eyebrow'  => $is_new ? '' : (string) ( $category['hero_eyebrow'] ?? '' ),
			'hero_title'    => $is_new ? '' : (string) ( $category['hero_title'] ?? '' ),
			'hero_subtitle' => $is_new ? '' : (string) ( $category['hero_subtitle'] ?? '' ),
			'status'        => $is_new ? 'active' : $category['status'],
			'sort_order'    => $is_new ? 0 : (int) $category['sort_order'],
		);
	}

	private function render_form_main( $data ) {
		?>
		<div class="postbox wp-delopay-postbox">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Category', 'wp-delopay' ); ?></h2></div>
			<div class="inside">
				<div class="wp-delopay-field">
					<label for="wp-delopay-cat-name"><?php esc_html_e( 'Name', 'wp-delopay' ); ?></label>
					<input type="text" id="wp-delopay-cat-name" name="name" required value="<?php echo esc_attr( $data['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Coffee', 'wp-delopay' ); ?>">
				</div>
				<div class="wp-delopay-field">
					<label for="wp-delopay-cat-slug"><?php esc_html_e( 'Slug', 'wp-delopay' ); ?></label>
					<input type="text" id="wp-delopay-cat-slug" name="slug" value="<?php echo esc_attr( $data['slug'] ); ?>" placeholder="<?php esc_attr_e( 'auto-generated from name', 'wp-delopay' ); ?>">
					<p class="wp-delopay-help">
						<?php
						echo wp_kses(
							__( 'Used in the shortcode <code>[delopay_products category="slug"]</code> and in URLs. Lowercase, hyphens only.', 'wp-delopay' ),
							array( 'code' => array() )
						);
						?>
					</p>
				</div>
				<div class="wp-delopay-field">
					<label for="wp-delopay-cat-description"><?php esc_html_e( 'Description', 'wp-delopay' ); ?></label>
					<textarea id="wp-delopay-cat-description" name="description" rows="6"><?php echo esc_textarea( $data['description'] ); ?></textarea>
				</div>
				<div class="wp-delopay-section">
					<h3 class="wp-delopay-section-title"><?php esc_html_e( 'Hero', 'wp-delopay' ); ?></h3>
					<p class="wp-delopay-help" style="margin-top:0;">
						<?php
						echo wp_kses(
							__( 'Eyebrow / title / subtitle shown above the product grid on this category\'s page (via <code>[delopay_category_hero]</code>). Leave empty for a clean grid — the shortcode keeps the same vertical spacing either way.', 'wp-delopay' ),
							array( 'code' => array() )
						);
						?>
					</p>
					<div class="wp-delopay-field">
						<label for="wp-delopay-cat-hero-eyebrow"><?php esc_html_e( 'Eyebrow', 'wp-delopay' ); ?></label>
						<input type="text" id="wp-delopay-cat-hero-eyebrow" name="hero_eyebrow" value="<?php echo esc_attr( $data['hero_eyebrow'] ); ?>" placeholder="<?php esc_attr_e( 'Short context line above the title', 'wp-delopay' ); ?>">
					</div>
					<div class="wp-delopay-field">
						<label for="wp-delopay-cat-hero-title"><?php esc_html_e( 'Title', 'wp-delopay' ); ?></label>
						<input type="text" id="wp-delopay-cat-hero-title" name="hero_title" value="<?php echo esc_attr( $data['hero_title'] ); ?>" placeholder="<?php esc_attr_e( 'Headline for this category', 'wp-delopay' ); ?>">
					</div>
					<div class="wp-delopay-field">
						<label for="wp-delopay-cat-hero-subtitle"><?php esc_html_e( 'Subtitle', 'wp-delopay' ); ?></label>
						<textarea id="wp-delopay-cat-hero-subtitle" name="hero_subtitle" rows="3"><?php echo esc_textarea( $data['hero_subtitle'] ); ?></textarea>
					</div>
				</div>
				<div class="wp-delopay-section">
					<h3 class="wp-delopay-section-title"><?php esc_html_e( 'Layout', 'wp-delopay' ); ?></h3>
					<div class="wp-delopay-row">
						<div class="wp-delopay-field wp-delopay-field-sort">
							<label for="wp-delopay-cat-sort-order"><?php esc_html_e( 'Sort order', 'wp-delopay' ); ?></label>
							<input type="number" step="1" id="wp-delopay-cat-sort-order" name="sort_order" value="<?php echo esc_attr( $data['sort_order'] ); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_form_side( $data, $is_new ) {
		$delete_url = $is_new ? '' : WP_Delopay_Admin_UI::delete_url( 'wp_delopay_delete_category', 'category', $data['id'] );
		$page_url   = $is_new ? null : WP_Delopay_Categories::page_url_for_slug( $data['slug'] );
		?>
		<div class="postbox wp-delopay-postbox">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Publish', 'wp-delopay' ); ?></h2></div>
			<div class="inside">
				<div class="wp-delopay-field">
					<label for="wp-delopay-cat-status"><?php esc_html_e( 'Status', 'wp-delopay' ); ?></label>
					<select id="wp-delopay-cat-status" name="status">
						<option value="active" <?php selected( $data['status'], 'active' ); ?>><?php esc_html_e( 'Active (visible)', 'wp-delopay' ); ?></option>
						<option value="draft"  <?php selected( $data['status'], 'draft' ); ?>><?php esc_html_e( 'Draft (hidden)', 'wp-delopay' ); ?></option>
					</select>
				</div>
				<button type="submit" class="button button-primary wp-delopay-save">
					<?php echo esc_html( $is_new ? __( 'Create category', 'wp-delopay' ) : __( 'Save changes', 'wp-delopay' ) ); ?>
				</button>
				<?php if ( ! $is_new ) : ?>
					<a href="<?php echo esc_url( $delete_url ); ?>" class="wp-delopay-delete-category wp-delopay-delete-link">
						<?php esc_html_e( 'Delete category', 'wp-delopay' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! $is_new && $page_url ) : ?>
			<div class="postbox wp-delopay-postbox">
				<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Page', 'wp-delopay' ); ?></h2></div>
				<div class="inside">
					<p><?php esc_html_e( 'A page on your site renders this category.', 'wp-delopay' ); ?></p>
					<p>
						<a class="button" href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open page', 'wp-delopay' ); ?> ↗</a>
					</p>
					<p class="wp-delopay-help">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: shortcode example */
								__( 'Manually: drop %s into any other page or post.', 'wp-delopay' ),
								'<code>[delopay_products category="' . esc_html( $data['slug'] ) . '"]</code>'
							),
							array( 'code' => array() )
						);
						?>
					</p>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	private function render_flash() {
		WP_Delopay_Admin_UI::flash_notice(
			array(
				'cat_created' => array( 'success', __( 'Category created. A matching page was published automatically.', 'wp-delopay' ) ),
				'cat_updated' => array( 'success', __( 'Category updated.', 'wp-delopay' ) ),
				'cat_deleted' => array( 'success', __( 'Category deleted.', 'wp-delopay' ) ),
				'cat_error'   => array( 'error', __( 'Something went wrong.', 'wp-delopay' ) ),
			)
		);
	}
}
