<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * Payment link copy button for WooCommerce order edit screens.
 *
 * Renders a "Copy Payment Link" button in the order actions sidebar for
 * pending, unpaid orders so admins can quickly share the payment URL.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.0.2
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a copy-to-clipboard payment link button on the order edit screen.
 *
 * @since 1.0.2
 */
class PaymentLink {

	/**
	 * Register hooks for script enqueuing and the payment link UI.
	 *
	 * @since 1.0.2
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_order_actions_end', array( $this, 'add_payment_link_copy_button' ) );
	}

	/**
	 * Enqueue CSS for the payment link button on WooCommerce order edit screens.
	 *
	 * Detects both legacy CPT-based order pages and HPOS order pages.
	 *
	 * @since 1.0.2
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		$is_order_page = false;
		$screen        = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! in_array( $hook, array( 'post.php', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}

		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading current admin screen query args only. */
		if ( $screen && 'post' === $screen->base && 'shop_order' === $screen->post_type ) {
			$legacy_order_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
			$is_order_page   = $legacy_order_id > 0;
		}

		if ( ! $is_order_page && $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
			$hpos_order_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
			$hpos_action   = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

			$is_order_page = ( 'edit' === $hpos_action && $hpos_order_id > 0 );
		}
		/* phpcs:enable */

		if ( ! $is_order_page ) {
			return;
		}

		// Enqueue Dashicons.
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'awcom-payment-link', plugins_url( '../assets/css/payment-link.css', __FILE__ ), array(), '1.0.1' );
		wp_enqueue_script(
			'awcom-payment-link',
			plugins_url( '../assets/js/payment-link.js', __FILE__ ),
			array(),
			AWCOM_VERSION,
			true
		);
		wp_localize_script(
			'awcom-payment-link',
			'awcomPaymentLinkVars',
			array(
				'i18n' => array(
					'no_payment_link' => __( 'No payment link found to copy.', 'alynt-wc-customer-order-manager' ),
					'copied'          => __( 'Payment link copied to clipboard!', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: payment link URL. */
					'copy_failed'     => __( 'Failed to copy. Please copy manually: %s', 'alynt-wc-customer-order-manager' ),
				),
			)
		);
	}

	/**
	 * Render the "Copy Payment Link" button in the order actions sidebar.
	 *
	 * Only displayed for pending, unpaid orders. The button uses the
	 * Clipboard API with a textarea fallback for older browsers.
	 *
	 * @since 1.0.2
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return void
	 */
	public function add_payment_link_copy_button( $order_id ) {
		// Get the order object from the ID.
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Only show the button for unpaid orders.
		if ( $order->is_paid() ) {
			return;
		}

		// Show the button only for pending orders.
		if ( 'pending' !== $order->get_status() ) {
			return;
		}

		// Always use the standard WooCommerce payment link.
		$link          = $order->get_checkout_payment_url();
		$button_text   = __( 'Copy Payment Link', 'alynt-wc-customer-order-manager' );
		$section_title = __( 'Payment Link', 'alynt-wc-customer-order-manager' );
		?>
		<div class="payment-link-actions">
			<h3 class="wc-order-data-row-toggle">
				<?php echo esc_html( $section_title ); ?>
			</h3>
			<div class="wc-order-data-row">
				<button type="button" class="button button-primary awcom-copy-payment-link" data-payment-link="<?php echo esc_attr( $link ); ?>">
					<span class="dashicons dashicons-clipboard"></span>
					<?php echo esc_html( $button_text ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
