<?php
/**
 * Security utilities for capability and nonce checks.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Provides static helper methods for access control and nonce verification.
 *
 * @since 1.0.0
 */
class Security {
	/**
	 * Check whether the current user has access to plugin features.
	 *
	 * Access is granted to users with the administrator or shop_manager role.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the user is allowed, false otherwise.
	 */
	public static function user_can_access() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Verify a nonce from the current request and die on failure.
	 *
	 * Checks $_REQUEST[$nonce_name] against the expected $action. Calls
	 * wp_die() with an error message if the nonce is missing or invalid.
	 *
	 * @since 1.0.0
	 *
	 * @param string $nonce_name The key in $_REQUEST that holds the nonce value.
	 * @param string $action     The nonce action string to verify against.
	 * @return void
	 */
	public static function verify_nonce( $nonce_name, $action ) {
		$nonce = isset( $_REQUEST[ $nonce_name ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $nonce_name ] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'Security check failed', 'alynt-wc-customer-order-manager' ) );
		}
	}
}
