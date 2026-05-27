<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Admin_Page_Branding extends Delopay_Admin_Page {

	public function slug() {
		return Delopay_Admin::SLUG_BRANDING;
	}

	public function label() {
		return __( 'Branding', 'delopay' );
	}

	public function render() {
		$settings = Delopay_Settings::all();
		$mode     = Delopay_Settings::color_mode();
		$light    = Delopay_Settings::palette_defaults( 'light' );
		$dark     = Delopay_Settings::palette_defaults( 'dark' );
		?>
		<div class="wrap delopay-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Storefront branding', 'delopay' ); ?></h1>
			<hr class="wp-header-end">

			<p class="description" style="max-width: 60ch;">
				<?php esc_html_e( 'Controls the colors of the cart, checkout, products, and category hero shortcodes — the UI elements rendered by the DeloPay plugin itself. The DeloPay Shop theme owns its own typography, layout, and footer; on other themes these settings let you keep DeloPay\'s buttons and text on-brand.', 'delopay' ); ?>
			</p>

			<form method="post" action="options.php" class="delopay-settings-form">
				<?php settings_fields( Delopay_Admin::SETTINGS_GROUP ); ?>

				<h2 class="delopay-settings-section-title"><?php esc_html_e( 'Color mode', 'delopay' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th><label for="delopay_color_mode"><?php esc_html_e( 'Color mode', 'delopay' ); ?></label></th>
							<td>
								<select id="delopay_color_mode" name="<?php echo esc_attr( Delopay_Settings::OPTION_KEY ); ?>[color_mode]">
									<option value="auto"  <?php selected( $mode, 'auto' ); ?>><?php esc_html_e( 'Auto (follow OS)', 'delopay' ); ?></option>
									<option value="light" <?php selected( $mode, 'light' ); ?>><?php esc_html_e( 'Light', 'delopay' ); ?></option>
									<option value="dark"  <?php selected( $mode, 'dark' ); ?>><?php esc_html_e( 'Dark', 'delopay' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Auto matches the visitor\'s OS preference; light or dark force the chosen palette regardless.', 'delopay' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<details class="delopay-design-details" open>
					<summary><?php esc_html_e( 'Light mode colors', 'delopay' ); ?></summary>
					<?php $this->render_palette_table( 'light', $light, $settings ); ?>
				</details>
				<details class="delopay-design-details">
					<summary><?php esc_html_e( 'Dark mode colors', 'delopay' ); ?></summary>
					<?php $this->render_palette_table( 'dark', $dark, $settings ); ?>
				</details>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function render_palette_table( $mode, $defaults, $settings ) {
		$labels     = array(
			'accent'      => __( 'Buttons & links', 'delopay' ),
			'accent_fg'   => __( 'Text on buttons', 'delopay' ),
			'fg'          => __( 'Body text', 'delopay' ),
			'muted'       => __( 'Secondary text', 'delopay' ),
			'line'        => __( 'Borders', 'delopay' ),
			'surface_alt' => __( 'Hover & inset background', 'delopay' ),
		);
		$option_key = Delopay_Settings::OPTION_KEY;
		?>
		<p class="description" style="margin: 0.75rem 0;">
			<?php esc_html_e( 'Leave a field blank to keep the built-in default shown as placeholder.', 'delopay' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
				<?php
				foreach ( $labels as $token => $label ) :
					$default_hex = $defaults[ $token ] ?? '';
					$key         = $mode . '_' . $token;
					$id          = 'delopay_' . $key;
					$value       = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
					$swatch      = '' !== $value ? $value : $default_hex;
					?>
					<tr>
						<th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<span class="delopay-color-row">
								<span class="delopay-color-swatch" style="background: <?php echo esc_attr( $swatch ); ?>" aria-hidden="true"></span>
								<input type="text"
									id="<?php echo esc_attr( $id ); ?>"
									name="<?php echo esc_attr( $option_key . '[' . $key . ']' ); ?>"
									value="<?php echo esc_attr( $value ); ?>"
									placeholder="<?php echo esc_attr( $default_hex ); ?>"
									pattern="^#?[A-Fa-f0-9]{3}([A-Fa-f0-9]{3})?$"
									maxlength="7"
									class="regular-text delopay-color-input"
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
