<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Admin_Page_Business extends Delopay_Admin_Page {

	public function slug() {
		return Delopay_Admin::SLUG_BUSINESS;
	}

	public function label() {
		return __( 'Business Profile', 'delopay' );
	}

	public function render() {
		$settings     = Delopay_Settings::all();
		$option_key   = Delopay_Settings::OPTION_KEY;
		$fields       = array(
			'business_name'    => array( 'text', __( 'Business name', 'delopay' ) ),
			'business_email'   => array( 'email', __( 'Contact email', 'delopay' ) ),
			'business_support' => array( 'text', __( 'Support phone or URL', 'delopay' ) ),
		);
		$branding_url = Delopay_Settings::get_branding_url();
		?>
		<div class="wrap delopay-wrap">
			<h1><?php esc_html_e( 'Business Profile', 'delopay' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Branding and contact info shown to buyers on receipts and emails. The DeloPay-side profile (acquirers, payout settings, webhooks) is configured in your DeloPay dashboard.', 'delopay' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( Delopay_Admin::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( $fields as $key => [ $type, $label ] ) : ?>
							<tr>
								<th><label for="delopay_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
								<td>
									<input type="<?php echo esc_attr( $type ); ?>"
										id="delopay_<?php echo esc_attr( $key ); ?>"
										name="<?php echo esc_attr( $option_key . '[' . $key . ']' ); ?>"
										value="<?php echo esc_attr( $settings[ $key ] ); ?>"
										class="regular-text">
								</td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th><?php esc_html_e( 'DeloPay profile ID', 'delopay' ); ?></th>
							<td>
								<code><?php echo esc_html( $settings['profile_id'] ? $settings['profile_id'] : __( '(not set)', 'delopay' ) ); ?></code>
								<p class="description"><?php esc_html_e( 'Set this on the Settings page.', 'delopay' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Checkout branding', 'delopay' ); ?></th>
							<td>
								<?php if ( $branding_url ) : ?>
									<a href="<?php echo esc_url( $branding_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Open checkout branding ↗', 'delopay' ); ?>
									</a>
									<p class="description"><?php esc_html_e( 'Logo, colors, and copy for the hosted checkout iframe live in the DeloPay control center.', 'delopay' ); ?></p>
								<?php else : ?>
									<em><?php esc_html_e( 'Add Project ID + Shop / Profile ID + Control center URL on the Settings page to enable.', 'delopay' ); ?></em>
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
