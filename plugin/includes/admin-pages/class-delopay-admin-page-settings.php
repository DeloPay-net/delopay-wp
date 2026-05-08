<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Admin_Page_Settings extends WP_Delopay_Admin_Page {

	public function slug() {
		return WP_Delopay_Admin::SLUG_SETTINGS;
	}

	public function label() {
		return __( 'Settings', 'wp-delopay' );
	}

	public function render() {
		$settings     = WP_Delopay_Settings::all();
		$pages        = get_pages( array( 'sort_column' => 'post_title' ) );
		$option_key   = WP_Delopay_Settings::OPTION_KEY;
		$env          = WP_Delopay_Settings::get_environment();
		$env_urls     = WP_Delopay_Settings::env_urls();
		$is_custom    = WP_Delopay_Settings::ENV_CUSTOM === $env;
		$branding_url = WP_Delopay_Settings::get_branding_url();
		$is_connected = WP_Delopay_Connect::is_connected() && WP_Delopay_Settings::is_configured();
		$connected_at = WP_Delopay_Connect::connected_at();
		$manual_open  = ! $is_connected;
		?>
		<div class="wrap wp-delopay-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'DeloPay Settings', 'wp-delopay' ); ?></h1>
			<hr class="wp-header-end">

			<?php $this->render_connect_card( $is_connected, $env, $settings, $connected_at ); ?>

			<form method="post" action="options.php" class="wp-delopay-settings-form">
				<?php settings_fields( WP_Delopay_Admin::SETTINGS_GROUP ); ?>

				<details class="wp-delopay-manual-details" <?php echo esc_attr( $manual_open ? 'open' : '' ); ?>>
					<summary><?php esc_html_e( 'Advanced — paste credentials manually', 'wp-delopay' ); ?></summary>
					<?php $this->render_credentials( $settings, $option_key ); ?>
					<?php $this->render_identifiers( $settings, $option_key ); ?>
				</details>

				<?php $this->render_environment( $env, $env_urls, $is_custom, $settings, $option_key ); ?>
				<?php $this->render_storefront( $settings, $option_key, $pages ); ?>
				<?php $this->render_branding( $branding_url ); ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function render_connect_card( $is_connected, $env, $settings, $connected_at ) {
		?>
		<div class="wp-delopay-connect-card <?php echo esc_attr( $is_connected ? 'is-connected' : 'is-brand' ); ?>">
			<?php if ( $is_connected ) : ?>
				<div class="wp-delopay-connect-status">
					<span class="wp-delopay-connect-dot" aria-hidden="true">●</span>
					<div>
						<strong><?php esc_html_e( 'Connected to DeloPay', 'wp-delopay' ); ?></strong>
						<p class="description">
							<?php
							printf(
								/* translators: 1: project id, 2: profile id, 3: human time diff */
								esc_html__( 'Project %1$s · Shop %2$s · connected %3$s ago', 'wp-delopay' ),
								'<code>' . esc_html( $settings['project_id'] ? $settings['project_id'] : '—' ) . '</code>',
								'<code>' . esc_html( $settings['profile_id'] ? $settings['profile_id'] : '—' ) . '</code>',
								esc_html( $connected_at ? human_time_diff( $connected_at ) : '—' )
							);
							?>
						</p>
					</div>
				</div>
				<div class="wp-delopay-connect-actions">
					<button type="button" class="button" data-delopay-connect-button data-delopay-environment="<?php echo esc_attr( $env ); ?>">
						<?php esc_html_e( 'Reconnect', 'wp-delopay' ); ?>
					</button>
					<button type="button" class="button button-link-delete" data-delopay-disconnect-button>
						<?php esc_html_e( 'Disconnect', 'wp-delopay' ); ?>
					</button>
				</div>
			<?php else : ?>
				<div class="wp-delopay-connect-pitch">
					<img class="wp-delopay-connect-symbol"
						src="<?php echo esc_url( WP_DELOPAY_URL . 'assets/img/symbol-white-on-blue.svg' ); ?>"
						alt="" aria-hidden="true" width="40" height="40">
					<div>
						<h2><?php esc_html_e( 'Connect this site to DeloPay', 'wp-delopay' ); ?></h2>
						<p>
							<?php esc_html_e( 'Sign in to the DeloPay control center, pick the project and shop this site should belong to, and we\'ll fill in every credential below for you — and register the webhook URL automatically.', 'wp-delopay' ); ?>
						</p>
					</div>
				</div>
				<div class="wp-delopay-connect-actions">
					<button type="button" class="button button-hero wp-delopay-connect-cta"
						data-delopay-connect-button data-delopay-environment="<?php echo esc_attr( $env ); ?>">
						<?php esc_html_e( 'Connect to DeloPay', 'wp-delopay' ); ?>
					</button>
				</div>
			<?php endif; ?>
			<p class="wp-delopay-connect-status-msg" data-delopay-connect-msg role="status" aria-live="polite"></p>
		</div>
		<?php
	}

	private function render_credentials( $settings, $option_key ) {
		$rows = array(
			array(
				'id'           => 'wp_delopay_api_key',
				'name'         => 'api_key',
				'type'         => 'password',
				'label'        => __( 'API key', 'wp-delopay' ),
				'value'        => $settings['api_key'],
				'description'  => __( 'Server-side merchant API key. Never exposed to the browser.', 'wp-delopay' ),
				'autocomplete' => 'off',
			),
			array(
				'id'           => 'wp_delopay_webhook_secret',
				'name'         => 'webhook_secret',
				'type'         => 'password',
				'label'        => __( 'Webhook secret', 'wp-delopay' ),
				'value'        => $settings['webhook_secret'],
				'description'  => __( 'Webhook signing secret. Set in your DeloPay business profile and copied here. Without it the webhook endpoint rejects every request.', 'wp-delopay' ),
				'autocomplete' => 'off',
			),
		);
		?>
		<h2 class="wp-delopay-settings-section-title"><?php esc_html_e( 'Credentials', 'wp-delopay' ); ?></h2>
		<p class="wp-delopay-settings-section-desc"><?php esc_html_e( 'The keys DeloPay uses to authenticate this site. Both stay server-side.', 'wp-delopay' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php $this->render_input_row( $option_key, $row ); ?>
				<?php endforeach; ?>
				<tr>
					<th><?php esc_html_e( 'Webhook URL', 'wp-delopay' ); ?></th>
					<td>
						<?php WP_Delopay_Admin_UI::copy_field( rest_url( 'delopay/v1/webhook' ), 'webhook-url' ); ?>
						<p class="description"><?php esc_html_e( 'Paste this into your DeloPay business profile → Webhooks. The matching signing secret goes in the field above. Connect to DeloPay registers this for you automatically.', 'wp-delopay' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	private function render_identifiers( $settings, $option_key ) {
		$rows = array(
			array(
				'id'          => 'wp_delopay_profile_id',
				'name'        => 'profile_id',
				'type'        => 'text',
				'label'       => __( 'Shop / Profile ID', 'wp-delopay' ),
				'value'       => $settings['profile_id'],
				'placeholder' => 'pro_…',
				'description' => __( 'The shop / business profile id (pro_…). Required if your account has more than one profile.', 'wp-delopay' ),
			),
			array(
				'id'          => 'wp_delopay_project_id',
				'name'        => 'project_id',
				'type'        => 'text',
				'label'       => __( 'Project ID', 'wp-delopay' ),
				'value'       => $settings['project_id'],
				'placeholder' => 'prj_…',
				'description' => __( 'The DeloPay project id (prj_…). Used with the shop id to build the checkout-branding link.', 'wp-delopay' ),
			),
		);
		?>
		<h2 class="wp-delopay-settings-section-title"><?php esc_html_e( 'Identifiers', 'wp-delopay' ); ?></h2>
		<p class="wp-delopay-settings-section-desc"><?php esc_html_e( 'Pointers DeloPay uses to attribute payments to this shop and to deep-link into the control center.', 'wp-delopay' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php $this->render_input_row( $option_key, $row ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_input_row( $option_key, array $row ) {
		$id           = (string) ( $row['id'] ?? 'wp_delopay_' . $row['name'] );
		$type         = (string) ( $row['type'] ?? 'text' );
		$class        = (string) ( $row['class'] ?? 'regular-text' );
		$placeholder  = (string) ( $row['placeholder'] ?? '' );
		$autocomplete = (string) ( $row['autocomplete'] ?? '' );
		$maxlength    = isset( $row['maxlength'] ) ? (int) $row['maxlength'] : 0;
		$description  = (string) ( $row['description'] ?? '' );
		?>
		<tr>
			<th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $row['label'] ); ?></label></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $option_key . '[' . $row['name'] . ']' ); ?>"
					value="<?php echo esc_attr( $row['value'] ); ?>"
					class="<?php echo esc_attr( $class ); ?>"
					<?php
					if ( $placeholder ) :
						?>
						placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>
					<?php
					if ( $autocomplete ) :
						?>
						autocomplete="<?php echo esc_attr( $autocomplete ); ?>"<?php endif; ?>
					<?php
					if ( $maxlength > 0 ) :
						?>
						maxlength="<?php echo esc_attr( (string) $maxlength ); ?>"<?php endif; ?>>
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function render_environment( $env, $env_urls, $is_custom, $settings, $option_key ) {
		?>
		<h2 class="wp-delopay-settings-section-title"><?php esc_html_e( 'Environment', 'wp-delopay' ); ?></h2>
		<p class="wp-delopay-settings-section-desc"><?php esc_html_e( 'Pick the DeloPay environment this shop talks to. Production and Sandbox use canonical URLs.', 'wp-delopay' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th><label for="wp_delopay_environment"><?php esc_html_e( 'Environment', 'wp-delopay' ); ?></label></th>
					<td>
						<select id="wp_delopay_environment"
							name="<?php echo esc_attr( $option_key ); ?>[environment]"
							class="wp-delopay-env-select"
							data-prod-control="<?php echo esc_attr( $env_urls['production']['control_center_url'] ); ?>"
							data-prod-checkout="<?php echo esc_attr( $env_urls['production']['checkout_base_url'] ); ?>"
							data-sandbox-control="<?php echo esc_attr( $env_urls['sandbox']['control_center_url'] ); ?>"
							data-sandbox-checkout="<?php echo esc_attr( $env_urls['sandbox']['checkout_base_url'] ); ?>">
							<option value="production" <?php selected( $env, 'production' ); ?>><?php esc_html_e( 'Production', 'wp-delopay' ); ?></option>
							<option value="sandbox"    <?php selected( $env, 'sandbox' ); ?>><?php esc_html_e( 'Sandbox', 'wp-delopay' ); ?></option>
							<option value="custom"     <?php selected( $env, 'custom' ); ?>><?php esc_html_e( 'Custom', 'wp-delopay' ); ?></option>
						</select>
						<p class="description wp-delopay-env-summary" data-env-summary>
							<?php $this->render_env_summary( $env ); ?>
						</p>
					</td>
				</tr>
				<tr class="wp-delopay-env-custom-row" <?php echo esc_attr( $is_custom ? '' : 'hidden' ); ?>>
					<th><label for="wp_delopay_control_center_url"><?php esc_html_e( 'Control center URL', 'wp-delopay' ); ?></label></th>
					<td>
						<input type="url" id="wp_delopay_control_center_url"
							name="<?php echo esc_attr( $option_key ); ?>[control_center_url]"
							value="<?php echo esc_attr( $settings['control_center_url'] ); ?>"
							class="regular-text" placeholder="https://dashboard.example.com">
						<p class="description">
							<?php
							printf(
								/* translators: %s = computed API URL */
								esc_html__( 'The REST API is always reached at %s.', 'wp-delopay' ),
								'<code>' . esc_html( '' !== WP_Delopay_Settings::get_api_base_url() ? WP_Delopay_Settings::get_api_base_url() : '{control_center_url}/api' ) . '</code>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr class="wp-delopay-env-custom-row" <?php echo esc_attr( $is_custom ? '' : 'hidden' ); ?>>
					<th><label for="wp_delopay_checkout_base_url"><?php esc_html_e( 'Checkout origin', 'wp-delopay' ); ?></label></th>
					<td>
						<input type="url" id="wp_delopay_checkout_base_url"
							name="<?php echo esc_attr( $option_key ); ?>[checkout_base_url]"
							value="<?php echo esc_attr( $settings['checkout_base_url'] ); ?>"
							class="regular-text" placeholder="https://checkout.example.com">
						<p class="description"><?php esc_html_e( 'Origin of the DeloPay checkout app. Buyers are sent to {origin}/pay/{merchant}/{payment_id} — embedded as an iframe by [delopay_checkout].', 'wp-delopay' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	private function render_storefront( $settings, $option_key, $pages ) {
		?>
		<h2 class="wp-delopay-settings-section-title"><?php esc_html_e( 'Storefront', 'wp-delopay' ); ?></h2>
		<p class="wp-delopay-settings-section-desc"><?php esc_html_e( 'Defaults for products and post-purchase routing.', 'wp-delopay' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th><label for="wp_delopay_currency"><?php esc_html_e( 'Default currency', 'wp-delopay' ); ?></label></th>
					<td>
						<input type="text" id="wp_delopay_currency"
							name="<?php echo esc_attr( $option_key ); ?>[currency]"
							value="<?php echo esc_attr( $settings['currency'] ); ?>"
							maxlength="3" class="wp-delopay-currency-input">
						<p class="description"><?php esc_html_e( 'ISO 4217 currency code. Per-product currencies on each product override this.', 'wp-delopay' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="wp_delopay_complete_page_id"><?php esc_html_e( 'Order-complete page', 'wp-delopay' ); ?></label></th>
					<td>
						<select id="wp_delopay_complete_page_id" name="<?php echo esc_attr( $option_key ); ?>[complete_page_id]">
							<option value="0">— <?php esc_html_e( 'Not set', 'wp-delopay' ); ?> —</option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) $settings['complete_page_id'], (int) $page->ID ); ?>>
									<?php echo esc_html( $page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Pick a WP page that contains the [delopay_complete] shortcode. DeloPay redirects buyers there after payment.', 'wp-delopay' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<?php $cart_mode = WP_Delopay_Settings::cart_checkout_mode(); ?>
					<th><label for="wp_delopay_cart_checkout_mode"><?php esc_html_e( 'Cart checkout buttons', 'wp-delopay' ); ?></label></th>
					<td>
						<select id="wp_delopay_cart_checkout_mode" name="<?php echo esc_attr( $option_key ); ?>[cart_checkout_mode]">
							<option value="both"     <?php selected( $cart_mode, 'both' ); ?>><?php esc_html_e( 'Show both — embedded + external', 'wp-delopay' ); ?></option>
							<option value="embedded" <?php selected( $cart_mode, 'embedded' ); ?>><?php esc_html_e( 'Embedded checkout only (iframe page)', 'wp-delopay' ); ?></option>
							<option value="external" <?php selected( $cart_mode, 'external' ); ?>><?php esc_html_e( 'External checkout only (redirect to hosted)', 'wp-delopay' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Embedded sends the buyer to your [delopay_checkout] page with the iframe. External redirects straight to DeloPay\'s hosted checkout. When only one is shown, it becomes the primary CTA.', 'wp-delopay' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}


	private function render_branding( $branding_url ) {
		?>
		<div data-branding-section <?php echo esc_attr( $branding_url ? '' : 'hidden' ); ?>>
			<h2 class="wp-delopay-settings-section-title"><?php esc_html_e( 'Integration links', 'wp-delopay' ); ?></h2>
			<p class="wp-delopay-settings-section-desc"><?php esc_html_e( 'Read-only references you wire into the DeloPay control center.', 'wp-delopay' ); ?></p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr data-branding-row>
						<th><?php esc_html_e( 'Checkout branding', 'wp-delopay' ); ?></th>
						<td>
							<a href="<?php echo esc_url( (string) $branding_url ); ?>" class="button" target="_blank" rel="noopener noreferrer" data-branding-link>
								<?php esc_html_e( 'Open checkout branding ↗', 'wp-delopay' ); ?>
							</a>
							<p class="description"><?php esc_html_e( 'Opens this shop\'s checkout-branding screen in the DeloPay control center, in a new tab.', 'wp-delopay' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_env_summary( $env ) {
		$urls = WP_Delopay_Settings::env_urls();
		if ( WP_Delopay_Settings::ENV_CUSTOM === $env ) {
			esc_html_e( 'Set the control-center and checkout origins below.', 'wp-delopay' );
			return;
		}
		printf(
			/* translators: 1: control center URL, 2: checkout origin URL */
			esc_html__( 'Control center: %1$s · Checkout origin: %2$s', 'wp-delopay' ),
			'<code>' . esc_html( $urls[ $env ]['control_center_url'] ) . '</code>',
			'<code>' . esc_html( $urls[ $env ]['checkout_base_url'] ) . '</code>'
		);
	}
}
