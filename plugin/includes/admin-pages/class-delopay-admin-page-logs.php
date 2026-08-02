<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DeloPay → Logs.
 *
 * The point of this screen is that a merchant with no server access can answer
 * "why is checkout broken?" themselves, and that a support ticket can start
 * with a screenshot or a paste instead of a request for SSH.
 */
class Delopay_Admin_Page_Logs extends Delopay_Admin_Page {

	const PER_PAGE = 50;

	public function slug(): string {
		return Delopay_Admin::SLUG_LOGS;
	}

	public function label(): string {
		return __( 'Logs', 'delopay' );
	}

	public function render(): void {
		Delopay_Admin_UI::require_cap();

		$level = $this->get_key( 'level' );
		$level = in_array( $level, Delopay_Log::levels(), true ) ? $level : '';
		$page  = max( 1, $this->get_int( 'paged' ) );

		$result   = Delopay_Log::query(
			array(
				'level'    => $level,
				'page'     => $page,
				'per_page' => self::PER_PAGE,
			)
		);
		$rows     = $result['rows'];
		$total    = $result['total'];
		$max_page = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		?>
		<div class="wrap delopay-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'DeloPay Logs', 'delopay' ); ?></h1>
			<hr class="wp-header-end">

			<?php Delopay_Admin_UI::flash_notice( self::flash_messages() ); ?>

			<?php $this->render_connection_card(); ?>

			<p class="description">
				<?php
				printf(
					/* translators: 1: retention in days, 2: maximum number of stored entries */
					esc_html__( 'What the plugin recorded while talking to DeloPay. Kept for %1$d days, up to %2$d entries — API keys, webhook secrets and signatures are redacted before anything is written.', 'delopay' ),
					(int) Delopay_Log::RETENTION_DAYS,
					(int) Delopay_Log::RETENTION_MAX_ROWS
				);
				?>
			</p>

			<?php if ( ! Delopay_Orders::has_logs_table() ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'The log table has not been created yet. Reload this page; if it still does not appear, deactivate and reactivate the plugin.', 'delopay' ); ?></p>
				</div>
			<?php else : ?>
				<?php $this->render_toolbar( $level, $total, $rows ); ?>

				<?php if ( empty( $rows ) ) : ?>
					<?php
					Delopay_Admin_UI::empty_state(
						'' === $level
							? __( 'Nothing logged yet. Entries appear here as the plugin talks to DeloPay.', 'delopay' )
							: __( 'No entries at this level.', 'delopay' )
					);
					?>
				<?php else : ?>
					<table class="widefat striped delopay-logs">
						<thead>
							<tr>
								<th class="delopay-log-col-time"><?php esc_html_e( 'When', 'delopay' ); ?></th>
								<th class="delopay-log-col-level"><?php esc_html_e( 'Level', 'delopay' ); ?></th>
								<th><?php esc_html_e( 'Message', 'delopay' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<?php $this->render_row( $row ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php $this->render_pagination( $level, $page, $max_page, $total ); ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Notices this screen can be redirected back to, keyed by flash slug.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function flash_messages(): array {
		return array(
			'logs_cleared'   => array( 'success', __( 'Log cleared.', 'delopay' ) ),
			'health_ok'      => array( 'success', __( 'DeloPay accepted your API key — the connection is working.', 'delopay' ) ),
			'health_bad'     => array( 'error', __( 'DeloPay rejected your API key. Reconnect in Settings to fix it.', 'delopay' ) ),
			'health_unknown' => array( 'warning', __( 'Could not reach DeloPay to check the connection. Try again in a moment.', 'delopay' ) ),
		);
	}

	/**
	 * Connection health, restated here because this is the screen a merchant
	 * lands on when something is wrong.
	 */
	private function render_connection_card(): void {
		$state        = Delopay_Health::state();
		$checked_at   = Delopay_Health::checked_at();
		$message      = Delopay_Health::message();
		$settings_url = Delopay_Admin_UI::page_url( Delopay_Admin::SLUG_SETTINGS );
		?>
		<div class="delopay-health delopay-health-<?php echo esc_attr( $state ); ?>">
			<p class="delopay-health-line">
				<span class="delopay-health-icon" aria-hidden="true"><?php echo esc_html( Delopay_Health::icon( $state ) ); ?></span>
				<strong><?php echo esc_html( Delopay_Health::label( $state ) ); ?></strong>
				<?php if ( $checked_at > 0 ) : ?>
					<span class="delopay-health-when">
						<?php
						printf(
							/* translators: %s = human-readable time difference, e.g. "5 mins" */
							esc_html__( 'last checked %s ago', 'delopay' ),
							esc_html( human_time_diff( $checked_at ) )
						);
						?>
					</span>
				<?php endif; ?>
			</p>
			<?php if ( '' !== $message ) : ?>
				<p class="delopay-health-detail"><code><?php echo esc_html( $message ); ?></code></p>
			<?php endif; ?>
			<div class="delopay-health-actions">
				<form method="post" action="<?php echo esc_url( Delopay_Admin_UI::admin_post_url() ); ?>">
					<?php wp_nonce_field( 'delopay_check_connection' ); ?>
					<input type="hidden" name="action" value="delopay_check_connection">
					<button type="submit" class="button"><?php esc_html_e( 'Check connection now', 'delopay' ); ?></button>
				</form>
				<a class="button <?php echo esc_attr( Delopay_Health::STATE_INVALID === $state ? 'button-primary' : '' ); ?>"
					href="<?php echo esc_url( $settings_url ); ?>">
					<?php esc_html_e( 'Open Settings', 'delopay' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Level filter, copy-for-support and clear-log controls above the table.
	 *
	 * @param string                           $level Active level filter, '' for all.
	 * @param int                              $total Total matching entries.
	 * @param array<int, array<string, mixed>> $rows  Entries on this page.
	 */
	private function render_toolbar( $level, $total, array $rows ): void {
		$base = Delopay_Admin_UI::page_url( Delopay_Admin::SLUG_LOGS );
		$tabs = array( '' => __( 'All', 'delopay' ) );
		foreach ( Delopay_Log::levels() as $candidate ) {
			$tabs[ $candidate ] = self::level_label( $candidate );
		}
		$last  = count( $tabs ) - 1;
		$index = 0;
		?>
		<div class="delopay-logs-toolbar">
			<ul class="subsubsub">
				<?php
				foreach ( $tabs as $value => $label ) :
					$url = '' === $value ? $base : add_query_arg( 'level', $value, $base );
					?>
					<li>
						<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $level === $value ? 'current' : '' ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
						<?php echo esc_html( $index < $last ? ' | ' : '' ); ?>
					</li>
					<?php
					++$index;
				endforeach;
				?>
			</ul>

			<div class="delopay-logs-actions">
				<?php $this->render_copy_control( $rows ); ?>
				<?php if ( $total > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( Delopay_Admin_UI::admin_post_url() ); ?>">
						<?php wp_nonce_field( 'delopay_clear_logs' ); ?>
						<input type="hidden" name="action" value="delopay_clear_logs">
						<button type="submit" class="button"
							data-delopay-confirm="<?php esc_attr_e( 'Clear every log entry? This cannot be undone.', 'delopay' ); ?>">
							<?php esc_html_e( 'Clear log', 'delopay' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * "Copy for support" — dumps the visible page as plain text, which is the
	 * whole ask in a support thread.
	 *
	 * The source textarea stays in the layout (screen-reader-text keeps it
	 * off-screen but selectable) because the execCommand fallback in
	 * delopay-admin.js copies a real selection.
	 *
	 * @param array<int, array<string, mixed>> $rows Entries on this page.
	 */
	private function render_copy_control( array $rows ): void {
		if ( empty( $rows ) ) {
			return;
		}

		$lines = array(
			'DeloPay ' . DELOPAY_VERSION . ' — ' . home_url( '/' ),
			'connection: ' . Delopay_Health::state(),
			'',
		);
		foreach ( $rows as $row ) {
			$line = (string) ( $row['created_at'] ?? '' ) . ' UTC  '
				. strtoupper( (string) ( $row['level'] ?? '' ) ) . '  '
				. (string) ( $row['message'] ?? '' );
			if ( '' !== (string) ( $row['context'] ?? '' ) ) {
				$line .= '  ' . (string) $row['context'];
			}
			$lines[] = trim( $line );
		}
		?>
		<textarea id="delopay-logs-copy" class="screen-reader-text" readonly aria-hidden="true"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
		<button type="button" class="button delopay-copy-btn" data-copy-target="#delopay-logs-copy">
			<?php esc_html_e( 'Copy for support', 'delopay' ); ?>
		</button>
		<?php
	}

	/**
	 * Render one table row: local timestamp, level pill, message and an
	 * expandable context pane.
	 *
	 * @param array<string, mixed> $row One log entry.
	 */
	private function render_row( array $row ): void {
		$level     = (string) ( $row['level'] ?? Delopay_Log::LEVEL_INFO );
		$created   = (string) ( $row['created_at'] ?? '' );
		$timestamp = '' === $created ? 0 : (int) strtotime( $created . ' UTC' );
		$context   = (string) ( $row['context'] ?? '' );
		?>
		<tr>
			<td class="delopay-log-col-time">
				<?php if ( $timestamp > 0 ) : ?>
					<span title="<?php echo esc_attr( $created . ' UTC' ); ?>">
						<?php echo esc_html( wp_date( 'Y-m-d H:i:s', $timestamp ) ); ?>
					</span>
				<?php else : ?>
					<?php Delopay_Admin_UI::dim_cell( '—' ); ?>
				<?php endif; ?>
			</td>
			<td class="delopay-log-col-level">
				<span class="delopay-log-level delopay-log-level-<?php echo esc_attr( $level ); ?>">
					<?php echo esc_html( self::level_label( $level ) ); ?>
				</span>
			</td>
			<td>
				<span class="delopay-log-message"><?php echo esc_html( (string) ( $row['message'] ?? '' ) ); ?></span>
				<?php if ( '' !== $context ) : ?>
					<details class="delopay-log-context">
						<summary><?php esc_html_e( 'Details', 'delopay' ); ?></summary>
						<pre><code><?php echo esc_html( self::pretty_context( $context ) ); ?></code></pre>
					</details>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Newer/older links under the table, preserving the active level filter.
	 *
	 * @param string $level Active level filter, '' for all.
	 * @param int    $page  Current page number.
	 * @param int    $max   Last page number.
	 * @param int    $total Total matching entries.
	 */
	private function render_pagination( $level, $page, $max, $total ): void {
		if ( $max < 2 ) {
			return;
		}
		$base = Delopay_Admin_UI::page_url( Delopay_Admin::SLUG_LOGS );
		if ( '' !== $level ) {
			$base = add_query_arg( 'level', $level, $base );
		}
		?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s = number of log entries */
						esc_html( _n( '%s entry', '%s entries', (int) $total, 'delopay' ) ),
						esc_html( number_format_i18n( (int) $total ) )
					);
					?>
				</span>
				<?php if ( $page > 1 ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1, $base ) ); ?>">
						<?php esc_html_e( '‹ Newer', 'delopay' ); ?>
					</a>
				<?php endif; ?>
				<span class="delopay-log-page">
					<?php
					printf(
						/* translators: 1: current page number, 2: total number of pages */
						esc_html__( 'Page %1$d of %2$d', 'delopay' ),
						(int) $page,
						(int) $max
					);
					?>
				</span>
				<?php if ( $page < $max ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1, $base ) ); ?>">
						<?php esc_html_e( 'Older ›', 'delopay' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Translated name for a level, used by both the filter tabs and the pills.
	 *
	 * @param string $level One of the Delopay_Log::LEVEL_* constants.
	 */
	private static function level_label( $level ): string {
		switch ( $level ) {
			case Delopay_Log::LEVEL_ERROR:
				return __( 'Error', 'delopay' );
			case Delopay_Log::LEVEL_WARNING:
				return __( 'Warning', 'delopay' );
			default:
				return __( 'Info', 'delopay' );
		}
	}

	/**
	 * Re-encode the stored JSON with newlines so it is readable in the details
	 * pane. Falls back to the raw string when the context was clamped on the
	 * way in and no longer parses.
	 *
	 * @param string $context Stored JSON context.
	 */
	private static function pretty_context( $context ): string {
		$decoded = json_decode( $context, true );
		if ( null === $decoded ) {
			return $context;
		}
		$pretty = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return false === $pretty ? $context : $pretty;
	}
}
