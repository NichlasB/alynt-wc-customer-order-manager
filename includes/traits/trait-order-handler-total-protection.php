<?php
/**
 * Order total protection hooks for OrderHandler.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Prevents WooCommerce from overwriting custom-priced order totals.
 *
 * @since 1.0.0
 */
trait OrderHandlerTotalProtectionTrait {

	/**
	 * Cancel total recalculation for orders with custom pricing.
	 *
	 * Hooked to woocommerce_order_before_calculate_totals. Returns false
	 * (and removes itself) to abort the recalculation for orders created
	 * by this plugin or flagged with _awcom_has_custom_pricing.
	 *
	 * @since 1.0.0
	 *
	 * @param bool      $and_taxes Whether taxes should be recalculated.
	 * @param \WC_Order $order     The order being recalculated.
	 * @return void
	 */
	public function prevent_total_recalculation( $and_taxes, $order ) {
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		if ( $order->get_created_via() === self::ORDER_CREATED_VIA ) {
			$this->log( 'Preventing total recalculation for order #' . $order->get_id() . ' to preserve customer group pricing' );
			remove_action( 'woocommerce_order_before_calculate_totals', array( $this, 'prevent_total_recalculation' ), 10 );
			return false;
		}

		if ( $order->get_meta( self::ORDER_META_HAS_CUSTOM_PRICING ) === 'yes' ) {
			$this->log( 'Preventing total recalculation for order #' . $order->get_id() . ' - has custom pricing flag' );
			remove_action( 'woocommerce_order_before_calculate_totals', array( $this, 'prevent_total_recalculation' ), 10 );
			return false;
		}
	}

	/**
	 * Re-apply custom item prices after WooCommerce recalculates order totals.
	 *
	 * Iterates order items and restores _awcom_custom_price / _awcom_custom_subtotal_price
	 * meta values, then triggers a clean recalculation without looping.
	 *
	 * @since 1.0.0
	 *
	 * @param bool      $and_taxes Whether taxes were recalculated.
	 * @param \WC_Order $order     The order that was recalculated.
	 * @return void
	 */
	public function restore_custom_prices_after_recalculation( $and_taxes, $order ) {
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			return;
		}

		if ( $order->get_created_via() !== self::ORDER_CREATED_VIA && $order->get_meta( self::ORDER_META_HAS_CUSTOM_PRICING ) !== 'yes' ) {
			return;
		}

		$this->log( 'Restoring custom prices after recalculation for order #' . $order->get_id() );
		$needs_save = false;

		foreach ( $order->get_items() as $item_id => $item ) {
			$custom_price          = $item->get_meta( self::ITEM_META_CUSTOM_PRICE, true );
			$custom_subtotal_price = $item->get_meta( self::ITEM_META_CUSTOM_SUBTOTAL_PRICE, true );

			if ( $custom_price && $custom_subtotal_price ) {
				$quantity = $item->get_quantity();
				$item->set_subtotal( $custom_subtotal_price * $quantity );
				$item->set_total( $custom_price * $quantity );
				$item->save();
				$needs_save = true;

				$this->log(
					sprintf(
						'Restored custom price for item #%d: subtotal=%s, total=%s',
						$item_id,
						$custom_subtotal_price * $quantity,
						$custom_price * $quantity
					)
				);
			}
		}

		if ( $needs_save ) {
			remove_action( 'woocommerce_order_after_calculate_totals', array( $this, 'restore_custom_prices_after_recalculation' ), 10 );
			$order->calculate_totals( false );
			add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'restore_custom_prices_after_recalculation' ), 10, 2 );
			$this->log( 'Order #' . $order->get_id() . ' total after restoring custom prices: ' . $order->get_total() );
		}
	}

	/**
	 * Pass through item subtotal unchanged for orders with custom pricing.
	 *
	 * @since 1.0.0
	 *
	 * @param float                  $subtotal The current item subtotal.
	 * @param \WC_Order_Item_Product $item     The order item.
	 * @return float The unchanged subtotal.
	 */
	public function preserve_item_subtotal( $subtotal, $item ) {
		$order = $item->get_order();
		if ( ! $order || is_a( $order, 'WC_Order_Refund' ) ) {
			return $subtotal;
		}

		if ( $order->get_created_via() === self::ORDER_CREATED_VIA || $order->get_meta( self::ORDER_META_HAS_CUSTOM_PRICING ) === 'yes' ) {
			return $subtotal;
		}

		return $subtotal;
	}

	/**
	 * Pass through item total unchanged for orders with custom pricing.
	 *
	 * @since 1.0.0
	 *
	 * @param float                  $total The current item total.
	 * @param \WC_Order_Item_Product $item  The order item.
	 * @return float The unchanged total.
	 */
	public function preserve_item_total( $total, $item ) {
		$order = $item->get_order();
		if ( ! $order || is_a( $order, 'WC_Order_Refund' ) ) {
			return $total;
		}

		if ( $order->get_created_via() === self::ORDER_CREATED_VIA || $order->get_meta( self::ORDER_META_HAS_CUSTOM_PRICING ) === 'yes' ) {
			return $total;
		}

		return $total;
	}

	/**
	 * Return the locked order total for orders created by this plugin.
	 *
	 * If _awcom_locked_total meta is set it is returned directly. Otherwise the
	 * total is calculated from order items, stored in meta, and returned.
	 * Skips locking in the admin context to avoid interfering with edits.
	 *
	 * @since 1.0.0
	 *
	 * @param float     $total The total passed by WooCommerce.
	 * @param \WC_Order $order The order being evaluated.
	 * @return float The locked or calculated total.
	 */
	public function lock_order_total( $total, $order ) {
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			return $total;
		}

		if ( is_admin() ) {
			return $total;
		}

		if ( $order->get_created_via() === self::ORDER_CREATED_VIA || $order->get_meta( self::ORDER_META_HAS_CUSTOM_PRICING ) === 'yes' ) {
			$locked_total = $order->get_meta( self::ORDER_META_LOCKED_TOTAL );

			if ( $locked_total ) {
				$this->log( 'Order Total Lock: Returning locked total ' . $locked_total . ' for order #' . $order->get_id() . ' (passed in: ' . $total . ')' );
				return $locked_total;
			}

			$this->log( 'Order Total Lock: No locked total metadata, calculating from order items for #' . $order->get_id() );

			$calculated_total = 0;
			foreach ( $order->get_items() as $item ) {
				$calculated_total += $item->get_total();
			}

			$calculated_total += $order->get_shipping_total();

			foreach ( $order->get_fees() as $fee ) {
				$calculated_total += $fee->get_total();
			}

			$calculated_total += $order->get_total_tax();

			$this->log( 'Order Total Lock: Calculated total from items: ' . $calculated_total . ' (passed in total was: ' . $total . ')' );

			$order->update_meta_data( self::ORDER_META_LOCKED_TOTAL, $calculated_total );
			$order->save_meta_data();

			return $calculated_total;
		}

		return $total;
	}
}
