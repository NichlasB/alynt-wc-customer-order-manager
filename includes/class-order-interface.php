<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * Order creation interface for admins.
 *
 * Renders the admin UI for building a new WooCommerce order on behalf of a
 * customer, including product search, shipping selection, and AJAX endpoints
 * that power the real-time order builder.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/traits/trait-order-interface-render.php';
require_once __DIR__ . '/traits/trait-order-interface-ajax-products.php';
require_once __DIR__ . '/traits/trait-order-interface-ajax-shipping.php';

/**
 * Provides the admin order creation UI and supporting AJAX endpoints.
 *
 * @since 1.0.0
 */
class OrderInterface {
	use OrderInterfaceRenderTrait;
	use OrderInterfaceAjaxProductsTrait;
	use OrderInterfaceAjaxShippingTrait;

	/**
	 * ID of the customer for whom an order is being created.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private $customer_id;

	/**
	 * Register admin menu and AJAX hooks for the order creation interface.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'wp_ajax_awcom_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_awcom_get_shipping_methods', array( $this, 'ajax_get_shipping_methods' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Send a structured AJAX error response for the order builder UI.
	 *
	 * @since 1.0.6
	 *
	 * @param string $message   User-facing message.
	 * @param int    $status    HTTP status code.
	 * @param bool   $retryable Whether the client should offer a retry action.
	 * @return void
	 */
	protected function send_order_interface_error( $message, $status = 400, $retryable = false ) {
		wp_send_json_error(
			array(
				'message'   => $message,
				'retryable' => (bool) $retryable,
			),
			$status
		);
	}

	/**
	 * Log order interface failures without exposing raw details in the UI.
	 *
	 * @since 1.0.6
	 *
	 * @param string $message Error details for debugging.
	 * @return void
	 */
	protected function log_order_interface_error( $message ) {
		// Logging removed in pre-release cleanup.
	}

	/**
	 * Register the hidden "Create Order" submenu page.
	 *
	 * The page is attached to null (no parent) so it does not appear in
	 * the menu but is accessible via direct URL.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		// phpcs:disable WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability.
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability.
		add_submenu_page(
			null,
			__( 'Create Order', 'alynt-wc-customer-order-manager' ),
			__( 'Create Order', 'alynt-wc-customer-order-manager' ),
			'manage_woocommerce',
			'alynt-wc-customer-order-manager-create-order',
			array( $this, 'render_create_order_page' )
		);
		// phpcs:enable
	}

	/**
	 * Enqueue scripts and styles for the Create Order admin page.
	 *
	 * Loads Select2, accounting.js, and the plugin's own order interface
	 * assets. Passes localised variables to JavaScript via wp_localize_script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'admin_page_alynt-wc-customer-order-manager-create-order' !== $hook ) {
			return;
		}

		$select_script_handle         = '';
		$select_style_dependencies    = array();
		$order_interface_dependencies = array( 'jquery' );

		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			$select_script_handle = 'selectWoo';
		} elseif ( wp_script_is( 'select2', 'registered' ) ) {
			$select_script_handle = 'select2';
		}

		if ( $select_script_handle ) {
			wp_enqueue_script( $select_script_handle );
			$order_interface_dependencies[] = $select_script_handle;
		}

		if ( wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'select2' );
			$select_style_dependencies[] = 'select2';
		} elseif ( wp_style_is( 'selectWoo', 'registered' ) ) {
			wp_enqueue_style( 'selectWoo' );
			$select_style_dependencies[] = 'selectWoo';
		}

		if ( wp_script_is( 'accounting', 'registered' ) ) {
			wp_enqueue_script( 'accounting' );
			$order_interface_dependencies[] = 'accounting';
		}

		wp_enqueue_style(
			'awcom-order-interface',
			AWCOM_PLUGIN_URL . 'assets/css/order-interface.css',
			$select_style_dependencies,
			AWCOM_VERSION
		);

		wp_enqueue_script(
			'awcom-order-interface-core',
			AWCOM_PLUGIN_URL . 'assets/js/order-interface-core.js',
			$order_interface_dependencies,
			AWCOM_VERSION,
			true
		);

		wp_enqueue_script(
			'awcom-order-interface-shipping',
			AWCOM_PLUGIN_URL . 'assets/js/order-interface-shipping.js',
			array( 'awcom-order-interface-core' ),
			AWCOM_VERSION,
			true
		);

		wp_localize_script(
			'awcom-order-interface-core',
			'awcomOrderVars',
			array(
				'ajaxurl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'awcom-order-interface' ),
				'currency_symbol' => get_woocommerce_currency_symbol(),
				'i18n'            => array(
					'search_products'      => __( 'Search for a product...', 'alynt-wc-customer-order-manager' ),
					'no_products'          => __( 'No products found', 'alynt-wc-customer-order-manager' ),
					'product_search_error' => __( 'Could not load matching products. Please try again.', 'alynt-wc-customer-order-manager' ),
					'remove_item'          => __( 'Remove item', 'alynt-wc-customer-order-manager' ),
					'calculating'          => __( 'Calculating...', 'alynt-wc-customer-order-manager' ),
					'no_shipping'          => __( 'No shipping methods available', 'alynt-wc-customer-order-manager' ),
					'shipping_error'       => __( 'Error calculating shipping methods', 'alynt-wc-customer-order-manager' ),
					'shipping_timeout'     => __( 'Shipping is taking longer than expected. Please try again.', 'alynt-wc-customer-order-manager' ),
					'offline_error'        => __( 'You appear to be offline. Check your connection and try again.', 'alynt-wc-customer-order-manager' ),
					'retry_shipping'       => __( 'Try Again', 'alynt-wc-customer-order-manager' ),
					'no_items'             => __( 'Please add at least one item to the order.', 'alynt-wc-customer-order-manager' ),
					'invalid_quantity'     => __( 'Enter a quantity greater than 0 for each item.', 'alynt-wc-customer-order-manager' ),
					'no_shipping_selected' => __( 'Please select a shipping method.', 'alynt-wc-customer-order-manager' ),
					'creating_order'       => __( 'Creating order...', 'alynt-wc-customer-order-manager' ),
				),
			)
		);
	}
}
