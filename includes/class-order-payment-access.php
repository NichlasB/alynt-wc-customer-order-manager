<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * Payment form access control for administrators and shop managers.
 *
 * Grants the pay_for_order capability to administrator and shop_manager roles
 * so staff can process payments on behalf of customers, and disables the
 * WooCommerce email verification gate on the pay page for those roles.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.0.4
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Manages payment capability grants and email verification bypass.
 *
 * @since 1.0.4
 */
class OrderPaymentAccess {

	/**
	 * Register hooks that control pay-for-order access.
	 *
	 * @since 1.0.4
	 */
	public function __construct() {
		add_filter( 'woocommerce_order_email_verification_required', array( $this, 'maybe_bypass_email_verification' ), 9999, 2 );
	}

	/**
	 * Grant the pay_for_order capability to administrator and shop_manager roles.
	 *
	 * @since 1.0.4
	 *
	 * @return void
	 */
	public static function grant_payment_capabilities() {
		// Add the capability to the administrator role.
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'pay_for_order' );
		}

		// Add the capability to the shop manager role.
		$shop_manager = get_role( 'shop_manager' );
		if ( $shop_manager ) {
			$shop_manager->add_cap( 'pay_for_order' );
		}
	}

	/**
	 * Allow trusted staff to bypass the pay-for-order email verification gate.
	 *
	 * @since 1.0.6
	 *
	 * @param bool      $required Whether verification is required.
	 * @param \WC_Order $order    The order being paid for.
	 * @return bool
	 */
	public function maybe_bypass_email_verification( $required, $order ) {
		if ( ! $order instanceof \WC_Order || ! Security::user_can_access() ) {
			return $required;
		}

		return false;
	}
}
