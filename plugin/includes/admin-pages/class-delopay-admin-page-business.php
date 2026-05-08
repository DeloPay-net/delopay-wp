<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Admin_Page_Business extends WP_Delopay_Admin_Page {

	public function slug() {
		return WP_Delopay_Admin::SLUG_BUSINESS;
	}

	public function label() {
		return __( 'Business Profile', 'wp-delopay' );
	}

	public function render() {
		$settings     = WP_Delopay_Settings::all();
		$option_key   = WP_Delopay_Settings::OPTION_KEY;
		$fields       = array(
			'business_name'    => array( 'text', __( 'Business name', 'wp-delopay' ) ),
			'business_email'   => array( 'email', __( 'Contact email', 'wp-delopay' ) ),
			'business_support' => array( 'text', __( 'Support phone or URL', 'wp-delopay' ) ),
		);
		$branding_url = WP_Delopay_Settings::get_branding_url();
		?>
		<div class="wrap wp-delopay-wrap">
			<h1><?php esc_html_e( 'Business Profile', 'wp-delopay' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Branding and contact info shown to buyers on receipts and emails. The DeloPay-side profile (acquirers, payout settings, webhooks) is configured in your DeloPay dashboard.', 'wp-delopay' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( WP_Delopay_Admin::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( $fields as $key => [ $type, $label ] ) : ?>
							<tr>
								<th><label for="wp_delopay_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
								<td>
									<input type="<?php echo esc_attr( $type ); ?>"
										id="wp_delopay_<?php echo esc_attr( $key ); ?>"
										name="<?php echo esc_attr( $option_key . '[' . $key . ']' ); ?>"
										value="<?php echo esc_attr( $settings[ $key ] ); ?>"
										class="regular-text">
								</td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th><?php esc_html_e( 'DeloPay profile ID', 'wp-delopay' ); ?></th>
							<td>
								<code><?php echo esc_html( $settings['profile_id'] ? $settings['profile_id'] : __( '(not set)', 'wp-delopay' ) ); ?></code>
								<p class="description"><?php esc_html_e( 'Set this on the Settings page.', 'wp-delopay' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Checkout branding', 'wp-delopay' ); ?></th>
							<td>
								<?php if ( $branding_url ) : ?>
									<a href="<?php echo esc_url( $branding_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Open checkout branding ↗', 'wp-delopay' ); ?>
									</a>
									<p class="description"><?php esc_html_e( 'Logo, colors, and copy for the hosted checkout iframe live in the DeloPay control center.', 'wp-delopay' ); ?></p>
								<?php else : ?>
									<em><?php esc_html_e( 'Add Project ID + Shop / Profile ID + Control center URL on the Settings page to enable.', 'wp-delopay' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
