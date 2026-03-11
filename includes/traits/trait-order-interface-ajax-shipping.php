<?php
/**
 * Shipping methods AJAX endpoint for OrderInterface.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the AJAX shipping methods request for the Create Order interface.
 *
 * @since 1.0.0
 */
trait OrderInterfaceAjaxShippingTrait {

	/**
	 * Handle the AJAX request to retrieve available shipping methods.
	 *
	 * Temporarily populates the WooCommerce cart with the submitted items,
	 * sets the customer's shipping address, and queries enabled shipping
	 * methods for the resulting package. Returns rates as JSON.
	 *
	 * @since 1.0.0
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function ajax_get_shipping_methods() {
		if ( false === check_ajax_referer( 'awcom-order-interface', 'nonce', false ) ) {
			$this->send_order_interface_error(
				__( 'Your session expired. Refresh the page and try again.', 'alynt-wc-customer-order-manager' ),
				403,
				true
			);
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->send_order_interface_error(
				__( 'You do not have permission to calculate shipping.', 'alynt-wc-customer-order-manager' ),
				403
			);
		}

		$customer_id = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
		if ( ! $customer_id ) {
			$this->send_order_interface_error(
				__( 'Choose a valid customer before calculating shipping.', 'alynt-wc-customer-order-manager' ),
				400
			);
		}

		if ( ! get_user_by( 'id', $customer_id ) ) {
			$this->send_order_interface_error(
				__( 'Choose a valid customer before calculating shipping.', 'alynt-wc-customer-order-manager' ),
				400
			);
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->customer || ! WC()->shipping ) {
			$this->send_order_interface_error(
				__( 'Shipping is not available right now. Please refresh the page and try again.', 'alynt-wc-customer-order-manager' ),
				500,
				true
			);
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested cart items are sanitized per-entry below.
		$raw_items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$items     = array();
		if ( is_array( $raw_items ) ) {
			foreach ( $raw_items as $raw_item ) {
				if ( ! is_array( $raw_item ) ) {
					continue;
				}

				$items[] = array(
					'product_id' => isset( $raw_item['product_id'] ) ? absint( $raw_item['product_id'] ) : 0,
					'quantity'   => isset( $raw_item['quantity'] ) ? absint( $raw_item['quantity'] ) : 0,
				);
			}
		}
		if ( empty( $items ) ) {
			$this->send_order_interface_error(
				__( 'Add at least one item before calculating shipping.', 'alynt-wc-customer-order-manager' ),
				400
			);
		}

		$shipping_address = array(
			'country'   => get_user_meta( $customer_id, 'shipping_country', true ),
			'state'     => get_user_meta( $customer_id, 'shipping_state', true ),
			'postcode'  => get_user_meta( $customer_id, 'shipping_postcode', true ),
			'city'      => get_user_meta( $customer_id, 'shipping_city', true ),
			'address_1' => get_user_meta( $customer_id, 'shipping_address_1', true ),
			'address_2' => get_user_meta( $customer_id, 'shipping_address_2', true ),
		);

		$billing_address = array(
			'country'   => get_user_meta( $customer_id, 'billing_country', true ),
			'state'     => get_user_meta( $customer_id, 'billing_state', true ),
			'postcode'  => get_user_meta( $customer_id, 'billing_postcode', true ),
			'city'      => get_user_meta( $customer_id, 'billing_city', true ),
			'address_1' => get_user_meta( $customer_id, 'billing_address_1', true ),
			'address_2' => get_user_meta( $customer_id, 'billing_address_2', true ),
		);

		$has_shipping_address = ! empty( $shipping_address['address_1'] ) &&
			! empty( $shipping_address['city'] ) &&
			! empty( $shipping_address['country'] );

		$address              = $has_shipping_address ? $shipping_address : $billing_address;
		$has_complete_address = ! empty( $address['address_1'] ) &&
			! empty( $address['city'] ) &&
			! empty( $address['country'] );

		if ( ! $has_complete_address ) {
			$this->send_order_interface_error(
				__( 'Add a billing or shipping address for this customer before calculating shipping.', 'alynt-wc-customer-order-manager' ),
				400
			);
		}

		$cache_items = array();
		foreach ( $items as $item ) {
			$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			$quantity   = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;

			if ( $product_id <= 0 || $quantity < 1 ) {
				continue;
			}

			$cache_items[] = array(
				'product_id' => $product_id,
				'quantity'   => $quantity,
			);
		}

		usort(
			$cache_items,
			static function ( $left, $right ) {
				if ( $left['product_id'] === $right['product_id'] ) {
					return $left['quantity'] <=> $right['quantity'];
				}

				return $left['product_id'] <=> $right['product_id'];
			}
		);

		$shipping_cache_key = 'awcom_ship_' . md5(
			wp_json_encode(
				array(
					'customer_id' => $customer_id,
					'address'     => $address,
					'items'       => $cache_items,
				)
			)
		);
		$cached_shipping_methods = get_transient( $shipping_cache_key );
		if ( is_array( $cached_shipping_methods ) && ! empty( $cached_shipping_methods ) ) {
			wp_send_json_success( array( 'methods' => $cached_shipping_methods ) );
		}

		$shipping_methods  = array();
		$had_method_errors = false;
		$cart              = WC()->cart;
		$customer          = WC()->customer;
		$shipping          = WC()->shipping;

		$cart->empty_cart();

		try {
			$added_items = 0;
			foreach ( $items as $item ) {
				$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
				$quantity   = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;

				if ( $product_id <= 0 || $quantity < 1 ) {
					continue;
				}

				$added = $cart->add_to_cart( $product_id, $quantity );
				if ( $added ) {
					++$added_items;
					continue;
				}

				$had_method_errors = true;
				$this->log_order_interface_error(
					sprintf(
						'Shipping calculation skipped invalid cart item product #%1$d with quantity %2$d.',
						$product_id,
						$quantity
					)
				);
			}

			if ( 0 === $added_items ) {
				$cart->empty_cart();
				$this->send_order_interface_error(
					__( 'Add at least one valid item before calculating shipping.', 'alynt-wc-customer-order-manager' ),
					400
				);
			}

			$customer->set_billing_location(
				$address['country'],
				$address['state'],
				$address['postcode'],
				$address['city']
			);
			$customer->set_shipping_location(
				$address['country'],
				$address['state'],
				$address['postcode'],
				$address['city']
			);
			$customer->set_shipping_address_1( $address['address_1'] );
			$customer->set_shipping_address_2( $address['address_2'] );

			$packages = array(
				array(
					'contents'        => $cart->get_cart(),
					'contents_cost'   => $cart->get_cart_contents_total(),
					'applied_coupons' => $cart->get_applied_coupons(),
					'destination'     => array(
						'country'   => $address['country'],
						'state'     => $address['state'],
						'postcode'  => $address['postcode'],
						'city'      => $address['city'],
						'address'   => $address['address_1'],
						'address_2' => $address['address_2'],
					),
				),
			);

			$shipping->load_shipping_methods( $packages[0] );
			$enabled_methods = $shipping->get_shipping_methods();

			foreach ( $enabled_methods as $method ) {
				if ( ! $method->is_enabled() ) {
					continue;
				}

				try {
					$rates = $method->get_rates_for_package( $packages[0] );
				} catch ( \Throwable $throwable ) {
					$had_method_errors = true;
					$this->log_order_interface_error(
						sprintf(
							'Shipping method %1$s failed during admin calculation: %2$s',
							$method->id,
							$throwable->getMessage()
						)
					);
					continue;
				}

				if ( ! $rates ) {
					continue;
				}

				foreach ( $rates as $rate_id => $rate_data ) {
					$shipping_methods[] = array(
						'id'             => $rate_id,
						'method_id'      => $method->id,
						'label'          => $rate_data->label,
						'cost'           => $rate_data->cost,
						'formatted_cost' => wc_price( $rate_data->cost ),
						'taxes'          => $rate_data->taxes,
					);
				}
			}
		} catch ( \Throwable $throwable ) {
			$cart->empty_cart();
			$this->log_order_interface_error( 'Shipping calculation failed: ' . $throwable->getMessage() );
			$this->send_order_interface_error(
				__( 'We could not calculate shipping methods right now. Please try again.', 'alynt-wc-customer-order-manager' ),
				500,
				true
			);
		}

		$cart->empty_cart();

		if ( empty( $shipping_methods ) ) {
			if ( $had_method_errors ) {
				$this->send_order_interface_error(
					__( 'We could not calculate shipping methods right now. Please try again.', 'alynt-wc-customer-order-manager' ),
					500,
					true
				);
			}

			$this->send_order_interface_error(
				__( 'No shipping methods are available for this customer address. Check the address and your WooCommerce shipping settings, then try again.', 'alynt-wc-customer-order-manager' ),
				400
			);
		}

		set_transient( $shipping_cache_key, $shipping_methods, MINUTE_IN_SECONDS );

		wp_send_json_success( array( 'methods' => $shipping_methods ) );
	}
}
