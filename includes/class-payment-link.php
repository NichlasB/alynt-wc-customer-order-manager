<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * Payment-link actions for WooCommerce order edit screens.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.0.2
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Adds payment-link actions on the order edit screen.
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
		wp_enqueue_style( 'awcom-payment-link', plugins_url( '../assets/css/payment-link.css', __FILE__ ), array(), AWCOM_VERSION );
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
	 * Render the payment-link actions in the order actions sidebar.
	 *
	 * Only displayed for pending, unpaid orders. The copy button uses the
	 * Clipboard API with a textarea fallback for older browsers.
	 *
	 * @since 1.0.2
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return void
	 */
	public function add_payment_link_copy_button( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $this->is_order_eligible_for_payment_actions( $order ) ) {
			return;
		}

		$link                      = $order->get_checkout_payment_url();
		$button_text               = __( 'Copy Payment Link', 'alynt-wc-customer-order-manager' );
		$section_title             = __( 'Payment Link', 'alynt-wc-customer-order-manager' );
		$switch_button_text        = __( 'Switch to Customer & Pay', 'alynt-wc-customer-order-manager' );
		$workflow_guidance         = __( 'Some payment gateways may require this payment link to be completed while signed in as the customer rather than while signed in as staff. If payment does not go through, switch into the customer account or send the link to the customer to complete themselves.', 'alynt-wc-customer-order-manager' );
		$switch_unavailable_reason = $this->get_switch_unavailable_reason( $order );
		$switch_url                = '';

		if ( '' === $switch_unavailable_reason ) {
			$switch_url = CustomerPaymentSwitch::get_switch_action_url( $order->get_id() );
		}
		?>
		<div class="payment-link-actions">
			<h3 class="wc-order-data-row-toggle">
				<?php echo esc_html( $section_title ); ?>
			</h3>
			<div class="wc-order-data-row">
				<?php $switch_error = $this->get_switch_error_message(); ?>
				<?php if ( '' !== $switch_error ) : ?>
					<div class="notice notice-error inline awcom-payment-link-inline-notice">
						<p><?php echo esc_html( $switch_error ); ?></p>
					</div>
				<?php endif; ?>

				<button type="button" class="button button-secondary awcom-payment-action awcom-copy-payment-link" data-payment-link="<?php echo esc_attr( $link ); ?>">
					<span class="dashicons dashicons-clipboard"></span>
					<?php echo esc_html( $button_text ); ?>
				</button>
				<?php if ( '' !== $switch_url ) : ?>
					<a href="<?php echo esc_url( $switch_url ); ?>" class="button button-secondary awcom-payment-action awcom-switch-to-customer">
						<span class="dashicons dashicons-randomize"></span>
						<?php echo esc_html( $switch_button_text ); ?>
					</a>
				<?php endif; ?>
				<div class="awcom-payment-link-feedback" hidden></div>
				<p class="description"><?php echo esc_html( $workflow_guidance ); ?></p>
				<?php if ( '' !== $switch_unavailable_reason ) : ?>
					<p class="description"><?php echo esc_html( $switch_unavailable_reason ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Check whether the payment actions should render for an order.
	 *
	 * @param mixed $order WooCommerce order object.
	 * @return bool
	 */
	private function is_order_eligible_for_payment_actions( $order ) {
		return $order instanceof \WC_Order && ! $order->is_paid() && 'pending' === $order->get_status();
	}

	/**
	 * Read any switch error message passed back to the order screen.
	 *
	 * @return string
	 */
	private function get_switch_error_message() {
		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading redirect notice query args only. */
		$error_message = isset( $_GET[ CustomerPaymentSwitch::REQUEST_ERROR ] )
			? sanitize_text_field( rawurldecode( wp_unslash( $_GET[ CustomerPaymentSwitch::REQUEST_ERROR ] ) ) )
			: '';
		/* phpcs:enable */

		return $error_message;
	}

	/**
	 * Explain why the switch action is unavailable for this order.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return string
	 */
	private function get_switch_unavailable_reason( \WC_Order $order ) {
		if ( ! CustomerPaymentSwitch::is_user_switching_available() ) {
			return __( 'Install and activate the User Switching plugin to enable the one-click customer payment switch.', 'alynt-wc-customer-order-manager' );
		}

		$customer_id = absint( $order->get_customer_id() );
		if ( $customer_id <= 0 ) {
			return __( 'This order is not attached to a valid WordPress customer account, so customer switching is unavailable.', 'alynt-wc-customer-order-manager' );
		}

		$customer = get_user_by( 'id', $customer_id );
		if ( ! $customer instanceof \WP_User ) {
			return __( 'This order is not attached to a valid WordPress customer account, so customer switching is unavailable.', 'alynt-wc-customer-order-manager' );
		}

		if ( $customer_id === get_current_user_id() ) {
			return __( 'This order is already attached to your current WordPress account, so User Switching cannot switch into the same user.', 'alynt-wc-customer-order-manager' );
		}

		if ( ! \user_switching::maybe_switch_url( $customer ) ) {
			return __( 'Your current account cannot switch into this customer through the User Switching plugin.', 'alynt-wc-customer-order-manager' );
		}

		return '';
	}
}
