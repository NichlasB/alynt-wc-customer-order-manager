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
	 * Source identifier stored on orders created by this plugin.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public const ORDER_CREATED_VIA = 'alynt_wc_customer_order_manager';

	/**
	 * Order meta key indicating custom pricing was applied.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public const ORDER_META_HAS_CUSTOM_PRICING = '_awcom_has_custom_pricing';

	/**
	 * Order meta key indicating totals are pricing-locked.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public const ORDER_META_PRICING_LOCKED = '_awcom_pricing_locked';

	/**
	 * Order meta key storing the locked order total.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public const ORDER_META_LOCKED_TOTAL = '_awcom_locked_total';

	/**
	 * Order item meta key storing the adjusted per-unit price.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public const ITEM_META_CUSTOM_PRICE = '_awcom_custom_price';

	/**
	 * Order item meta key storing the original per-unit price before discount.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public const ITEM_META_CUSTOM_SUBTOTAL_PRICE = '_awcom_custom_subtotal_price';

	/**
	 * Order item meta key storing the discount description.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public const ITEM_META_DISCOUNT_DESCRIPTION = '_awcom_discount_description';

	/**
	 * Register all WooCommerce and WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_post_awcom_create_order', array( $this, 'handle_order_creation' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_cart_display_styles' ) );
		add_action( 'wp_ajax_wc_square_credit_card_get_order_amount', array( $this, 'log_square_order_amount_request' ), 1 );
		add_action( 'wp_ajax_nopriv_wc_square_credit_card_get_order_amount', array( $this, 'log_square_order_amount_request' ), 1 );

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
	 * Determine whether the current request is the Square order-amount lookup.
	 *
	 * @since 1.0.6
	 *
	 * @return bool True when handling Square's order amount AJAX action.
	 */
	private function is_square_order_amount_request() {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return 'wc_square_credit_card_get_order_amount' === $action;
	}

	/**
	 * Write a message to WooCommerce logs.
	 *
	 * @since 1.0.6
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Optional structured context.
	 * @return void
	 */
	private function write_log( $level, $message, array $context = array() ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger_context           = $context;
		$logger_context['source'] = 'awcom-payment-debug';

		wc_get_logger()->log( $level, $message, $logger_context );
	}

	/**
	 * Log a summarized snapshot of an order for payment debugging.
	 *
	 * @since 1.0.6
	 *
	 * @param string    $stage  Short label describing when the snapshot was taken.
	 * @param \WC_Order $order  The order being logged.
	 * @param array     $extra  Optional extra context values.
	 * @return void
	 */
	protected function log_payment_order_snapshot( $stage, $order, array $extra = array() ) {
		if ( ! $order instanceof \WC_Order ) {
			$this->write_log(
				'warning',
				'Payment debug snapshot requested without a valid order.',
				array_merge(
					array(
						'stage' => $stage,
					),
					$extra
				)
			);
			return;
		}

		$item_total = 0.0;
		$fee_total  = 0.0;
		$items      = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$item_total += (float) $item->get_total();
			$items[] = array(
				'item_id'            => $item_id,
				'product_id'         => $item->get_product_id(),
				'variation_id'       => $item->get_variation_id(),
				'quantity'           => (int) $item->get_quantity(),
				'subtotal'           => (float) $item->get_subtotal(),
				'total'              => (float) $item->get_total(),
				'custom_price_meta'  => $item->get_meta( self::ITEM_META_CUSTOM_PRICE, true ),
				'custom_base_meta'   => $item->get_meta( self::ITEM_META_CUSTOM_SUBTOTAL_PRICE, true ),
				'discount_meta'      => $item->get_meta( self::ITEM_META_DISCOUNT_DESCRIPTION, true ),
			);
		}

		foreach ( $order->get_fees() as $fee ) {
			$fee_total += (float) $fee->get_total();
		}

		$this->write_log(
			'debug',
			'Payment order snapshot',
			array_merge(
				array(
					'stage'                  => $stage,
					'order_id'               => $order->get_id(),
					'order_key'              => $order->get_order_key(),
					'status'                 => $order->get_status(),
					'created_via'            => $order->get_created_via(),
					'is_paid'                => $order->is_paid(),
					'item_total'             => $item_total,
					'shipping_total'         => (float) $order->get_shipping_total(),
					'fee_total'              => $fee_total,
					'tax_total'              => (float) $order->get_total_tax(),
					'order_total'            => (float) $order->get_total(),
					'locked_total_meta'      => $order->get_meta( self::ORDER_META_LOCKED_TOTAL, true ),
					'has_custom_pricing'     => $order->get_meta( self::ORDER_META_HAS_CUSTOM_PRICING, true ),
					'pricing_locked'         => $order->get_meta( self::ORDER_META_PRICING_LOCKED, true ),
					'item_count'             => count( $items ),
					'items'                  => $items,
				),
				$extra
			)
		);
	}

	/**
	 * Log the inbound Square order-amount lookup request before Square handles it.
	 *
	 * @since 1.0.6
	 *
	 * @return void
	 */
	public function log_square_order_amount_request() {
		$order_id  = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
		$order     = $order_id > 0 ? wc_get_order( $order_id ) : false;

		$this->write_log(
			'debug',
			'Square order amount request received.',
			array(
				'order_id'        => $order_id,
				'posted_order_key'=> $order_key,
				'is_pay_order'    => isset( $_POST['is_pay_order'] ) ? wc_string_to_bool( wp_unslash( $_POST['is_pay_order'] ) ) : null,
				'order_found'     => (bool) $order,
				'order_key_match' => $order instanceof \WC_Order ? hash_equals( $order->get_order_key(), $order_key ) : false,
			)
		);

		$this->log_payment_order_snapshot(
			'square_get_order_amount_request',
			$order,
			array(
				'posted_order_key' => $order_key,
			)
		);
	}

	/**
	 * Write low-noise debug logs for payment troubleshooting.
	 *
	 * @since 1.0.6
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	private function log( $message ) {
		if ( ! $this->is_square_order_amount_request() ) {
			return;
		}

		$this->write_log( 'debug', $message );
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
		$this->write_log( 'error', $message );
	}

	protected function cleanup_failed_order_creation( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( self::ORDER_CREATED_VIA !== $order->get_created_via() ) {
			return;
		}

		try {
			$order->delete( true );
		} catch ( \Throwable $throwable ) {
			$this->log_order_handler_error( 'Failed to clean up incomplete order #' . $order->get_id() . ': ' . $throwable->getMessage() );
		}
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
