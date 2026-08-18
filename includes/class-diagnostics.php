<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * Safe diagnostics logging and admin tools.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.1.3
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Provides opt-in diagnostics storage and cleanup.
 *
 * @since 1.1.3
 */
class Diagnostics {
	const SETTINGS_OPTION = 'awcom_diagnostics_settings';
	const EVENTS_OPTION   = 'awcom_diagnostics_events';
	const CAPABILITY      = 'manage_woocommerce';
	const MAX_EVENTS      = 200;

	/**
	 * Allowed severity levels in ascending order.
	 *
	 * @since 1.1.3
	 *
	 * @var string[]
	 */
	private static $levels = array( 'debug', 'info', 'warning', 'error', 'critical' );

	/**
	 * Record a diagnostics event when diagnostics are enabled.
	 *
	 * @since 1.1.3
	 *
	 * @param string $category Event category.
	 * @param string $level    Event severity.
	 * @param string $code     Short event code.
	 * @param string $message  Human-readable summary.
	 * @param array  $context  Additional context.
	 * @return void
	 */
	public static function log( $category, $level, $code, $message, array $context = array() ) {
		$settings = self::get_settings();

		if ( ! $settings['enabled'] || ! self::passes_level_threshold( $level, $settings['min_level'] ) ) {
			return;
		}

		$events   = self::get_events();
		$events[] = array(
			'timestamp' => gmdate( 'c' ),
			'level'     => sanitize_key( $level ),
			'category'  => sanitize_key( $category ),
			'code'      => sanitize_key( $code ),
			'message'   => sanitize_text_field( $message ),
			'context'   => self::redact_context( $context ),
		);

		update_option( self::EVENTS_OPTION, self::prune_events( $events, $settings['retention_days'] ), false );
	}

	/**
	 * Get normalized diagnostics settings.
	 *
	 * @since 1.1.3
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings       = get_option( self::SETTINGS_OPTION, array() );
		$settings       = is_array( $settings ) ? $settings : array();
		$retention_days = (int) ( $settings['retention_days'] ?? 30 );

		return array(
			'enabled'        => ! empty( $settings['enabled'] ),
			'min_level'      => in_array( $settings['min_level'] ?? '', self::$levels, true ) ? $settings['min_level'] : 'warning',
			'retention_days' => in_array( $retention_days, array( 7, 14, 30, 90 ), true ) ? $retention_days : 30,
		);
	}

	/**
	 * Get stored diagnostics events.
	 *
	 * @since 1.1.3
	 *
	 * @return array
	 */
	public static function get_events() {
		$events = get_option( self::EVENTS_OPTION, array() );

		return is_array( $events ) ? $events : array();
	}

	/**
	 * Remove old and overflow diagnostics events.
	 *
	 * @since 1.1.3
	 *
	 * @param array $events         Events to prune.
	 * @param int   $retention_days Retention period in days.
	 * @return array
	 */
	public static function prune_events( array $events, $retention_days ) {
		$cutoff = time() - ( absint( $retention_days ) * DAY_IN_SECONDS );
		$events = array_values(
			array_filter(
				$events,
				static function ( $event ) use ( $cutoff ) {
					return ! empty( $event['timestamp'] ) && strtotime( $event['timestamp'] ) >= $cutoff;
				}
			)
		);

		if ( count( $events ) > self::MAX_EVENTS ) {
			$events = array_slice( $events, -1 * self::MAX_EVENTS );
		}

		return $events;
	}

	/**
	 * Determine whether a level should be logged.
	 *
	 * @since 1.1.3
	 *
	 * @param string $level     Candidate level.
	 * @param string $threshold Minimum configured level.
	 * @return bool
	 */
	private static function passes_level_threshold( $level, $threshold ) {
		return array_search( $level, self::$levels, true ) >= array_search( $threshold, self::$levels, true );
	}

	/**
	 * Redact sensitive context before storage or export.
	 *
	 * @since 1.1.3
	 *
	 * @param array $context Raw event context.
	 * @return array
	 */
	private static function redact_context( array $context ) {
		$redacted = array();

		foreach ( $context as $key => $value ) {
			$key_string = (string) $key;
			if ( preg_match( '/password|secret|token|api[_-]?key|authorization|cookie|nonce|request_body/i', $key_string ) ) {
				$redacted[ $key_string ] = '[redacted]';
				continue;
			}

			if ( is_array( $value ) ) {
				$redacted[ $key_string ] = self::redact_context( $value );
				continue;
			}

			$value = is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value );
			if ( strlen( $value ) > 300 ) {
				$value = substr( $value, 0, 300 ) . '...';
			}

			$redacted[ $key_string ] = sanitize_text_field( $value );
		}

		return $redacted;
	}
}
