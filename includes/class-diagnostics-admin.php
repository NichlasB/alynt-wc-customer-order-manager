<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * Diagnostics admin screen and handlers.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.1.3
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Provides diagnostics settings, viewing, export, and cleanup.
 *
 * @since 1.1.3
 */
class DiagnosticsAdmin {
	/**
	 * Register admin actions for diagnostics settings and data.
	 *
	 * @since 1.1.3
	 */
	public function __construct() {
		add_action( 'admin_post_awcom_save_diagnostics_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_awcom_clear_diagnostics', array( $this, 'handle_clear_events' ) );
		add_action( 'admin_post_awcom_export_diagnostics', array( $this, 'handle_export_events' ) );
	}

	/**
	 * Render the diagnostics admin screen.
	 *
	 * @since 1.1.3
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( Diagnostics::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'alynt-wc-customer-order-manager' ) );
		}

		$settings = Diagnostics::get_settings();
		$events   = array_reverse( Diagnostics::get_events() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Customer Manager Diagnostics', 'alynt-wc-customer-order-manager' ); ?></h1>

			<?php self::render_notices(); ?>

			<h2><?php esc_html_e( 'Settings', 'alynt-wc-customer-order-manager' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="awcom_save_diagnostics_settings">
				<?php wp_nonce_field( 'awcom_save_diagnostics_settings' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Diagnostics', 'alynt-wc-customer-order-manager' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
									<?php esc_html_e( 'Enable diagnostics logging', 'alynt-wc-customer-order-manager' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Diagnostics are disabled by default. Sensitive values are redacted before storage or export.', 'alynt-wc-customer-order-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="awcom-diagnostics-min-level"><?php esc_html_e( 'Minimum level', 'alynt-wc-customer-order-manager' ); ?></label></th>
							<td>
								<select id="awcom-diagnostics-min-level" name="min_level">
									<?php foreach ( self::get_levels() as $level ) : ?>
										<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $settings['min_level'], $level ); ?>>
											<?php echo esc_html( ucfirst( $level ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="awcom-diagnostics-retention-days"><?php esc_html_e( 'Retention', 'alynt-wc-customer-order-manager' ); ?></label></th>
							<td>
								<select id="awcom-diagnostics-retention-days" name="retention_days">
									<?php foreach ( array( 7, 14, 30, 90 ) as $days ) : ?>
										<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( $settings['retention_days'], $days ); ?>>
											<?php
											printf(
												esc_html(
													/* translators: %d: number of days. */
													_n( '%d day', '%d days', $days, 'alynt-wc-customer-order-manager' )
												),
												esc_html( number_format_i18n( $days ) )
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Diagnostics Settings', 'alynt-wc-customer-order-manager' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Health', 'alynt-wc-customer-order-manager' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr><th scope="row"><?php esc_html_e( 'Plugin version', 'alynt-wc-customer-order-manager' ); ?></th><td><?php echo esc_html( AWCOM_VERSION ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'WordPress version', 'alynt-wc-customer-order-manager' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'PHP version', 'alynt-wc-customer-order-manager' ); ?></th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Storage', 'alynt-wc-customer-order-manager' ); ?></th><td><?php esc_html_e( 'Option-backed ring buffer', 'alynt-wc-customer-order-manager' ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Stored events', 'alynt-wc-customer-order-manager' ); ?></th><td><?php echo esc_html( number_format_i18n( count( $events ) ) ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Last event', 'alynt-wc-customer-order-manager' ); ?></th><td><?php echo esc_html( self::get_last_event_time( $events ) ); ?></td></tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Recent Events', 'alynt-wc-customer-order-manager' ); ?></h2>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=awcom_export_diagnostics' ), 'awcom_export_diagnostics' ) ); ?>">
					<?php esc_html_e( 'Export Events', 'alynt-wc-customer-order-manager' ); ?>
				</a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Clear all diagnostics events?', 'alynt-wc-customer-order-manager' ) ); ?>');">
				<input type="hidden" name="action" value="awcom_clear_diagnostics">
				<?php wp_nonce_field( 'awcom_clear_diagnostics' ); ?>
				<?php submit_button( __( 'Clear Events', 'alynt-wc-customer-order-manager' ), 'delete', 'submit', false ); ?>
			</form>

			<?php self::render_events_table( $events ); ?>
		</div>
		<?php
	}

	/**
	 * Save diagnostics settings from the admin form.
	 *
	 * @since 1.1.3
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		$this->verify_action( 'awcom_save_diagnostics_settings' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified before reading form data.
		$settings = array(
			'enabled'        => isset( $_POST['enabled'] ),
			'min_level'      => isset( $_POST['min_level'] ) ? sanitize_key( wp_unslash( $_POST['min_level'] ) ) : 'warning',
			'retention_days' => isset( $_POST['retention_days'] ) ? absint( wp_unslash( $_POST['retention_days'] ) ) : 30,
		);
		// phpcs:enable

		if ( ! in_array( $settings['min_level'], self::get_levels(), true ) ) {
			$settings['min_level'] = 'warning';
		}

		if ( ! in_array( $settings['retention_days'], array( 7, 14, 30, 90 ), true ) ) {
			$settings['retention_days'] = 30;
		}

		update_option( Diagnostics::SETTINGS_OPTION, $settings, false );
		update_option( Diagnostics::EVENTS_OPTION, Diagnostics::prune_events( Diagnostics::get_events(), $settings['retention_days'] ), false );
		$this->redirect_with_notice( 'settings_saved' );
	}

	/**
	 * Clear stored diagnostics events.
	 *
	 * @since 1.1.3
	 *
	 * @return void
	 */
	public function handle_clear_events() {
		$this->verify_action( 'awcom_clear_diagnostics' );
		delete_option( Diagnostics::EVENTS_OPTION );
		$this->redirect_with_notice( 'events_cleared' );
	}

	/**
	 * Export stored diagnostics events as JSON.
	 *
	 * @since 1.1.3
	 *
	 * @return void
	 */
	public function handle_export_events() {
		$this->verify_action( 'awcom_export_diagnostics' );

		$payload = array(
			'plugin'     => 'alynt-wc-customer-order-manager',
			'version'    => AWCOM_VERSION,
			'exportedAt' => gmdate( 'c' ),
			'settings'   => Diagnostics::get_settings(),
			'events'     => Diagnostics::get_events(),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=awcom-diagnostics-' . gmdate( 'Ymd-His' ) . '.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Get allowed diagnostics levels.
	 *
	 * @since 1.1.3
	 *
	 * @return string[]
	 */
	private static function get_levels() {
		return array( 'debug', 'info', 'warning', 'error', 'critical' );
	}

	/**
	 * Render saved-result notices.
	 *
	 * @since 1.1.3
	 *
	 * @return void
	 */
	private static function render_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads a redirect-only status flag.
		$notice = isset( $_GET['awcom_diagnostics_notice'] ) ? sanitize_key( wp_unslash( $_GET['awcom_diagnostics_notice'] ) ) : '';

		if ( 'settings_saved' === $notice ) {
			echo '<div class="notice notice-success is-dismissible" role="status"><p>' . esc_html__( 'Diagnostics settings saved.', 'alynt-wc-customer-order-manager' ) . '</p></div>';
		} elseif ( 'events_cleared' === $notice ) {
			echo '<div class="notice notice-success is-dismissible" role="status"><p>' . esc_html__( 'Diagnostics events cleared.', 'alynt-wc-customer-order-manager' ) . '</p></div>';
		}
	}

	/**
	 * Render the diagnostics events table.
	 *
	 * @since 1.1.3
	 *
	 * @param array $events Events to render.
	 * @return void
	 */
	private static function render_events_table( array $events ) {
		if ( empty( $events ) ) {
			echo '<p>' . esc_html__( 'No diagnostics events have been recorded.', 'alynt-wc-customer-order-manager' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Time', 'alynt-wc-customer-order-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Level', 'alynt-wc-customer-order-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Category', 'alynt-wc-customer-order-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Code', 'alynt-wc-customer-order-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Message', 'alynt-wc-customer-order-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Context', 'alynt-wc-customer-order-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $events as $event ) : ?>
					<tr>
						<td><?php echo esc_html( $event['timestamp'] ?? '' ); ?></td>
						<td><?php echo esc_html( $event['level'] ?? '' ); ?></td>
						<td><?php echo esc_html( $event['category'] ?? '' ); ?></td>
						<td><?php echo esc_html( $event['code'] ?? '' ); ?></td>
						<td><?php echo esc_html( $event['message'] ?? '' ); ?></td>
						<td><code><?php echo esc_html( wp_json_encode( $event['context'] ?? array() ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get the last event timestamp for display.
	 *
	 * @since 1.1.3
	 *
	 * @param array $events Reverse-sorted events.
	 * @return string
	 */
	private static function get_last_event_time( array $events ) {
		if ( empty( $events[0]['timestamp'] ) ) {
			return __( 'None recorded', 'alynt-wc-customer-order-manager' );
		}

		return $events[0]['timestamp'];
	}

	/**
	 * Verify capability and nonce for a diagnostics admin action.
	 *
	 * @since 1.1.3
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function verify_action( $action ) {
		if ( ! current_user_can( Diagnostics::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'alynt-wc-customer-order-manager' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirect back to the diagnostics page with a notice flag.
	 *
	 * @since 1.1.3
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function redirect_with_notice( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => 'alynt-wc-customer-order-manager-diagnostics',
					'awcom_diagnostics_notice' => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
