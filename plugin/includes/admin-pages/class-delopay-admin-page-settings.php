<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delopay_Admin_Page_Settings extends Delopay_Admin_Page {

	public function slug() {
		return Delopay_Admin::SLUG_SETTINGS;
	}

	public function label() {
		return __( 'Settings', 'delopay' );
	}

	public function render() {
		$settings     = Delopay_Settings::all();
		$pages        = get_pages( array( 'sort_column' => 'post_title' ) );
		$option_key   = Delopay_Settings::OPTION_KEY;
		$env          = Delopay_Settings::get_environment();
		$env_urls     = Delopay_Settings::env_urls();
		$is_custom    = Delopay_Settings::ENV_CUSTOM === $env;
		$branding_url = Delopay_Settings::get_branding_url();
		$is_connected = Delopay_Connect::is_connected() && Delopay_Settings::is_configured();
		$connected_at = Delopay_Connect::connected_at();
		$manual_open  = ! $is_connected;
		?>
		<div class="wrap delopay-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'DeloPay Settings', 'delopay' ); ?></h1>
			<hr class="wp-header-end">

			<?php $this->render_connect_card( $is_connected, $env, $settings, $connected_at ); ?>

			<form method="post" action="options.php" class="delopay-settings-form">
				<?php settings_fields( Delopay_Admin::SETTINGS_GROUP ); ?>

				<details class="delopay-manual-details" <?php echo esc_attr( $manual_open ? 'open' : '' ); ?>>
					<summary><?php esc_html_e( 'Advanced — paste credentials manually', 'delopay' ); ?></summary>
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
		<div class="delopay-connect-card <?php echo esc_attr( $is_connected ? 'is-connected' : 'is-brand' ); ?>">
			<?php if ( $is_connected ) : ?>
				<div class="delopay-connect-status">
					<span class="delopay-connect-dot" aria-hidden="true">●</span>
					<div>
						<strong><?php esc_html_e( 'Connected to DeloPay', 'delopay' ); ?></strong>
						<p class="description">
							<?php
							printf(
								/* translators: 1: project id, 2: profile id, 3: human time diff */
								esc_html__( 'Project %1$s · Shop %2$s · connected %3$s ago', 'delopay' ),
								'<code>' . esc_html( $settings['project_id'] ? $settings['project_id'] : '—' ) . '</code>',
								'<code>' . esc_html( $settings['profile_id'] ? $settings['profile_id'] : '—' ) . '</code>',
								esc_html( $connected_at ? human_time_diff( $connected_at ) : '—' )
							);
							?>
						</p>
					</div>
				</div>
				<div class="delopay-connect-actions">
					<button type="button" class="button" data-delopay-connect-button data-delopay-environment="<?php echo esc_attr( $env ); ?>">
						<?php esc_html_e( 'Reconnect', 'delopay' ); ?>
					</button>
					<button type="button" class="button button-link-delete" data-delopay-disconnect-button>
						<?php esc_html_e( 'Disconnect', 'delopay' ); ?>
					</button>
				</div>
			<?php else : ?>
				<div class="delopay-connect-pitch">
					<img class="delopay-connect-symbol"
						src="<?php echo esc_url( DELOPAY_URL . 'assets/img/symbol-white-on-blue.svg' ); ?>"
						alt="" aria-hidden="true" width="40" height="40">
					<div>
						<h2><?php esc_html_e( 'Connect this site to DeloPay', 'delopay' ); ?></h2>
						<p>
							<?php esc_html_e( 'Sign in to the DeloPay control center, pick the project and shop this site should belong to, and we\'ll fill in every credential below for you — and register the webhook URL automatically.', 'delopay' ); ?>
						</p>
					</div>
				</div>
				<div class="delopay-connect-actions">
					<button type="button" class="button button-hero delopay-connect-cta"
						data-delopay-connect-button data-delopay-environment="<?php echo esc_attr( $env ); ?>">
						<?php esc_html_e( 'Connect to DeloPay', 'delopay' ); ?>
					</button>
				</div>
			<?php endif; ?>
			<p class="delopay-connect-status-msg" data-delopay-connect-msg role="status" aria-live="polite"></p>
		</div>
		<?php
	}

	private function render_credentials( $settings, $option_key ) {
		$rows = array(
			array(
				'id'           => 'delopay_api_key',
				'name'         => 'api_key',
				'type'         => 'password',
				'label'        => __( 'API key', 'delopay' ),
				'value'        => $settings['api_key'],
				'description'  => __( 'Server-side merchant API key. Never exposed to the browser.', 'delopay' ),
				'autocomplete' => 'off',
			),
			array(
				'id'           => 'delopay_webhook_secret',
				'name'         => 'webhook_secret',
				'type'         => 'password',
				'label'        => __( 'Webhook secret', 'delopay' ),
				'value'        => $settings['webhook_secret'],
				'description'  => __( 'Webhook signing secret. Set in your DeloPay business profile and copied here. Without it the webhook endpoint rejects every request.', 'delopay' ),
				'autocomplete' => 'off',
			),
		);
		?>
		<h2 class="delopay-settings-section-title"><?php esc_html_e( 'Credentials', 'delopay' ); ?></h2>
		<p class="delopay-settings-section-desc"><?php esc_html_e( 'The keys DeloPay uses to authenticate this site. Both stay server-side.', 'delopay' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php $this->render_input_row( $option_key, $row ); ?>
				<?php endforeach; ?>
				<tr>
					<th><?php esc_html_e( 'Webhook URL', 'delopay' ); ?></th>
					<td>
						<?php Delopay_Admin_UI::copy_field( rest_url( 'delopay/v1/webhook' ), 'webhook-url' ); ?>
						<p class="description"><?php esc_html_e( 'Paste this into your DeloPay business profile → Webhooks. The matching signing secret goes in the field above. Connect to DeloPay registers this for you automatically.', 'delopay' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	private function render_identifiers( $settings, $option_key ) {
		$rows = array(
			array(
				'id'          => 'delopay_profile_id',
				'name'        => 'profile_id',
				'type'        => 'text',
				'label'       => __( 'Shop / Profile ID', 'delopay' ),
				'value'       => $settings['profile_id'],
				'placeholder' => 'pro_…',
				'description' => __( 'The shop / business profile id (pro_…). Required if your account has more than one profile.', 'delopay' ),
			),
			array(
				'id'          => 'delopay_project_id',
				'name'        => 'project_id',
				'type'        => 'text',
				'label'       => __( 'Project ID', 'delopay' ),
				'value'       => $settings['project_id'],
				'placeholder' => 'prj_…',
				'description' => __( 'The DeloPay project id (prj_…). Used with the shop id to build the checkout-branding link.', 'delopay' ),
			),
		);
		?>
		<h2 class="delopay-settings-section-title"><?php esc_html_e( 'Identifiers', 'delopay' ); ?></h2>
		<p class="delopay-settings-section-desc"><?php esc_html_e( 'Pointers DeloPay uses to attribute payments to this shop and to deep-link into the control center.', 'delopay' ); ?></p>
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
		$id           = (string) ( $row['id'] ?? 'delopay_' . $row['name'] );
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
		<h2 class="delopay-settings-section-title"><?php esc_html_e( 'Environment', 'delopay' ); ?></h2>
		<p class="delopay-settings-section-desc"><?php esc_html_e( 'Pick the DeloPay environment this shop talks to. Production and Sandbox use canonical URLs.', 'delopay' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th><label for="delopay_environment"><?php esc_html_e( 'Environment', 'delopay' ); ?></label></th>
					<td>
						<select id="delopay_environment"
							name="<?php echo esc_attr( $option_key ); ?>[environment]"
							class="delopay-env-select"
							data-prod-control="<?php echo esc_attr( $env_urls['production']['control_center_url'] ); ?>"
							data-prod-checkout="<?php echo esc_attr( $env_urls['production']['checkout_base_url'] ); ?>"
							data-sandbox-control="<?php echo esc_attr( $env_urls['sandbox']['control_center_url'] ); ?>"
							data-sandbox-checkout="<?php echo esc_attr( $env_urls['sandbox']['checkout_base_url'] ); ?>">
							<option value="production" <?php selected( $env, 'production' ); ?>><?php esc_html_e( 'Production', 'delopay' ); ?></option>
							<option value="sandbox"    <?php selected( $env, 'sandbox' ); ?>><?php esc_html_e( 'Sandbox', 'delopay' ); ?></option>
							<option value="custom"     <?php selected( $env, 'custom' ); ?>><?php esc_html_e( 'Custom', 'delopay' ); ?></option>
						</select>
						<p class="description delopay-env-summary" data-env-summary>
							<?php $this->render_env_summary( $env ); ?>
						</p>
					</td>
				</tr>
				<tr class="delopay-env-custom-row" <?php echo esc_attr( $is_custom ? '' : 'hidden' ); ?>>
					<th><label for="delopay_control_center_url"><?php esc_html_e( 'Control center URL', 'delopay' ); ?></label></th>
					<td>
						<input type="url" id="delopay_control_center_url"
							name="<?php echo esc_attr( $option_key ); ?>[control_center_url]"
							value="<?php echo esc_attr( $settings['control_center_url'] ); ?>"
							class="regular-text" placeholder="https://dashboard.example.com">
						<p class="description">
							<?php
							printf(
								/* translators: %s = computed API URL */
								esc_html__( 'The REST API is always reached at %s.', 'delopay' ),
								'<code>' . esc_html( '' !== Delopay_Settings::get_api_base_url() ? Delopay_Settings::get_api_base_url() : '{control_center_url}/api' ) . '</code>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr class="delopay-env-custom-row" <?php echo esc_attr( $is_custom ? '' : 'hidden' ); ?>>
					<th><label for="delopay_checkout_base_url"><?php esc_html_e( 'Checkout origin', 'delopay' ); ?></label></th>
					<td>
						<input type="url" id="delopay_checkout_base_url"
							name="<?php echo esc_attr( $option_key ); ?>[checkout_base_url]"
							value="<?php echo esc_attr( $settings['checkout_base_url'] ); ?>"
							class="regular-text" placeholder="https://checkout.example.com">
						<p class="description"><?php esc_html_e( 'Origin of the DeloPay checkout app. Buyers are sent to {origin}/pay/{merchant}/{payment_id} — embedded as an iframe by [delopay_checkout].', 'delopay' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	private function render_storefront( $settings, $option_key, $pages ) {
		?>
		<h2 class="delopay-settings-section-title"><?php esc_html_e( 'Storefront', 'delopay' ); ?></h2>
		<p class="delopay-settings-section-desc"><?php esc_html_e( 'Defaults for products and post-purchase routing.', 'delopay' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th><label for="delopay_currency"><?php esc_html_e( 'Default currency', 'delopay' ); ?></label></th>
					<td>
						<input type="text" id="delopay_currency"
							name="<?php echo esc_attr( $option_key ); ?>[currency]"
							value="<?php echo esc_attr( $settings['currency'] ); ?>"
							maxlength="3" class="delopay-currency-input">
						<p class="description"><?php esc_html_e( 'ISO 4217 currency code. Per-product currencies on each product override this.', 'delopay' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="delopay_capture_method"><?php esc_html_e( 'Capture mode', 'delopay' ); ?></label></th>
					<td>
						<?php $capture_method = isset( $settings['capture_method'] ) ? $settings['capture_method'] : 'automatic'; ?>
						<select id="delopay_capture_method" name="<?php echo esc_attr( $option_key ); ?>[capture_method]">
							<option value="automatic" <?php selected( $capture_method, 'automatic' ); ?>><?php esc_html_e( 'Automatic — capture immediately on payment', 'delopay' ); ?></option>
							<option value="manual" <?php selected( $capture_method, 'manual' ); ?>><?php esc_html_e( 'Manual — authorize now, capture later from the order', 'delopay' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Manual mode authorizes the card at checkout; you then capture or cancel each order from the Orders screen.', 'delopay' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="delopay_complete_page_id"><?php esc_html_e( 'Order-complete page', 'delopay' ); ?></label></th>
					<td>
						<select id="delopay_complete_page_id" name="<?php echo esc_attr( $option_key ); ?>[complete_page_id]">
							<option value="0">— <?php esc_html_e( 'Not set', 'delopay' ); ?> —</option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) $settings['complete_page_id'], (int) $page->ID ); ?>>
									<?php echo esc_html( $page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Pick a WP page that contains the [delopay_complete] shortcode. DeloPay redirects buyers there after payment.', 'delopay' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<?php $cart_mode = Delopay_Settings::cart_checkout_mode(); ?>
					<th><label for="delopay_cart_checkout_mode"><?php esc_html_e( 'Cart checkout buttons', 'delopay' ); ?></label></th>
					<td>
						<select id="delopay_cart_checkout_mode" name="<?php echo esc_attr( $option_key ); ?>[cart_checkout_mode]">
							<option value="both"     <?php selected( $cart_mode, 'both' ); ?>><?php esc_html_e( 'Show both — embedded + external', 'delopay' ); ?></option>
							<option value="embedded" <?php selected( $cart_mode, 'embedded' ); ?>><?php esc_html_e( 'Embedded checkout only (iframe page)', 'delopay' ); ?></option>
							<option value="external" <?php selected( $cart_mode, 'external' ); ?>><?php esc_html_e( 'External checkout only (redirect to hosted)', 'delopay' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Embedded sends the buyer to your [delopay_checkout] page with the iframe. External redirects straight to DeloPay\'s hosted checkout. When only one is shown, it becomes the primary CTA.', 'delopay' ); ?>
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
			<h2 class="delopay-settings-section-title"><?php esc_html_e( 'Integration links', 'delopay' ); ?></h2>
			<p class="delopay-settings-section-desc"><?php esc_html_e( 'Read-only references you wire into the DeloPay control center.', 'delopay' ); ?></p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr data-branding-row>
						<th><?php esc_html_e( 'Checkout branding', 'delopay' ); ?></th>
						<td>
							<a href="<?php echo esc_url( (string) $branding_url ); ?>" class="button" target="_blank" rel="noopener noreferrer" data-branding-link>
								<?php esc_html_e( 'Open checkout branding ↗', 'delopay' ); ?>
							</a>
							<p class="description"><?php esc_html_e( 'Opens this shop\'s checkout-branding screen in the DeloPay control center, in a new tab.', 'delopay' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_env_summary( $env ) {
		$urls = Delopay_Settings::env_urls();
		if ( Delopay_Settings::ENV_CUSTOM === $env ) {
			esc_html_e( 'Set the control-center and checkout origins below.', 'delopay' );
			return;
		}
		printf(
			/* translators: 1: control center URL, 2: checkout origin URL */
			esc_html__( 'Control center: %1$s · Checkout origin: %2$s', 'delopay' ),
			'<code>' . esc_html( $urls[ $env ]['control_center_url'] ) . '</code>',
			'<code>' . esc_html( $urls[ $env ]['checkout_base_url'] ) . '</code>'
		);
	}
}
