<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * Customer payment switching support.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the switch-to-customer payment workflow.
 */
class CustomerPaymentSwitch {

	/**
	 * Admin-post action used to start the switch flow.
	 */
	public const REQUEST_ACTION = 'awcom_switch_to_customer_payment';

	/**
	 * Admin-post action used after User Switching completes.
	 */
	public const COMPLETE_REQUEST_ACTION = 'awcom_complete_customer_payment_switch';

	/**
	 * Request key for the order ID.
	 */
	public const REQUEST_ORDER_ID = 'awcom_order_id';

	/**
	 * Request key for the switch nonce.
	 */
	public const REQUEST_NONCE = 'awcom_switch_nonce';

	/**
	 * Request key for the pay-page switch token.
	 */
	public const REQUEST_SWITCH_TOKEN = 'awcom_switch_token';

	/**
	 * Request key for inline order-screen errors.
	 */
	public const REQUEST_ERROR = 'awcom_switch_error';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_post_' . self::REQUEST_ACTION, array( $this, 'handle_switch_to_customer_payment' ) );
		add_action( 'admin_post_' . self::COMPLETE_REQUEST_ACTION, array( $this, 'handle_complete_customer_payment_switch' ) );
		add_filter( 'user_switching_redirect_to', array( $this, 'filter_user_switching_redirect_to' ), 10, 4 );
		add_filter( 'user_has_cap', array( $this, 'filter_user_has_cap' ), 9, 4 );
		add_action( 'wp_head', array( $this, 'render_switch_back_button_styles' ) );
		add_action( 'woocommerce_pay_order_before_payment', array( $this, 'render_switched_payment_notice' ) );
	}

	/**
	 * Scope User Switching capability checks to this feature's signed order flow.
	 *
	 * User Switching treats the primitive `switch_users` capability as blanket
	 * permission to switch into any user account. This filter grants the
	 * narrower `switch_to_user` capability only when the current request is the
	 * plugin's own signed switch flow for the order's linked customer account.
	 *
	 * @param array    $allcaps Array of the user's primitive capabilities.
	 * @param string[] $caps    Required primitive capabilities for the request.
	 * @param mixed[]  $args    Capability check arguments.
	 * @param \WP_User $user    Current user object.
	 * @return array
	 */
	public function filter_user_has_cap( array $allcaps, array $caps, array $args, \WP_User $user ) {
		unset( $caps );

		if ( empty( $args[0] ) || 'switch_to_user' !== $args[0] ) {
			return $allcaps;
		}

		$target_user_id = isset( $args[2] ) ? absint( $args[2] ) : 0;

		if ( $target_user_id <= 0 || $target_user_id === (int) $user->ID ) {
			return $allcaps;
		}

		$order = $this->get_authorized_switch_order( $target_user_id );

		if ( ! $order ) {
			return $allcaps;
		}

		$allcaps['switch_to_user'] = true;

		return $allcaps;
	}

	/**
	 * Check whether the current request is authorized to switch to the order's customer.
	 *
	 * @param int $target_user_id Expected target user ID.
	 * @return \WC_Order|false
	 */
	protected function get_authorized_switch_order( $target_user_id = 0 ) {
		if ( ! Security::user_can_access() ) {
			return false;
		}

		$order_id = $this->get_requested_order_id();

		if ( $order_id <= 0 ) {
			return false;
		}

		$nonce = $this->get_request_value( self::REQUEST_NONCE );

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::get_nonce_action( $order_id ) ) ) {
			return false;
		}

		$order = $this->get_requested_order();

		if ( ! $this->is_switchable_order( $order ) ) {
			return false;
		}

		$customer = $this->get_order_customer_user( $order );

		if ( ! $customer instanceof \WP_User || $this->is_staff_user( $customer ) ) {
			return false;
		}

		if ( $target_user_id > 0 && (int) $customer->ID !== (int) $target_user_id ) {
			return false;
		}

		return $order;
	}

	/**
	 * Determine whether a user account should be treated as a staff account.
	 *
	 * @param \WP_User $user User object.
	 * @return bool
	 */
	protected function is_staff_user( \WP_User $user ) {
		return user_can( $user, 'manage_woocommerce' ) || user_can( $user, 'manage_options' );
	}

	/**
	 * Check whether User Switching is available.
	 *
	 * @return bool
	 */
	public static function is_user_switching_available() {
		return class_exists( '\\user_switching', false ) && is_callable( array( '\\user_switching', 'maybe_switch_url' ) );
	}

	/**
	 * Build the admin URL used to start the switch flow.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return string
	 */
	public static function get_switch_action_url( $order_id ) {
		$order_id = absint( $order_id );

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'               => self::REQUEST_ACTION,
					self::REQUEST_ORDER_ID => $order_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::get_nonce_action( $order_id ),
			self::REQUEST_NONCE
		);
	}

	/**
	 * Start the switch flow.
	 *
	 * @return void
	 */
	public function handle_switch_to_customer_payment() {
		$order_id = $this->get_requested_order_id();

		if ( ! Security::user_can_access() ) {
			wp_die( esc_html__( 'You do not have permission to start this payment workflow.', 'alynt-wc-customer-order-manager' ), 403 );
		}

		Security::verify_nonce( self::REQUEST_NONCE, self::get_nonce_action( $order_id ) );

		if ( ! self::is_user_switching_available() ) {
			$this->redirect_to_order_with_error(
				$order_id,
				__( 'Install and activate the User Switching plugin to use this payment workflow.', 'alynt-wc-customer-order-manager' )
			);
		}

		$order = $this->get_authorized_switch_order();
		if ( ! $order ) {
			$this->redirect_to_order_with_error(
				$order_id,
				__( 'This order is no longer available for payment. Refresh the order screen and confirm the order is still pending and unpaid.', 'alynt-wc-customer-order-manager' )
			);
		}

		$customer = $this->get_order_customer_user( $order );
		if ( ! $customer instanceof \WP_User ) {
			$this->redirect_to_order_with_error(
				$order_id,
				__( 'This order is not attached to a valid WordPress customer account. Assign the order to a customer account or send the payment link directly.', 'alynt-wc-customer-order-manager' )
			);
		}

		if ( $this->is_staff_user( $customer ) ) {
			$this->redirect_to_order_with_error(
				$order_id,
				__( 'This order is assigned to a staff account, so customer switching is unavailable. Use the payment link directly instead.', 'alynt-wc-customer-order-manager' )
			);
		}

		if ( (int) $customer->ID === (int) get_current_user_id() ) {
			$this->redirect_to_order_with_error(
				$order_id,
				__( 'This order is already assigned to your current WordPress account, so User Switching cannot switch into it. Use the payment link directly instead.', 'alynt-wc-customer-order-manager' )
			);
		}

		$switch_url = $this->get_native_switch_url( $customer, $order );
		if ( '' === $switch_url ) {
			$this->redirect_to_order_with_error(
				$order_id,
				__( 'Could not start the customer switch flow for this order. Try again, or send the payment link directly if the problem continues.', 'alynt-wc-customer-order-manager' )
			);
		}

		$token        = wp_generate_password( 20, false, false );
		$redirect_to  = $this->get_switch_completion_url( $order->get_id(), $token );
		$redirect_key = $this->get_post_switch_redirect_key( get_current_user_id(), $customer->ID );

		set_transient( $redirect_key, $redirect_to, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( $switch_url );
		exit;
	}

	/**
	 * Complete the handoff after the user switch succeeds.
	 *
	 * @return void
	 */
	public function handle_complete_customer_payment_switch() {
		$switch_token     = $this->get_request_value( self::REQUEST_SWITCH_TOKEN );
		$originating_user = $this->get_originating_user();
		$order = $this->get_requested_order();

		if ( '' === $switch_token || ! $originating_user instanceof \WP_User || ! $this->is_staff_user( $originating_user ) ) {
			wp_die( esc_html__( 'This payment handoff is no longer valid. Switch back to staff, then restart the payment flow from the order screen.', 'alynt-wc-customer-order-manager' ), 403 );
		}

		if ( ! $this->is_switchable_order( $order ) ) {
			wp_die( esc_html__( 'This order is no longer available for payment. Return to the order screen and confirm the order is still pending and unpaid.', 'alynt-wc-customer-order-manager' ) );
		}

		$customer = $this->get_order_customer_user( $order );
		if ( ! $customer instanceof \WP_User || (int) $customer->ID !== (int) get_current_user_id() ) {
			wp_die( esc_html__( 'This switched session does not match the customer assigned to the order. Switch back to staff, then restart the payment flow from the order screen.', 'alynt-wc-customer-order-manager' ), 403 );
		}

		$this->prime_switch_back_redirect( $order );

		$redirect_to = add_query_arg(
			array(
				self::REQUEST_ORDER_ID     => $order->get_id(),
				self::REQUEST_SWITCH_TOKEN => $switch_token,
			),
			$order->get_checkout_payment_url()
		);

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Override User Switching's post-switch redirect for this workflow.
	 *
	 * @param string        $redirect_to Current redirect target.
	 * @param string|null   $redirect_type Redirect type.
	 * @param \WP_User|null $new_user Switched-to user.
	 * @param \WP_User|null $old_user Originating user.
	 * @return string
	 */
	public function filter_user_switching_redirect_to( $redirect_to, $redirect_type, $new_user, $old_user ) {
		unset( $redirect_type );

		if ( ! $new_user instanceof \WP_User || ! $old_user instanceof \WP_User ) {
			return $redirect_to;
		}

		$redirect_key   = $this->get_post_switch_redirect_key( $old_user->ID, $new_user->ID );
		$stored_redirect = get_transient( $redirect_key );

		if ( ! is_string( $stored_redirect ) || '' === $stored_redirect ) {
			return $redirect_to;
		}

		delete_transient( $redirect_key );

		return $stored_redirect;
	}

	/**
	 * Show a reminder on the pay page while the session is switched.
	 *
	 * @return void
	 */
	public function render_switched_payment_notice() {
		if ( ! function_exists( 'current_user_switched' ) || ! current_user_switched() ) {
			return;
		}

		if ( '' === $this->get_request_value( self::REQUEST_SWITCH_TOKEN ) ) {
			return;
		}

		$order = $this->get_requested_order();
		if ( $this->is_switchable_order( $order ) ) {
			$this->prime_switch_back_redirect( $order );
		}

		wc_print_notice(
			__( 'You are completing this payment while switched into the customer account. Use the admin bar to switch back after payment.', 'alynt-wc-customer-order-manager' ),
			'notice'
		);
	}

	/**
	 * Style the floating User Switching footer link on switched pay pages.
	 *
	 * User Switching prints the footer switch-back control with inline styles,
	 * so these overrides intentionally use specific selectors and !important.
	 *
	 * @return void
	 */
	public function render_switch_back_button_styles() {
		if ( ! function_exists( 'current_user_switched' ) || ! current_user_switched() ) {
			return;
		}

		if ( '' === $this->get_request_value( self::REQUEST_SWITCH_TOKEN ) || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		?>
		<style id="awcom-switch-back-button-styles">
			p[style*="position: fixed"][style*="z-index:99999"] {
				left: 24px !important;
				bottom: 24px !important;
				z-index: 2147483000 !important;
			}

			p[style*="position: fixed"][style*="z-index:99999"] > a[href*="action=switch_to_olduser"] {
				display: inline-flex !important;
				align-items: center !important;
				justify-content: center !important;
				min-height: 44px !important;
				padding: 12px 18px !important;
				border: 2px solid #1d2327 !important;
				border-radius: 999px !important;
				background: #ffffff !important;
				color: #1d2327 !important;
				font-size: 14px !important;
				font-weight: 700 !important;
				line-height: 1.2 !important;
				text-decoration: none !important;
				box-shadow: 0 12px 28px rgba( 0, 0, 0, 0.18 ) !important;
				transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease !important;
			}

			p[style*="position: fixed"][style*="z-index:99999"] > a[href*="action=switch_to_olduser"]:hover,
			p[style*="position: fixed"][style*="z-index:99999"] > a[href*="action=switch_to_olduser"]:focus {
				background: #f6f7f7 !important;
				color: #1d2327 !important;
				box-shadow: 0 16px 34px rgba( 0, 0, 0, 0.22 ) !important;
				transform: translateY( -1px ) !important;
				outline: none !important;
			}

			@media (max-width: 767px) {
				p[style*="position: fixed"][style*="z-index:99999"] {
					left: 12px !important;
					right: 12px !important;
					bottom: 12px !important;
				}

				p[style*="position: fixed"][style*="z-index:99999"] > a[href*="action=switch_to_olduser"] {
					display: flex !important;
					width: 100% !important;
					padding: 12px 16px !important;
					text-align: center !important;
				}
			}

			@media (prefers-reduced-motion: reduce) {
				p[style*="position: fixed"][style*="z-index:99999"] > a[href*="action=switch_to_olduser"] {
					transition: none !important;
					transform: none !important;
				}
			}
		</style>
		<?php
	}

	/**
	 * Get the requested order ID.
	 *
	 * @return int
	 */
	protected function get_requested_order_id() {
		return absint( $this->get_request_value( self::REQUEST_ORDER_ID ) );
	}

	/**
	 * Load the requested order when WooCommerce is available.
	 *
	 * @return \WC_Order|false
	 */
	protected function get_requested_order() {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order_id = $this->get_requested_order_id();

		return $order_id > 0 ? wc_get_order( $order_id ) : false;
	}

	/**
	 * Check whether an order can use the switch flow.
	 *
	 * @param mixed $order WooCommerce order object.
	 * @return bool
	 */
	protected function is_switchable_order( $order ) {
		return $order instanceof \WC_Order && ! $order->is_paid() && 'pending' === $order->get_status();
	}

	/**
	 * Get the order's linked customer user.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return \WP_User|false
	 */
	protected function get_order_customer_user( \WC_Order $order ) {
		$customer_id = absint( $order->get_customer_id() );

		if ( $customer_id <= 0 ) {
			return false;
		}

		return get_user_by( 'id', $customer_id );
	}

	/**
	 * Read a request value as a string.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	protected function get_request_value( $key ) {
		return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
	}

	/**
	 * Build the completion URL used after the native user switch succeeds.
	 *
	 * @param int    $order_id WooCommerce order ID.
	 * @param string $token Flow token.
	 * @return string
	 */
	protected function get_switch_completion_url( $order_id, $token ) {
		return add_query_arg(
			array(
				'action'               => self::COMPLETE_REQUEST_ACTION,
				self::REQUEST_ORDER_ID => absint( $order_id ),
				self::REQUEST_SWITCH_TOKEN => sanitize_text_field( $token ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Build the transient key used to store the post-switch redirect.
	 *
	 * @param int $old_user_id Originating user ID.
	 * @param int $new_user_id Target customer user ID.
	 * @return string
	 */
	protected function get_post_switch_redirect_key( $old_user_id, $new_user_id ) {
		return 'awcom_switch_redirect_' . absint( $old_user_id ) . '_' . absint( $new_user_id );
	}

	/**
	 * Prime the switch-back redirect so staff return to the order screen.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return void
	 */
	protected function prime_switch_back_redirect( \WC_Order $order ) {
		$originating_user = $this->get_originating_user();

		if ( ! $originating_user instanceof \WP_User ) {
			return;
		}

		set_transient(
			$this->get_post_switch_redirect_key( get_current_user_id(), $originating_user->ID ),
			awcom_get_order_edit_url( $order->get_id() ),
			30 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Get the originating user for the current switched session.
	 *
	 * @return \WP_User|false
	 */
	protected function get_originating_user() {
		if ( function_exists( 'current_user_switched' ) ) {
			$originating_user = current_user_switched();

			if ( $originating_user instanceof \WP_User ) {
				return $originating_user;
			}
		}

		if ( class_exists( '\\user_switching', false ) && is_callable( array( '\\user_switching', 'get_old_user' ) ) ) {
			$originating_user = \user_switching::get_old_user();

			if ( $originating_user instanceof \WP_User ) {
				return $originating_user;
			}
		}

		return false;
	}

	/**
	 * Get a raw User Switching URL suitable for an HTTP redirect.
	 *
	 * `maybe_switch_url()` returns an HTML-escaped URL for link output, so we
	 * decode entities before using it in `wp_safe_redirect()`.
	 *
	 * @param \WP_User $customer Target customer account.
	 * @return string
	 */
	protected function get_native_switch_url( \WP_User $customer, \WC_Order $order ) {
		$switch_url = \user_switching::maybe_switch_url( $customer );

		if ( ! is_string( $switch_url ) || '' === $switch_url ) {
			return '';
		}

		return esc_url_raw(
			add_query_arg(
				array(
					self::REQUEST_ORDER_ID => $order->get_id(),
					self::REQUEST_NONCE    => wp_create_nonce( self::get_nonce_action( $order->get_id() ) ),
				),
				html_entity_decode( $switch_url, ENT_QUOTES, 'UTF-8' )
			)
		);
	}

	/**
	 * Redirect back to the order screen with an inline error notice.
	 *
	 * @param int    $order_id WooCommerce order ID.
	 * @param string $message Error message.
	 * @return void
	 */
	protected function redirect_to_order_with_error( $order_id, $message ) {
		$target = absint( $order_id ) > 0
			? awcom_get_order_edit_url( $order_id )
			: admin_url( 'edit.php?post_type=shop_order' );

		wp_safe_redirect(
			add_query_arg(
				self::REQUEST_ERROR,
				rawurlencode( $message ),
				$target
			)
		);
		exit;
	}

	/**
	 * Build the nonce action for an order switch request.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return string
	 */
	protected static function get_nonce_action( $order_id ) {
		return 'awcom_switch_to_customer_payment_' . absint( $order_id );
	}
}
