<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only admin view of disputes (chargebacks) pulled live from DeloPay.
 * The plugin does not store disputes locally; this page lists/retrieves them
 * on demand via the API.
 */
class Delopay_Admin_Page_Disputes extends Delopay_Admin_Page {

	public function slug() {
		return Delopay_Admin::SLUG_DISPUTES;
	}

	public function label() {
		return __( 'Disputes', 'delopay' );
	}

	public function render() {
		$client = new Delopay_Client();
		?>
		<div class="wrap delopay-wrap">
			<h1><?php esc_html_e( 'Disputes', 'delopay' ); ?></h1>
			<?php
			if ( ! $client->is_ready() ) {
				echo '<p>' . esc_html__( 'Connect to DeloPay in Settings to view disputes.', 'delopay' ) . '</p></div>';
				return;
			}

			$selected_id = $this->get( 'dispute_id' );
			if ( '' !== $selected_id ) {
				$this->render_detail( $client, $selected_id );
			} else {
				$this->render_list( $client );
			}
			?>
		</div>
		<?php
	}

	private function render_list( Delopay_Client $client ) {
		$result = $client->list_disputes( array( 'limit' => 100 ) );
		if ( is_wp_error( $result ) ) {
			$this->render_error( $result );
			return;
		}

		$disputes = $this->normalize_list( $result );
		if ( empty( $disputes ) ) {
			echo '<p>' . esc_html__( 'No disputes.', 'delopay' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Dispute', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Payment', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Stage', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Status', 'delopay' ); ?></th>
					<th><?php esc_html_e( 'Created', 'delopay' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $disputes as $d ) :
				if ( ! is_array( $d ) ) {
					continue;
				}
				$dispute_id = isset( $d['dispute_id'] ) ? (string) $d['dispute_id'] : '';
				$detail_url = Delopay_Admin_UI::page_url( Delopay_Admin::SLUG_DISPUTES, array( 'dispute_id' => $dispute_id ) );
				?>
				<tr>
					<td><a href="<?php echo esc_url( $detail_url ); ?>"><code><?php echo esc_html( $dispute_id ); ?></code></a></td>
					<td><small><?php echo esc_html( isset( $d['payment_id'] ) ? (string) $d['payment_id'] : '—' ); ?></small></td>
					<td><?php echo esc_html( $this->format_amount( $d ) ); ?></td>
					<td><?php echo esc_html( isset( $d['dispute_stage'] ) ? (string) $d['dispute_stage'] : '—' ); ?></td>
					<td><?php Delopay_Admin_UI::status_badge( isset( $d['dispute_status'] ) ? (string) $d['dispute_status'] : 'unknown' ); ?></td>
					<td><?php echo esc_html( isset( $d['created_at'] ) ? (string) $d['created_at'] : '—' ); ?></td>
					<td><a class="button" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Open', 'delopay' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_detail( Delopay_Client $client, $dispute_id ) {
		$dispute  = $client->retrieve_dispute( $dispute_id );
		$list_url = Delopay_Admin_UI::page_url( Delopay_Admin::SLUG_DISPUTES );
		echo '<p><a href="' . esc_url( $list_url ) . '">&larr; ' . esc_html__( 'All disputes', 'delopay' ) . '</a></p>';

		if ( is_wp_error( $dispute ) ) {
			$this->render_error( $dispute );
			return;
		}
		?>
		<table class="widefat striped">
			<tbody>
			<?php foreach ( $dispute as $key => $value ) : ?>
				<tr>
					<th style="width:220px"><?php echo esc_html( (string) $key ); ?></th>
					<td><?php echo esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_error( WP_Error $error ) {
		echo '<div class="notice notice-error"><p>'
			. esc_html( $error->get_error_message() )
			. '</p></div>';
	}

	/**
	 * The list endpoint may return a bare array or an envelope ({ data: [...] }).
	 *
	 * @param mixed $result Decoded API response.
	 * @return array
	 */
	private function normalize_list( $result ) {
		if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			return $result['data'];
		}
		return is_array( $result ) ? $result : array();
	}

	private function format_amount( array $d ) {
		if ( ! isset( $d['amount'] ) || ! is_numeric( $d['amount'] ) ) {
			return '—';
		}
		$currency = isset( $d['currency'] ) ? (string) $d['currency'] : Delopay_Settings::get( 'currency' );
		return Delopay_Admin_UI::format_money( (int) $d['amount'], $currency );
	}
}
