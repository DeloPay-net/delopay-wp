<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Delopay_Admin_Page_Orders extends WP_Delopay_Admin_Page {

	const REFUNDABLE_STATUSES = array( 'succeeded', 'partially_captured', 'partially_captured_and_capturable' );

	public function slug() {
		return WP_Delopay_Admin::SLUG_ORDERS;
	}

	public function label() {
		return __( 'Orders', 'wp-delopay' );
	}

	public function render() {
		$selected_id = $this->get( 'order_id' );
		if ( '' !== $selected_id ) {
			$this->render_order_detail( $selected_id );
			return;
		}

		$orders = WP_Delopay_Orders::list( 200 );
		?>
		<div class="wrap wp-delopay-wrap">
			<h1><?php esc_html_e( 'Orders', 'wp-delopay' ); ?></h1>
			<?php if ( empty( $orders ) ) : ?>
				<p><?php esc_html_e( 'No orders yet.', 'wp-delopay' ); ?></p>
			<?php else : ?>
				<?php $this->render_table( $orders ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_table( array $orders ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Created', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Refunded', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Webhook', 'wp-delopay' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $orders as $o ) :
				$refunded = WP_Delopay_Orders::refunded_total( $o['order_id'] );
				$detail   = WP_Delopay_Admin_UI::page_url( WP_Delopay_Admin::SLUG_ORDERS, array( 'order_id' => $o['order_id'] ) );
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( $detail ); ?>"><code><?php echo esc_html( $o['order_id'] ); ?></code></a>
						<br><small><?php echo esc_html( $o['payment_id'] ); ?></small>
					</td>
					<td><?php echo esc_html( $o['created_at'] ); ?></td>
					<td><?php echo esc_html( WP_Delopay_Admin_UI::format_money( $o['amount_minor'], $o['currency'] ) ); ?></td>
					<td><?php WP_Delopay_Admin_UI::status_badge( $o['status'] ); ?></td>
					<td><?php echo esc_html( $refunded ? WP_Delopay_Admin_UI::format_money( $refunded, $o['currency'] ) : '—' ); ?></td>
					<td><?php echo esc_html( $o['last_webhook_at'] ? $o['last_webhook_at'] : '—' ); ?></td>
					<td><a class="button" href="<?php echo esc_url( $detail ); ?>"><?php esc_html_e( 'Open', 'wp-delopay' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_order_detail( $order_id ) {
		$order = WP_Delopay_Orders::find( $order_id );
		if ( ! $order ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Order not found', 'wp-delopay' ) . '</h1></div>';
			return;
		}

		$refunds        = WP_Delopay_Orders::refunds_for( $order['order_id'] );
		$refunded_total = WP_Delopay_Orders::refunded_total( $order['order_id'] );
		$remaining      = (int) $order['amount_minor'] - $refunded_total;
		$can_refund     = in_array( $order['status'], self::REFUNDABLE_STATUSES, true ) && $remaining > 0;
		$back           = WP_Delopay_Admin_UI::page_url( WP_Delopay_Admin::SLUG_ORDERS );
		$currency       = $order['currency'];
		?>
		<div class="wrap wp-delopay-wrap">
			<h1>
				<?php esc_html_e( 'Order', 'wp-delopay' ); ?>
				<code><?php echo esc_html( $order['order_id'] ); ?></code>
			</h1>
			<p><a href="<?php echo esc_url( $back ); ?>">← <?php esc_html_e( 'All orders', 'wp-delopay' ); ?></a></p>

			<table class="form-table">
				<tr><th><?php esc_html_e( 'Payment ID', 'wp-delopay' ); ?></th><td><code><?php echo esc_html( $order['payment_id'] ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Status', 'wp-delopay' ); ?></th><td><?php WP_Delopay_Admin_UI::status_badge( $order['status'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Amount', 'wp-delopay' ); ?></th><td><?php echo esc_html( WP_Delopay_Admin_UI::format_money( $order['amount_minor'], $currency ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Refunded', 'wp-delopay' ); ?></th><td><?php echo esc_html( WP_Delopay_Admin_UI::format_money( $refunded_total, $currency ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Created', 'wp-delopay' ); ?></th><td><?php echo esc_html( $order['created_at'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Last webhook', 'wp-delopay' ); ?></th><td><?php echo esc_html( $order['last_webhook_at'] ? $order['last_webhook_at'] : '—' ); ?></td></tr>
				<?php if ( $order['error_message'] ) : ?>
					<tr><th><?php esc_html_e( 'Error', 'wp-delopay' ); ?></th><td><?php echo esc_html( $order['error_message'] ); ?></td></tr>
				<?php endif; ?>
			</table>

			<h2><?php esc_html_e( 'Line items', 'wp-delopay' ); ?></h2>
			<?php $this->render_lines( (array) $order['lines'], $currency ); ?>

			<h2><?php esc_html_e( 'Refunds', 'wp-delopay' ); ?></h2>
			<?php $this->render_refunds( $refunds, $currency ); ?>

			<?php if ( $can_refund ) : ?>
				<?php $this->render_refund_form( $order, $remaining ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_lines( array $lines, $currency ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Unit price', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Subtotal', 'wp-delopay' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $lines as $l ) : ?>
				<tr>
					<td><?php echo esc_html( $l['product_name'] ); ?></td>
					<td><?php echo esc_html( $l['quantity'] ); ?></td>
					<td><?php echo esc_html( WP_Delopay_Admin_UI::format_money( $l['unit_price_minor'], $currency ) ); ?></td>
					<td><?php echo esc_html( WP_Delopay_Admin_UI::format_money( $l['unit_price_minor'] * $l['quantity'], $currency ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_refunds( array $refunds, $currency ) {
		if ( empty( $refunds ) ) {
			?>
			<p><?php esc_html_e( 'No refunds yet.', 'wp-delopay' ); ?></p>
			<?php
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Refund ID', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'wp-delopay' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'wp-delopay' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $refunds as $r ) : ?>
				<tr>
					<td><code><?php echo esc_html( $r['refund_id'] ); ?></code></td>
					<td><?php echo esc_html( WP_Delopay_Admin_UI::format_money( $r['amount_minor'], $currency ) ); ?></td>
					<td><?php echo esc_html( $r['status'] ); ?></td>
					<td><?php echo esc_html( $r['reason'] ? $r['reason'] : '—' ); ?></td>
					<td><?php echo esc_html( $r['updated_at'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_refund_form( $order, $remaining ) {
		?>
		<h2><?php esc_html_e( 'Issue refund', 'wp-delopay' ); ?></h2>
		<form class="wp-delopay-refund-form"
			data-order-id="<?php echo esc_attr( $order['order_id'] ); ?>"
			data-remaining="<?php echo esc_attr( $remaining ); ?>">
			<p>
				<label>
					<?php esc_html_e( 'Amount (decimal):', 'wp-delopay' ); ?>
					<input type="number" step="0.01" min="0.01"
						value="<?php echo esc_attr( number_format( $remaining / 100, 2, '.', '' ) ); ?>"
						name="amount" required>
				</label>
				<label style="margin-left: 1em;">
					<?php esc_html_e( 'Reason:', 'wp-delopay' ); ?>
					<select name="reason">
						<option value="requested_by_customer">requested_by_customer</option>
						<option value="duplicate">duplicate</option>
						<option value="fraudulent">fraudulent</option>
					</select>
				</label>
				<button type="submit" class="button button-primary" style="margin-left: 1em;"><?php esc_html_e( 'Refund', 'wp-delopay' ); ?></button>
			</p>
			<p class="wp-delopay-refund-status" style="margin-top:0;color:#646970;"></p>
		</form>
		<?php
	}
}
