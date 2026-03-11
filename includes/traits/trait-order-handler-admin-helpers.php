<?php
/**
 * Helper methods for admin order creation in OrderHandler.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

use Exception;
use WC_Order_Item_Shipping;
use WC_Shipping_Zones;

/**
 * Private helper methods for populating order data during admin order creation.
 *
 * @since 1.0.0
 */
trait OrderHandlerAdminHelpersTrait {

	/**
	 * Copy billing and shipping address fields from a customer's user meta to an order.
	 *
	 * Falls back to the billing address for shipping fields that are empty.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order       The order to populate.
	 * @param int       $customer_id The WordPress user ID.
	 * @return void
	 */
	private function add_customer_data_to_order( $order, $customer_id ) {
		$customer = get_user_by( 'id', $customer_id );
		if ( ! $customer ) {
			return;
		}

		$billing_fields = array(
			'first_name',
			'last_name',
			'company',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'country',
			'email',
			'phone',
		);

		foreach ( $billing_fields as $field ) {
			$value = '';
			if ( 'email' === $field ) {
				$value = $customer->user_email;
			} elseif ( 'first_name' === $field ) {
				$value = $customer->first_name;
			} elseif ( 'last_name' === $field ) {
				$value = $customer->last_name;
			} else {
				$value = get_user_meta( $customer_id, 'billing_' . $field, true );
			}
			$order->{"set_billing_$field"}( $value );
		}

		$shipping_fields = array(
			'first_name',
			'last_name',
			'company',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'country',
		);

		foreach ( $shipping_fields as $field ) {
			$shipping_value = get_user_meta( $customer_id, 'shipping_' . $field, true );
			if ( empty( $shipping_value ) ) {
				$shipping_value = get_user_meta( $customer_id, 'billing_' . $field, true );
			}
			$order->{"set_shipping_$field"}( $shipping_value );
		}
	}

	/**
	 * Add a shipping method line item to an order.
	 *
	 * Queries available shipping methods for the order's destination and
	 * matches the selected rate ID. Throws an exception if no match is found.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order           The order to add shipping to.
	 * @param string    $shipping_method The shipping rate ID (e.g. 'flat_rate:1').
	 * @return bool True on success.
	 * @throws Exception If no shipping methods are available or the selected method is not found.
	 */
	private function add_shipping_to_order( $order, $shipping_method ) {
		try {
			$available_methods = $this->get_available_shipping_methods( $order );
			$this->log( 'Selected shipping method: ' . $shipping_method );

			if ( empty( $available_methods ) ) {
				if ( ! empty( $this->shipping_errors ) ) {
					throw new Exception(
						sprintf(
							/* translators: %s: diagnostic details explaining why no shipping methods were available. */
							__( 'No shipping methods available for this destination. Details: %s', 'alynt-wc-customer-order-manager' ),
							implode( ' | ', $this->shipping_errors )
						)
					);
				}

				throw new Exception( __( 'No shipping methods available for this destination.', 'alynt-wc-customer-order-manager' ) );
			}

			$parts     = explode( ':', $shipping_method );
			$method_id = $parts[0] ?? '';

			foreach ( $available_methods as $method ) {
				if ( $shipping_method === $method['id'] ) {
					$item          = new WC_Order_Item_Shipping();
					$shipping_cost = ! empty( $method['cost'] ) ? floatval( $method['cost'] ) : 0;
					$this->log( 'Setting shipping cost: ' . $shipping_cost );

					$item->set_props(
						array(
							'method_title' => $method['label'],
							'method_id'    => $method_id,
							'instance_id'  => $parts[1] ?? '',
							'total'        => $shipping_cost,
							'taxes'        => $method['taxes'] ?? array( 'total' => array() ),
							'rate_id'      => $shipping_method,
						)
					);

					$order->add_item( $item );
					$order->set_shipping_total( $shipping_cost );
					$order->set_shipping_tax( 0 );
					return true;
				}
			}

			$available_ids = array_column( $available_methods, 'id' );
			throw new Exception(
				sprintf(
					/* translators: 1: selected shipping method ID, 2: comma-separated list of available shipping method IDs. */
					__( 'Selected shipping method "%1$s" not found. Available: %2$s', 'alynt-wc-customer-order-manager' ),
					$shipping_method,
					implode( ', ', $available_ids )
				)
			);
		} catch ( Exception $e ) {
			$this->log( 'Shipping error: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Retrieve all available shipping rates for an order's shipping destination.
	 *
	 * Builds a WooCommerce shipping package from the order's items and destination,
	 * matches a shipping zone, and collects rates from every enabled method in that zone.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The order used to build the shipping package.
	 * @return array List of shipping rate arrays with keys: id, method_id, instance_id, label, cost, taxes.
	 */
	private function get_available_shipping_methods( $order ) {
		$methods = array();
		$errors  = array();

		try {
			$package = array(
				'contents'        => array(),
				'contents_cost'   => 0,
				'applied_coupons' => array(),
				'destination'     => array(
					'country'   => $order->get_shipping_country() ? $order->get_shipping_country() : '',
					'state'     => $order->get_shipping_state() ? $order->get_shipping_state() : '',
					'postcode'  => $order->get_shipping_postcode() ? $order->get_shipping_postcode() : '',
					'city'      => $order->get_shipping_city() ? $order->get_shipping_city() : '',
					'address'   => $order->get_shipping_address_1() ? $order->get_shipping_address_1() : '',
					'address_2' => $order->get_shipping_address_2() ? $order->get_shipping_address_2() : '',
				),
				'user'            => array( 'ID' => $order->get_customer_id() ),
			);

			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( $product ) {
					$package['contents'][]     = array(
						'data'              => $product,
						'quantity'          => $item->get_quantity(),
						'product_id'        => $product->get_id(),
						'variation_id'      => $product->get_id(),
						'variation'         => array(),
						'line_total'        => $item->get_total(),
						'line_tax'          => $item->get_total_tax(),
						'line_subtotal'     => $item->get_subtotal(),
						'line_subtotal_tax' => $item->get_subtotal_tax(),
					);
					$package['contents_cost'] += $item->get_total();
				}
			}

			$this->log( 'Shipping package items count: ' . count( $package['contents'] ) );

			$shipping_zone = WC_Shipping_Zones::get_zone_matching_package( $package );
			$this->log( 'Matched shipping zone: ' . $shipping_zone->get_zone_name() . ' (ID: ' . $shipping_zone->get_id() . ')' );

			$shipping_methods = $shipping_zone->get_shipping_methods( true );
			$this->log( 'Enabled shipping methods in zone: ' . count( $shipping_methods ) );

			foreach ( $shipping_methods as $method ) {
				if ( $method->is_enabled() ) {
					$this->log( 'Processing shipping method: ' . $method->id . ' - ' . $method->get_method_title() );
					$this->log( 'Method class: ' . get_class( $method ) );
					$this->log( 'Method instance ID: ' . $method->get_instance_id() );

					try {
						if ( method_exists( $method, 'get_errors' ) && is_callable( array( $method, 'get_errors' ) ) ) {
							$pre_errors = $method->get_errors();
							if ( ! empty( $pre_errors ) ) {
								$this->log( 'Shipping method returned pre-rate diagnostics.' );
							}
						}

						ob_start();
						$rates  = $method->get_rates_for_package( $package );
						$output = ob_get_clean();

						if ( ! empty( $output ) ) {
							$this->log( 'USPS output during rate calculation: ' . $output );
						}

						if ( method_exists( $method, 'get_errors' ) && is_callable( array( $method, 'get_errors' ) ) ) {
							$post_errors = $method->get_errors();
							if ( ! empty( $post_errors ) ) {
								$errors[] = sprintf(
									/* translators: %s: shipping plugin error details. */
									__( 'USPS plugin errors: %s', 'alynt-wc-customer-order-manager' ),
									implode( ', ', $post_errors )
								);
							}
						}

						if ( property_exists( $method, 'debug' ) && ! empty( $method->debug ) ) {
							$this->log( 'Shipping method debug mode is enabled.' );
						}

						if ( $rates ) {
							$this->log( 'Found ' . count( $rates ) . ' rates for method: ' . $method->id );

							foreach ( $rates as $rate_id => $rate ) {
								$methods[] = array(
									'id'          => $rate_id,
									'method_id'   => $method->id,
									'instance_id' => $method->get_instance_id(),
									'label'       => $rate->get_label(),
									'cost'        => $rate->get_cost(),
									'taxes'       => $rate->get_taxes(),
								);
								$this->log( 'Added rate: ' . $rate_id . ' - ' . $rate->get_label() . ' ($' . $rate->get_cost() . ')' );
							}
						} else {
							$this->log( 'No rates returned for method: ' . $method->id );
							$this->log( 'USPS returned: ' . wp_json_encode( $rates ) );

							if ( method_exists( $method, 'get_instance_form_fields' ) ) {
								$fields = $method->get_instance_form_fields();
								unset( $fields );
							}

							$errors[] = sprintf(
								/* translators: %s: shipping method title. */
								__( 'Shipping method "%s" returned no rates for this destination', 'alynt-wc-customer-order-manager' ),
								$method->get_method_title()
							);
						}
					} catch ( Exception $e ) {
						$error_message = sprintf(
							/* translators: 1: shipping method title, 2: error details. */
							__( 'Shipping method "%1$s" error: %2$s', 'alynt-wc-customer-order-manager' ),
							$method->get_method_title(),
							$e->getMessage()
						);
						$this->log( $error_message );
						$this->log( 'Exception trace: ' . $e->getTraceAsString() );
						$errors[] = $error_message;
					} catch ( \Throwable $t ) {
						$error_message = sprintf(
							/* translators: 1: shipping method title, 2: fatal error details. */
							__( 'Shipping method "%1$s" fatal error: %2$s', 'alynt-wc-customer-order-manager' ),
							$method->get_method_title(),
							$t->getMessage()
						);
						$this->log( $error_message );
						$this->log( 'Throwable trace: ' . $t->getTraceAsString() );
						$errors[] = $error_message;
					}
				}
			}

			$this->log( 'Total available shipping methods calculated: ' . count( $methods ) );
		} catch ( Exception $e ) {
			$error_message = sprintf(
				/* translators: %s: critical shipping calculation error details. */
				__( 'Critical error getting shipping methods: %s', 'alynt-wc-customer-order-manager' ),
				$e->getMessage()
			);
			$this->log( $error_message );
			$errors[] = $error_message;
		}

		$this->shipping_errors = $errors;

		return $methods;
	}

	/**
	 * Log a message recording which admin created an order for which customer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id    The newly created order ID.
	 * @param int $customer_id The customer's WordPress user ID.
	 * @return void
	 */
	private function log_order_creation( $order_id, $customer_id ) {
		$current_user = wp_get_current_user();
		$log_entry    = sprintf(
			/* translators: 1: order ID, 2: customer ID, 3: admin display name. */
			__( 'Order #%1$d created for customer #%2$d by %3$s', 'alynt-wc-customer-order-manager' ),
			$order_id,
			$customer_id,
			$current_user->display_name
		);

		$this->log( $log_entry );
	}

	/**
	 * Redirect back to the Create Order page with an error query parameter.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $customer_id   The customer's WordPress user ID.
	 * @param string $error_message Human-readable error message to display.
	 * @return void  Calls exit after redirecting.
	 */
	private function redirect_with_error( $customer_id, $error_message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'alynt-wc-customer-order-manager-create-order',
					'customer_id' => $customer_id,
					'error'       => rawurlencode( $error_message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
