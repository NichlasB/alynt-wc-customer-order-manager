<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * Core order handler for admin-created orders with customer group pricing.
 *
 * Registers all hooks required to create orders from the admin interface,
 * preserve custom pricing through WooCommerce total recalculations, and
 * correct cart/checkout totals for logged-in customers with group pricing.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/traits/trait-order-handler-admin-create.php';
require_once __DIR__ . '/traits/trait-order-handler-admin-helpers.php';
require_once __DIR__ . '/traits/trait-order-handler-total-protection.php';
require_once __DIR__ . '/traits/trait-order-handler-cart-pricing-core.php';
require_once __DIR__ . '/traits/trait-order-handler-cart-display.php';
require_once __DIR__ . '/traits/trait-order-handler-checkout.php';
require_once __DIR__ . '/traits/trait-order-handler-payment-overrides.php';

/**
 * Manages order creation, pricing locks, and cart/checkout overrides.
 *
 * @since 1.0.0
 */
class OrderHandler {
	use OrderHandlerAdminCreateTrait;
	use OrderHandlerAdminHelpersTrait;
	use OrderHandlerTotalProtectionTrait;
	use OrderHandlerCartPricingCoreTrait;
	use OrderHandlerCartDisplayTrait;
	use OrderHandlerCheckoutTrait;
	use OrderHandlerPaymentOverridesTrait;

	/**
	 * Accumulated shipping errors from the most recent shipping method lookup.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $shipping_errors = array();

	/**
	 * Register all WooCommerce and WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_post_awcom_create_order', array( $this, 'handle_order_creation' ) );

		// Preserve custom totals for orders created by this plugin.
		add_action( 'woocommerce_order_before_calculate_totals', array( $this, 'prevent_total_recalculation' ), 10, 2 );
		add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'restore_custom_prices_after_recalculation' ), 10, 2 );
		add_filter( 'woocommerce_order_item_get_subtotal', array( $this, 'preserve_item_subtotal' ), 10, 2 );
		add_filter( 'woocommerce_order_item_get_total', array( $this, 'preserve_item_total' ), 10, 2 );
		add_filter( 'woocommerce_order_get_total', array( $this, 'lock_order_total' ), 9999, 2 );

		// Display discount labels and gateway-specific overrides.
		add_filter( 'woocommerce_cart_item_name', array( $this, 'add_discount_info_to_cart_item' ), 10, 3 );
		add_filter( 'woocommerce_order_get_total', array( $this, 'filter_order_total_for_payment' ), 10, 2 );
		add_action( 'woocommerce_before_pay_action', array( $this, 'fix_order_before_payment' ), 10, 1 );
		add_filter( 'wc_ppcp_cart_data', array( $this, 'fix_paypal_cart_data' ), 10, 1 );
		add_filter( 'woocommerce_cart_get_total', array( $this, 'override_cart_total' ), 9999, 1 );
	}

	/**
	 * No-op logger retained for backwards compatibility with existing calls.
	 *
	 * @since 1.0.6
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	private function log( $message ) {
		// Logging removed in pre-release cleanup.
	}

	/**
	 * Log actionable errors without surfacing technical details to users.
	 *
	 * @since 1.0.6
	 *
	 * @param string $message Error details for debugging.
	 * @return void
	 */
	protected function log_order_handler_error( $message ) {
		// Logging removed in pre-release cleanup.
	}

	/**
	 * Redirect to the customer manager list with an error notice.
	 *
	 * @since 1.0.6
	 *
	 * @param string $message User-facing error message.
	 * @return void
	 */
	protected function redirect_to_customer_manager_with_error( $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'alynt-wc-customer-order-manager',
					'error' => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
