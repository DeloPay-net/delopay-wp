<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Admin_Page_Branding extends WP_Delopay_Admin_Page {

	public function slug() {
		return WP_Delopay_Admin::SLUG_BRANDING;
	}

	public function label() {
		return __( 'Branding', 'wp-delopay' );
	}

	public function render() {
		$settings = WP_Delopay_Settings::all();
		$mode     = WP_Delopay_Settings::color_mode();
		$light    = WP_Delopay_Settings::palette_defaults( 'light' );
		$dark     = WP_Delopay_Settings::palette_defaults( 'dark' );
		?>
		<div class="wrap wp-delopay-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Storefront branding', 'wp-delopay' ); ?></h1>
			<hr class="wp-header-end">

			<p class="description" style="max-width: 60ch;">
				<?php esc_html_e( 'Controls the colors of the cart, checkout, products, and category hero shortcodes — the UI elements rendered by the DeloPay plugin itself. The DeloPay Shop theme owns its own typography, layout, and footer; on other themes these settings let you keep DeloPay\'s buttons and text on-brand.', 'wp-delopay' ); ?>
			</p>

			<form method="post" action="options.php" class="wp-delopay-settings-form">
				<?php settings_fields( WP_Delopay_Admin::SETTINGS_GROUP ); ?>

				<h2 class="wp-delopay-settings-section-title"><?php esc_html_e( 'Color mode', 'wp-delopay' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th><label for="wp_delopay_color_mode"><?php esc_html_e( 'Color mode', 'wp-delopay' ); ?></label></th>
							<td>
								<select id="wp_delopay_color_mode" name="<?php echo esc_attr( WP_Delopay_Settings::OPTION_KEY ); ?>[color_mode]">
									<option value="auto"  <?php selected( $mode, 'auto' ); ?>><?php esc_html_e( 'Auto (follow OS)', 'wp-delopay' ); ?></option>
									<option value="light" <?php selected( $mode, 'light' ); ?>><?php esc_html_e( 'Light', 'wp-delopay' ); ?></option>
									<option value="dark"  <?php selected( $mode, 'dark' ); ?>><?php esc_html_e( 'Dark', 'wp-delopay' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Auto matches the visitor\'s OS preference; light or dark force the chosen palette regardless.', 'wp-delopay' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<details class="wp-delopay-design-details" open>
					<summary><?php esc_html_e( 'Light mode colors', 'wp-delopay' ); ?></summary>
					<?php $this->render_palette_table( 'light', $light, $settings ); ?>
				</details>
				<details class="wp-delopay-design-details">
					<summary><?php esc_html_e( 'Dark mode colors', 'wp-delopay' ); ?></summary>
					<?php $this->render_palette_table( 'dark', $dark, $settings ); ?>
				</details>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function render_palette_table( $mode, $defaults, $settings ) {
		$labels     = array(
			'accent'      => __( 'Buttons & links', 'wp-delopay' ),
			'accent_fg'   => __( 'Text on buttons', 'wp-delopay' ),
			'fg'          => __( 'Body text', 'wp-delopay' ),
			'muted'       => __( 'Secondary text', 'wp-delopay' ),
			'line'        => __( 'Borders', 'wp-delopay' ),
			'surface_alt' => __( 'Hover & inset background', 'wp-delopay' ),
		);
		$option_key = WP_Delopay_Settings::OPTION_KEY;
		?>
		<p class="description" style="margin: 0.75rem 0;">
			<?php esc_html_e( 'Leave a field blank to keep the built-in default shown as placeholder.', 'wp-delopay' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
				<?php
				foreach ( $labels as $token => $label ) :
					$default_hex = $defaults[ $token ] ?? '';
					$key         = $mode . '_' . $token;
					$id          = 'wp_delopay_' . $key;
					$value       = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
					$swatch      = '' !== $value ? $value : $default_hex;
					?>
					<tr>
						<th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<span class="wp-delopay-color-row">
								<span class="wp-delopay-color-swatch" style="background: <?php echo esc_attr( $swatch ); ?>" aria-hidden="true"></span>
								<input type="text"
									id="<?php echo esc_attr( $id ); ?>"
									name="<?php echo esc_attr( $option_key . '[' . $key . ']' ); ?>"
									value="<?php echo esc_attr( $value ); ?>"
									placeholder="<?php echo esc_attr( $default_hex ); ?>"
									pattern="^#?[A-Fa-f0-9]{3}([A-Fa-f0-9]{3})?$"
									maxlength="7"
									class="regular-text wp-delopay-color-input"
									data-default="<?php echo esc_attr( $default_hex ); ?>"
									autocomplete="off">
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
