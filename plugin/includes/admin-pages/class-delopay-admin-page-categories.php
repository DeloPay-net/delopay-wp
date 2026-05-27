<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Admin_Page_Categories extends Delopay_Admin_Page {

	public function slug() {
		return Delopay_Admin::SLUG_CATEGORIES;
	}

	public function label() {
		return __( 'Categories', 'delopay' );
	}

	public function render() {
		$action = $this->get_key( 'action' );
		if ( 'new' === $action ) {
			$this->render_form( null );
			return;
		}
		if ( 'edit' === $action ) {
			$id  = $this->get_int( 'category' );
			$cat = Delopay_Categories::find_for_admin( $id );
			if ( ! $cat ) {
				Delopay_Admin_UI::not_found( __( 'Category not found', 'delopay' ), $this->slug(), __( 'All categories', 'delopay' ) );
				return;
			}
			$this->render_form( $cat );
			return;
		}
		$this->render_list();
	}

	private function render_list() {
		$categories = Delopay_Categories::list_all();
		$counts     = Delopay_Categories::product_counts();
		$add_url    = Delopay_Admin_UI::page_url( $this->slug(), array( 'action' => 'new' ) );
		?>
		<div class="wrap delopay-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Categories', 'delopay' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add New', 'delopay' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_flash(); ?>

			<p class="description delopay-section-description">
				<?php
				echo wp_kses(
					__( 'Creating a category automatically publishes a matching page with the <code>[delopay_products category="slug"]</code> shortcode.', 'delopay' ),
					array( 'code' => array() )
				);
				?>
			</p>

			<?php if ( empty( $categories ) ) : ?>
				<?php
				Delopay_Admin_UI::empty_state(
					__( 'No categories yet. Add your first one to start grouping products.', 'delopay' ),
					$add_url,
					__( 'Add a category', 'delopay' )
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
		<table class="widefat striped delopay-products-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Products', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Page', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Status', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'delopay' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $categories as $c ) :
					$edit_url   = Delopay_Admin_UI::page_url(
						$this->slug(),
						array(
							'action'   => 'edit',
							'category' => $c['id'],
						)
					);
					$delete_url = Delopay_Admin_UI::delete_url( 'delopay_delete_category', 'category', $c['id'] );
					$page_url   = Delopay_Categories::page_url_for_slug( $c['slug'] );
					$count      = isset( $counts[ (int) $c['id'] ] ) ? (int) $counts[ (int) $c['id'] ] : 0;
					$filter_url = Delopay_Admin_UI::page_url( Delopay_Admin::SLUG_PRODUCTS, array( 'category' => $c['slug'] ) );
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $c['name'] ); ?></a></strong>
							<?php
							Delopay_Admin_UI::row_actions(
								array(
									array( 'edit', $edit_url, array( 'label' => __( 'Edit', 'delopay' ) ) ),
									array(
										'trash',
										$delete_url,
										array(
											'label' => __( 'Delete', 'delopay' ),
											'class' => 'delopay-delete-category',
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
								<?php Delopay_Admin_UI::dim_cell( '0' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $page_url ) : ?>
								<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open page', 'delopay' ); ?> ↗</a>
							<?php else : ?>
								<?php Delopay_Admin_UI::dim_cell( __( '(missing — saving recreates)', 'delopay' ) ); ?>
							<?php endif; ?>
						</td>
						<td><?php Delopay_Admin_UI::active_status_badge( $c['status'] ); ?></td>
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
		<div class="wrap delopay-wrap">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( $is_new ? __( 'Add category', 'delopay' ) : __( 'Edit category', 'delopay' ) ); ?>
			</h1>
			<a class="page-title-action" href="<?php echo esc_url( Delopay_Admin_UI::page_url( $this->slug() ) ); ?>"><?php esc_html_e( 'All categories', 'delopay' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_flash(); ?>

			<form method="post" action="<?php echo esc_url( Delopay_Admin_UI::admin_post_url() ); ?>" class="delopay-product-form">
				<?php wp_nonce_field( 'delopay_save_category', 'delopay_category_nonce' ); ?>
				<input type="hidden" name="action" value="delopay_save_category">
				<input type="hidden" name="category_id" value="<?php echo esc_attr( $data['id'] ); ?>">

				<div class="delopay-form-grid">
					<div class="delopay-form-main">
						<?php $this->render_form_main( $data ); ?>
					</div>
					<div class="delopay-form-side">
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
		<div class="postbox delopay-postbox">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Category', 'delopay' ); ?></h2></div>
			<div class="inside">
				<div class="delopay-field">
					<label for="delopay-cat-name"><?php esc_html_e( 'Name', 'delopay' ); ?></label>
					<input type="text" id="delopay-cat-name" name="name" required value="<?php echo esc_attr( $data['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Coffee', 'delopay' ); ?>">
				</div>
				<div class="delopay-field">
					<label for="delopay-cat-slug"><?php esc_html_e( 'Slug', 'delopay' ); ?></label>
					<input type="text" id="delopay-cat-slug" name="slug" value="<?php echo esc_attr( $data['slug'] ); ?>" placeholder="<?php esc_attr_e( 'auto-generated from name', 'delopay' ); ?>">
					<p class="delopay-help">
						<?php
						echo wp_kses(
							__( 'Used in the shortcode <code>[delopay_products category="slug"]</code> and in URLs. Lowercase, hyphens only.', 'delopay' ),
							array( 'code' => array() )
						);
						?>
					</p>
				</div>
				<div class="delopay-field">
					<label for="delopay-cat-description"><?php esc_html_e( 'Description', 'delopay' ); ?></label>
					<textarea id="delopay-cat-description" name="description" rows="6"><?php echo esc_textarea( $data['description'] ); ?></textarea>
				</div>
				<div class="delopay-section">
					<h3 class="delopay-section-title"><?php esc_html_e( 'Hero', 'delopay' ); ?></h3>
					<p class="delopay-help" style="margin-top:0;">
						<?php
						echo wp_kses(
							__( 'Eyebrow / title / subtitle shown above the product grid on this category\'s page (via <code>[delopay_category_hero]</code>). Leave empty for a clean grid — the shortcode keeps the same vertical spacing either way.', 'delopay' ),
							array( 'code' => array() )
						);
						?>
					</p>
					<div class="delopay-field">
						<label for="delopay-cat-hero-eyebrow"><?php esc_html_e( 'Eyebrow', 'delopay' ); ?></label>
						<input type="text" id="delopay-cat-hero-eyebrow" name="hero_eyebrow" value="<?php echo esc_attr( $data['hero_eyebrow'] ); ?>" placeholder="<?php esc_attr_e( 'Short context line above the title', 'delopay' ); ?>">
					</div>
					<div class="delopay-field">
						<label for="delopay-cat-hero-title"><?php esc_html_e( 'Title', 'delopay' ); ?></label>
						<input type="text" id="delopay-cat-hero-title" name="hero_title" value="<?php echo esc_attr( $data['hero_title'] ); ?>" placeholder="<?php esc_attr_e( 'Headline for this category', 'delopay' ); ?>">
					</div>
					<div class="delopay-field">
						<label for="delopay-cat-hero-subtitle"><?php esc_html_e( 'Subtitle', 'delopay' ); ?></label>
						<textarea id="delopay-cat-hero-subtitle" name="hero_subtitle" rows="3"><?php echo esc_textarea( $data['hero_subtitle'] ); ?></textarea>
					</div>
				</div>
				<div class="delopay-section">
					<h3 class="delopay-section-title"><?php esc_html_e( 'Layout', 'delopay' ); ?></h3>
					<div class="delopay-row">
						<div class="delopay-field delopay-field-sort">
							<label for="delopay-cat-sort-order"><?php esc_html_e( 'Sort order', 'delopay' ); ?></label>
							<input type="number" step="1" id="delopay-cat-sort-order" name="sort_order" value="<?php echo esc_attr( $data['sort_order'] ); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_form_side( $data, $is_new ) {
		$delete_url = $is_new ? '' : Delopay_Admin_UI::delete_url( 'delopay_delete_category', 'category', $data['id'] );
		$page_url   = $is_new ? null : Delopay_Categories::page_url_for_slug( $data['slug'] );
		?>
		<div class="postbox delopay-postbox">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Publish', 'delopay' ); ?></h2></div>
			<div class="inside">
				<div class="delopay-field">
					<label for="delopay-cat-status"><?php esc_html_e( 'Status', 'delopay' ); ?></label>
					<select id="delopay-cat-status" name="status">
						<option value="active" <?php selected( $data['status'], 'active' ); ?>><?php esc_html_e( 'Active (visible)', 'delopay' ); ?></option>
						<option value="draft"  <?php selected( $data['status'], 'draft' ); ?>><?php esc_html_e( 'Draft (hidden)', 'delopay' ); ?></option>
					</select>
				</div>
				<button type="submit" class="button button-primary delopay-save">
					<?php echo esc_html( $is_new ? __( 'Create category', 'delopay' ) : __( 'Save changes', 'delopay' ) ); ?>
				</button>
				<?php if ( ! $is_new ) : ?>
					<a href="<?php echo esc_url( $delete_url ); ?>" class="delopay-delete-category delopay-delete-link">
						<?php esc_html_e( 'Delete category', 'delopay' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! $is_new && $page_url ) : ?>
			<div class="postbox delopay-postbox">
				<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Page', 'delopay' ); ?></h2></div>
				<div class="inside">
					<p><?php esc_html_e( 'A page on your site renders this category.', 'delopay' ); ?></p>
					<p>
						<a class="button" href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open page', 'delopay' ); ?> ↗</a>
					</p>
					<p class="delopay-help">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: shortcode example */
								__( 'Manually: drop %s into any other page or post.', 'delopay' ),
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
		Delopay_Admin_UI::flash_notice(
			array(
				'cat_created' => array( 'success', __( 'Category created. A matching page was published automatically.', 'delopay' ) ),
				'cat_updated' => array( 'success', __( 'Category updated.', 'delopay' ) ),
				'cat_deleted' => array( 'success', __( 'Category deleted.', 'delopay' ) ),
				'cat_error'   => array( 'error', __( 'Something went wrong.', 'delopay' ) ),
			)
		);
	}
}
