<?php
/**
 * Checkout hooks for preserving custom pricing on order creation.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures custom pricing is applied to orders created via the checkout flow.
 *
 * @since 1.0.0
 */
trait OrderHandlerCheckoutTrait {

	/**
	 * Write custom price data from the cart session to the new order item.
	 *
	 * Hooked to woocommerce_checkout_create_order_line_item. Sets item
	 * subtotal and total from the awcom_custom_price cart item value.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order_Item_Product $item          The order item being created.
	 * @param string                 $cart_item_key The cart item key.
	 * @param array                  $values        Cart item session data.
	 * @param \WC_Order              $_order        The order being created.
	 * @return void
	 */
	public function set_order_item_custom_price( $item, $cart_item_key, $values, $_order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WooCommerce action signature includes an unused order object.
		$this->log( 'Checkout: set_order_item_custom_price called for cart item key: ' . $cart_item_key );

		if ( ! is_user_logged_in() ) {
			$this->log( 'Checkout: User not logged in, skipping' );
			return;
		}

		if ( isset( $values['awcom_custom_price'] ) ) {
			$custom_price = $values['awcom_custom_price'];
			$quantity     = $item->get_quantity();

			$this->log(
				sprintf(
					'Checkout: Found custom price %s for product #%d (quantity: %d)',
					$custom_price,
					$item->get_product_id(),
					$quantity
				)
			);

			$item->set_subtotal( $custom_price * $quantity );
			$item->set_total( $custom_price * $quantity );

			if ( isset( $values['awcom_group_name'] ) ) {
				$item->add_meta_data( '_awcom_customer_group', $values['awcom_group_name'], true );
			}

			$this->log(
				sprintf(
					'Checkout: Set order item subtotal=%s, total=%s for product #%d',
					$custom_price * $quantity,
					$custom_price * $quantity,
					$item->get_product_id()
				)
			);
		} else {
			$this->log( 'Checkout: No custom price found in cart item values' );
		}
	}

	/**
	 * Trigger a full order total recalculation after checkout order creation.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The newly created order.
	 * @param array     $_data Checkout POST data.
	 * @return void
	 */
	public function recalculate_order_totals( $order, $_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WooCommerce action signature includes unused checkout data.
		$this->log( 'Checkout: Recalculating order totals for order #' . $order->get_id() );
		$order->calculate_totals();
		$this->log( 'Checkout: Order total after recalculation: ' . $order->get_total() );
	}

	/**
	 * Re-apply customer group pricing to all items on a newly created order.
	 *
	 * Reads the current customer's group, looks up rules for each item, adjusts
	 * subtotals and totals in place, then recalculates and saves the order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return void
	 */
	public function fix_order_pricing_on_creation( $order_id ) {
		$this->log( 'Order Creation: Fixing pricing for order #' . $order_id );

		if ( ! is_user_logged_in() ) {
			$this->log( 'Order Creation: User not logged in, skipping' );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->log( 'Order Creation: Could not get order object' );
			return;
		}

		$customer_id = get_current_user_id();

		$group_id = PricingRuleLookup::get_customer_group_id( $customer_id, true );
		if ( $group_id ) {
			$this->log( 'Order Creation: Customer is in group #' . $group_id );
		} else {
			$this->log( 'Order Creation: No customer group found, attempting to restore stored custom pricing only' );
		}

		$product_ids = array();
		foreach ( $order->get_items() as $order_item ) {
			$order_item_product_id = $order_item->get_variation_id() ? $order_item->get_variation_id() : $order_item->get_product_id();
			if ( $order_item_product_id > 0 ) {
				$product_ids[] = $order_item_product_id;
			}
		}
		$product_category_map = array();
		$rule_lookup          = array(
			'product_rules'        => array(),
			'category_rules'       => array(),
			'product_category_map' => array(),
			'resolved_rules'       => array(),
		);

		if ( $group_id && ! empty( $product_ids ) ) {
			$product_category_map = PricingRuleLookup::get_product_category_map( $product_ids );
			$rule_lookup          = PricingRuleLookup::get_rule_lookup( $group_id, $product_ids, $product_category_map, true, true );
		}

		$modified = false;

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			$quantity   = max( 1, (int) $item->get_quantity() );
			$product    = $item->get_product();

			if ( ! $product || $product_id <= 0 ) {
				continue;
			}

			$stored_custom_price          = $item->get_meta( self::ITEM_META_CUSTOM_PRICE, true );
			$stored_custom_subtotal_price = $item->get_meta( self::ITEM_META_CUSTOM_SUBTOTAL_PRICE, true );

			if ( '' !== $stored_custom_price && '' !== $stored_custom_subtotal_price && is_numeric( $stored_custom_price ) && is_numeric( $stored_custom_subtotal_price ) ) {
				$stored_custom_price          = max( 0, (float) $stored_custom_price );
				$stored_custom_subtotal_price = max( 0, (float) $stored_custom_subtotal_price );
				$expected_subtotal            = $stored_custom_subtotal_price * $quantity;
				$expected_total               = $stored_custom_price * $quantity;
				$current_subtotal             = (float) $item->get_subtotal();
				$current_total                = (float) $item->get_total();

				if ( abs( $current_subtotal - $expected_subtotal ) > 0.0001 || abs( $current_total - $expected_total ) > 0.0001 ) {
					$item->set_subtotal( $expected_subtotal );
					$item->set_total( $expected_total );
					$item->save();
					$modified = true;

					$this->log(
						sprintf(
							'Order Creation: Restored stored custom pricing for item #%d - Product #%d subtotal=%s total=%s',
							$item_id,
							$product_id,
							wc_price( $expected_subtotal ),
							wc_price( $expected_total )
						)
					);
				}

				continue;
			}

			if ( ! $group_id ) {
				continue;
			}

			$original_price = PricingRuleLookup::get_product_base_price( $product );
			if ( $original_price <= 0 ) {
				continue;
			}

			$matching_rule = PricingRuleLookup::get_matching_rule( $product_id, $rule_lookup );
			if ( ! $matching_rule ) {
				continue;
			}

			$adjusted_price = PricingRuleLookup::get_adjusted_price( $original_price, $matching_rule );
			if ( $adjusted_price >= $original_price ) {
				continue;
			}

			$group_name = isset( $matching_rule->group_name ) ? (string) $matching_rule->group_name : '';

			$item->set_subtotal( $original_price * $quantity );
			$item->set_total( $adjusted_price * $quantity );
			$item->update_meta_data( self::ITEM_META_CUSTOM_PRICE, $adjusted_price );
			$item->update_meta_data( self::ITEM_META_CUSTOM_SUBTOTAL_PRICE, $original_price );

			if ( $group_name ) {
				$item->update_meta_data( '_awcom_customer_group', $group_name );
			}

			$item->save();
			$modified = true;

			$this->log(
				sprintf(
					'Order Creation: Updated item #%d - Product #%d from %s to %s (total: %s)',
					$item_id,
					$product_id,
					wc_price( $original_price ),
					wc_price( $adjusted_price ),
					wc_price( $adjusted_price * $quantity )
				)
			);
		}

		if ( $modified ) {
			$order->calculate_totals( false );
			$order->update_meta_data( self::ORDER_META_LOCKED_TOTAL, $order->get_total() );
			$order->save();
			$this->log( 'Order Creation: Order totals recalculated. New total: ' . $order->get_total() );
		} else {
			$this->log( 'Order Creation: No pricing changes needed' );
		}
	}

	/**
	 * Filter hook stub — passes the order total through unchanged.
	 *
	 * Reserved for future payment-gateway-specific total corrections.
	 *
	 * @since 1.0.0
	 *
	 * @param float     $total The order total.
	 * @param \WC_Order $_order The order object.
	 * @return float The unchanged order total.
	 */
	public function filter_order_total_for_payment( $total, $_order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Payment filter signature includes an unused order object.
		return $total;
	}

	/**
	 * Ensure custom pricing is applied before a customer submits payment.
	 *
	 * Delegates to fix_order_pricing_on_creation so that prices are correct
	 * even if the cart session has been cleared since the order was created.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return void
	 */
	public function fix_order_before_payment( $order_id ) {
		$this->log( 'Before Payment: Fixing order #' . $order_id );
		$this->fix_order_pricing_on_creation( $order_id );
	}
}
