<?php
/**
 * Cart display filters for showing custom prices and totals.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Overrides cart price and subtotal HTML to show customer group pricing.
 *
 * @since 1.0.0
 */
trait OrderHandlerCartDisplayTrait {

	/**
	 * Return the custom per-unit price HTML for a cart item.
	 *
	 * @since 1.0.0
	 *
	 * @param string $price_html   Original formatted price HTML.
	 * @param array  $cart_item    Cart item data array.
	 * @param string $_cart_item_key Unique cart item key.
	 * @return string Formatted price HTML.
	 */
	public function display_custom_cart_price( $price_html, $cart_item, $_cart_item_key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WooCommerce filter signature includes an unused cart item key.
		if ( ! is_user_logged_in() ) {
			return $price_html;
		}

		if ( isset( $cart_item['awcom_custom_price'] ) ) {
			$this->log( 'Cart display: Using stored custom price ' . $cart_item['awcom_custom_price'] );
			return wc_price( $cart_item['awcom_custom_price'] );
		}

		$product          = $cart_item['data'];
		$discounted_price = $product->get_price();
		$this->log( 'Cart display: Showing price ' . $discounted_price . ' for product #' . $product->get_id() );

		return wc_price( $discounted_price );
	}

	/**
	 * Return the custom line-item subtotal HTML for a cart item.
	 *
	 * @since 1.0.0
	 *
	 * @param string $subtotal_html  Original formatted subtotal HTML.
	 * @param array  $cart_item      Cart item data array.
	 * @param string $_cart_item_key Unique cart item key.
	 * @return string Formatted subtotal HTML.
	 */
	public function display_custom_cart_subtotal( $subtotal_html, $cart_item, $_cart_item_key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WooCommerce filter signature includes an unused cart item key.
		if ( ! is_user_logged_in() ) {
			return $subtotal_html;
		}

		if ( isset( $cart_item['awcom_custom_price'] ) ) {
			$quantity = $cart_item['quantity'];
			$subtotal = $cart_item['awcom_custom_price'] * $quantity;
			$this->log( 'Cart display: Using stored custom price for subtotal ' . $subtotal );
			return wc_price( $subtotal );
		}

		$product          = $cart_item['data'];
		$discounted_price = $product->get_price();
		$quantity         = $cart_item['quantity'];
		$subtotal         = $discounted_price * $quantity;

		$this->log( 'Cart display: Showing subtotal ' . $subtotal . ' for product #' . $product->get_id() );

		return wc_price( $subtotal );
	}

	/**
	 * Recalculate and return the correct cart subtotal HTML.
	 *
	 * Sums custom prices for items that have them, otherwise uses the current
	 * product price, and returns the formatted total.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $cart_subtotal Existing subtotal HTML.
	 * @param bool     $_compound     Whether to include compound taxes.
	 * @param \WC_Cart $cart          The cart object.
	 * @return string Formatted subtotal HTML.
	 */
	public function fix_cart_subtotal( $cart_subtotal, $_compound, $cart ) {
		if ( ! is_user_logged_in() ) {
			return $cart_subtotal;
		}

		$subtotal = 0;
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['awcom_custom_price'] ) ) {
				$subtotal += $cart_item['awcom_custom_price'] * $cart_item['quantity'];
			} else {
				$subtotal += $cart_item['data']->get_price() * $cart_item['quantity'];
			}
		}

		$this->log( 'Cart totals: Calculated subtotal = ' . $subtotal );
		return wc_price( $subtotal );
	}

	/**
	 * Recalculate and return the correct cart total HTML including shipping and fees.
	 *
	 * @since 1.0.0
	 *
	 * @param string $total The existing total HTML.
	 * @return string Formatted total HTML.
	 */
	public function fix_cart_total( $total ) {
		if ( ! is_user_logged_in() ) {
			return $total;
		}

		$cart = WC()->cart;
		if ( ! $cart ) {
			return $total;
		}

		$cart_total = 0;
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['awcom_custom_price'] ) ) {
				$cart_total += $cart_item['awcom_custom_price'] * $cart_item['quantity'];
			} else {
				$cart_total += $cart_item['data']->get_price() * $cart_item['quantity'];
			}
		}

		if ( $cart->needs_shipping() && $cart->show_shipping() ) {
			$cart_total += $cart->get_shipping_total();
		}

		foreach ( $cart->get_fees() as $fee ) {
			$cart_total += $fee->total;
		}

		$cart_total += $cart->get_total_tax();

		$this->log( 'Cart totals: Calculated total = ' . $cart_total );
		return wc_price( $cart_total );
	}

	/**
	 * Append a customer group pricing label beneath the product name in the cart.
	 *
	 * Queries the customer's group name from the database and appends a styled
	 * "X Pricing Applied" label to the product name HTML string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $product_name  The product name HTML.
	 * @param array  $_cart_item     Cart item data array.
	 * @param string $_cart_item_key Unique cart item key.
	 * @return string Modified product name HTML.
	 */
	public function add_discount_info_to_cart_item( $product_name, $_cart_item, $_cart_item_key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WooCommerce filter signature includes unused cart context.
		$display_title = PricingRuleLookup::get_customer_group_display_title( get_current_user_id() );

		if ( $display_title ) {
			$discount_text = sprintf(
				'<br><small style="color: #3b5249; font-weight: 500;">%s Pricing Applied</small>',
				esc_html( $display_title )
			);
			return $product_name . $discount_text;
		}

		return $product_name;
	}
}
