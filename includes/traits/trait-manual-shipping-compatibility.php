<?php
/**
 * Compatibility helpers for manual-order shipping rate calculations.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.1.2
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Adds package-level support for shipping methods that usually inspect cart state.
 *
 * @since 1.1.2
 */
trait ManualShippingCompatibilityTrait {

	/**
	 * Add the customer ID in both WooCommerce package forms used by extensions.
	 *
	 * @since  1.1.2
	 * @param  array $package     Shipping package.
	 * @param  int   $customer_id Customer user ID.
	 * @return array
	 */
	private function prepare_manual_shipping_package_customer( array $package, $customer_id ) {
		$customer_id = absint( $customer_id );

		$package['customer_id'] = $customer_id;
		$package['user']        = array( 'ID' => $customer_id );

		return $package;
	}

	/**
	 * Retrieve rates, with a guarded fallback for free-shipping minimums.
	 *
	 * WooCommerce's core free-shipping method checks WC()->cart for its minimum
	 * amount. Manual order creation builds package arrays directly, so there may
	 * be no cart subtotal even when the package/order total qualifies.
	 *
	 * @since  1.1.2
	 * @param  object $method  Shipping method instance.
	 * @param  array  $package Shipping package.
	 * @return array
	 */
	private function get_manual_package_shipping_rates( $method, array $package ) {
		$rates = $method->get_rates_for_package( $package );

		if ( ! empty( $rates ) || ! $this->should_apply_manual_free_shipping_fallback( $method, $package ) ) {
			return $rates;
		}

		$method->rates = array();
		$method->calculate_shipping( $package );

		return $method->rates;
	}

	/**
	 * Determine whether a free-shipping rate should be calculated from package data.
	 *
	 * @since  1.1.2
	 * @param  object $method  Shipping method instance.
	 * @param  array  $package Shipping package.
	 * @return bool
	 */
	private function should_apply_manual_free_shipping_fallback( $method, array $package ) {
		if ( ! is_object( $method ) || ! isset( $method->id ) || 'free_shipping' !== $method->id ) {
			return false;
		}

		if ( ! method_exists( $method, 'calculate_shipping' ) ) {
			return false;
		}

		$requires = $this->get_shipping_method_setting( $method, 'requires', '' );
		if ( ! in_array( $requires, array( 'min_amount', 'either' ), true ) ) {
			return false;
		}

		$min_amount = (float) $this->get_shipping_method_setting( $method, 'min_amount', 0 );
		if ( $min_amount <= 0 ) {
			return false;
		}

		$package_total = $this->get_manual_free_shipping_package_total( $method, $package );
		if ( $package_total < $min_amount ) {
			return false;
		}

		return (bool) apply_filters( 'woocommerce_shipping_free_shipping_is_available', true, $package, $method );
	}

	/**
	 * Get a shipping method setting from public properties or instance options.
	 *
	 * @since  1.1.2
	 * @param  object $method  Shipping method instance.
	 * @param  string $key     Setting key.
	 * @param  mixed  $default Default value.
	 * @return mixed
	 */
	private function get_shipping_method_setting( $method, $key, $default = '' ) {
		if ( isset( $method->{$key} ) ) {
			return $method->{$key};
		}

		if ( method_exists( $method, 'get_option' ) ) {
			return $method->get_option( $key, $default );
		}

		return $default;
	}

	/**
	 * Resolve the package total used for manual free-shipping minimum checks.
	 *
	 * @since  1.1.2
	 * @param  object $method  Shipping method instance.
	 * @param  array  $package Shipping package.
	 * @return float
	 */
	private function get_manual_free_shipping_package_total( $method, array $package ) {
		$ignore_discounts = $this->get_shipping_method_setting( $method, 'ignore_discounts', 'no' );

		if ( 'yes' === $ignore_discounts && ! empty( $package['contents'] ) ) {
			$total = 0;
			foreach ( $package['contents'] as $item ) {
				$total += isset( $item['line_subtotal'] ) ? (float) $item['line_subtotal'] : 0;
			}

			return $total;
		}

		return isset( $package['contents_cost'] ) ? (float) $package['contents_cost'] : 0;
	}
}
