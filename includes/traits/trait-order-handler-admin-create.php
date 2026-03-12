<?php
/**
 * Admin order creation logic for OrderHandler.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

use Exception;
use WC_Order_Item_Product;

/**
 * Handles the authenticated admin-post request that creates a new WooCommerce order.
 *
 * @since 1.0.0
 */
trait OrderHandlerAdminCreateTrait {

	/**
	 * Process the Create Order form submission.
	 *
	 * Validates the nonce and permissions, resolves customer group pricing
	 * for each submitted product, adds a shipping method, calculates totals,
	 * locks the order total, and redirects to the new order edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_order_creation() {
		$request_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( 'awcom_create_order' !== $request_action ) {
			return;
		}

		$customer_id = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
		$order_nonce = isset( $_POST['awcom_order_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['awcom_order_nonce'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested order items are sanitized per-entry below.
		$raw_items       = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$submitted_items = is_array( $raw_items ) ? $raw_items : array();
		$shipping_method = isset( $_POST['shipping_method'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_method'] ) ) : '';
		$order_notes     = isset( $_POST['order_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['order_notes'] ) ) : '';

		if ( '' === $order_nonce || ! wp_verify_nonce( $order_nonce, 'create_order' ) ) {
			if ( $customer_id > 0 ) {
				$this->redirect_with_error( $customer_id, __( 'Your request could not be verified. Please try again.', 'alynt-wc-customer-order-manager' ) );
			}

			$this->redirect_to_customer_manager_with_error( __( 'Your request could not be verified. Please try again.', 'alynt-wc-customer-order-manager' ) );
		}

		if ( ! Security::user_can_access() ) {
			if ( $customer_id > 0 ) {
				$this->redirect_with_error( $customer_id, __( 'You do not have permission to create orders.', 'alynt-wc-customer-order-manager' ) );
			}

			$this->redirect_to_customer_manager_with_error( __( 'You do not have permission to create orders.', 'alynt-wc-customer-order-manager' ) );
		}

		if ( ! $customer_id ) {
			$this->redirect_to_customer_manager_with_error( __( 'Invalid customer.', 'alynt-wc-customer-order-manager' ) );
		}

		$customer = get_user_by( 'id', $customer_id );
		if ( ! $customer || ! in_array( 'customer', (array) $customer->roles, true ) ) {
			$this->redirect_to_customer_manager_with_error( __( 'Invalid customer.', 'alynt-wc-customer-order-manager' ) );
		}

		if ( empty( $submitted_items ) ) {
			$this->redirect_with_error( $customer_id, __( 'No items selected.', 'alynt-wc-customer-order-manager' ) );
		}

		if ( empty( $shipping_method ) ) {
			$this->redirect_with_error( $customer_id, __( 'Please select a shipping method.', 'alynt-wc-customer-order-manager' ) );
		}

		$validated_items   = array();
		$has_invalid_items = false;

		foreach ( $submitted_items as $product_id => $item_data ) {
			$product_id = absint( $product_id );
			$quantity   = isset( $item_data['quantity'] ) ? absint( $item_data['quantity'] ) : 0;

			if ( $product_id <= 0 || $quantity < 1 ) {
				$has_invalid_items = true;
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$has_invalid_items = true;
				continue;
			}

			$validated_items[] = array(
				'product_id' => $product_id,
				'product'    => $product,
				'quantity'   => $quantity,
			);
		}

		if ( empty( $validated_items ) ) {
			$this->redirect_with_error(
				$customer_id,
				__( 'Please add at least one valid product with a quantity greater than 0.', 'alynt-wc-customer-order-manager' )
			);
		}

		if ( $has_invalid_items ) {
			$this->redirect_with_error(
				$customer_id,
				__( 'One or more selected products are no longer available or have an invalid quantity. Please review the order items and try again.', 'alynt-wc-customer-order-manager' )
			);
		}

		$order = null;

		try {
			$order = wc_create_order(
				array(
					'customer_id' => $customer_id,
					'created_via' => self::ORDER_CREATED_VIA,
				)
			);

			if ( is_wp_error( $order ) ) {
				$this->log_order_handler_error( 'wc_create_order returned WP_Error: ' . $order->get_error_message() );
				$this->redirect_with_error(
					$customer_id,
					__( 'Could not create the order. Please try again.', 'alynt-wc-customer-order-manager' )
				);
			}

			$product_ids          = array_map(
				static function ( $validated_item ) {
					return (int) $validated_item['product_id'];
				},
				$validated_items
			);
			$group_id             = PricingRuleLookup::get_customer_group_id( $customer_id, true );
			$product_category_map = array();
			$rule_lookup          = array(
				'product_rules'        => array(),
				'category_rules'       => array(),
				'product_category_map' => array(),
			);
			if ( $group_id && ! empty( $product_ids ) ) {
				$product_category_map = PricingRuleLookup::get_product_category_map( $product_ids );
				$rule_lookup          = PricingRuleLookup::get_rule_lookup( $group_id, $product_ids, $product_category_map, true, true );
			}

			foreach ( $validated_items as $validated_item ) {
				$product_id = $validated_item['product_id'];
				$product    = $validated_item['product'];
				$quantity   = $validated_item['quantity'];

				try {
					$original_price = PricingRuleLookup::get_product_base_price( $product );

					$adjusted_price       = $original_price;
					$discount_description = '';

					if ( $group_id ) {
						$matching_rule = PricingRuleLookup::get_matching_rule( $product_id, $rule_lookup );

						if ( $matching_rule ) {
							$adjusted_price = PricingRuleLookup::get_adjusted_price( $original_price, $matching_rule );

							if ( 'percentage' === $matching_rule->discount_type ) {
								$discount_description = sprintf(
									/* translators: 1: customer group name, 2: discount percentage. */
									__( '%1$s Group Discount: %2$s%%', 'alynt-wc-customer-order-manager' ),
									$matching_rule->group_name,
									$matching_rule->discount_value
								);
							} else {
								$discount_description = sprintf(
									/* translators: 1: customer group name, 2: discount amount. */
									__( '%1$s Group Discount: %2$s', 'alynt-wc-customer-order-manager' ),
									$matching_rule->group_name,
									wc_price( $matching_rule->discount_value )
								);
							}
						}
					}

					$adjusted_price = max( 0, $adjusted_price );

					$item = new WC_Order_Item_Product();
					$item->set_props(
						array(
							'product'  => $product,
							'quantity' => $quantity,
							'subtotal' => $original_price * $quantity,
							'total'    => $adjusted_price * $quantity,
						)
					);

					$item->add_meta_data( self::ITEM_META_CUSTOM_PRICE, $adjusted_price, true );
					$item->add_meta_data( self::ITEM_META_CUSTOM_SUBTOTAL_PRICE, $original_price, true );

					if ( $adjusted_price < $original_price ) {
						$item->add_meta_data( self::ITEM_META_DISCOUNT_DESCRIPTION, $discount_description );

						$discount_amount = ( $original_price - $adjusted_price ) * $quantity;
						$item->set_subtotal( $original_price * $quantity );
						$item->set_total( $adjusted_price * $quantity );

						$order->add_order_note(
							sprintf(
								/* translators: 1: discount description, 2: original product price, 3: discounted product price, 4: total discount amount. */
								__( 'Applied %1$s. Original Price: %2$s, Discounted Price: %3$s, Total Discount: %4$s', 'alynt-wc-customer-order-manager' ),
								$discount_description,
								wc_price( $original_price ),
								wc_price( $adjusted_price ),
								wc_price( $discount_amount )
							)
						);
					}

					$order->add_item( $item );
				} catch ( Exception $e ) {
					$this->log_order_handler_error(
						sprintf(
							'Error adding product #%1$d to a new order for customer #%2$d: %3$s',
							$product_id,
							$customer_id,
							$e->getMessage()
						)
					);

					$this->cleanup_failed_order_creation( $order );

					$this->redirect_with_error(
						$customer_id,
						sprintf(
							/* translators: %s: product name. */
							__( 'Could not add "%s" to the order. Please try again.', 'alynt-wc-customer-order-manager' ),
							$product->get_name()
						)
					);
					return;
				}
			}

			$this->add_customer_data_to_order( $order, $customer_id );

			try {
				$this->add_shipping_to_order( $order, $shipping_method );
			} catch ( Exception $e ) {
				$this->log_order_handler_error(
					sprintf(
						'Error adding shipping method "%1$s" for customer #%2$d: %3$s',
						$shipping_method,
						$customer_id,
						$e->getMessage()
					)
				);

				$this->cleanup_failed_order_creation( $order );

				$this->redirect_with_error(
					$customer_id,
					__( 'Could not add the selected shipping method. Please refresh the shipping options and try again.', 'alynt-wc-customer-order-manager' )
				);
				return;
			}

			if ( ! empty( $order_notes ) ) {
				$order->add_order_note( $order_notes, 0, true );
			}

			$order->calculate_totals( false );

			if ( $order->get_shipping_total() <= 0 ) {
				$this->log( 'Warning: Zero or negative shipping total detected' );
				$order->calculate_shipping();
				$order->calculate_totals( false );
			}

			if ( ! $order->get_items( 'shipping' ) ) {
				$this->log( 'Warning: No shipping items found in order' );
			}

			$order->update_meta_data( self::ORDER_META_HAS_CUSTOM_PRICING, 'yes' );
			$order->update_meta_data( self::ORDER_META_PRICING_LOCKED, 'yes' );
			$order->update_meta_data( self::ORDER_META_LOCKED_TOTAL, $order->get_total() );

			$this->log( 'Order Creation: Locked total for order #' . $order->get_id() . ' at ' . $order->get_total() );

			$order->save();

			$admin_note = sprintf(
				/* translators: %s: admin display name. */
				__( 'Order created via Alynt WC Customer Order Manager by %s', 'alynt-wc-customer-order-manager' ),
				wp_get_current_user()->display_name
			);
			$order->add_order_note( $admin_note, 0, false );

			$this->log_order_creation( $order->get_id(), $customer_id );

			wp_safe_redirect( awcom_get_order_edit_url( $order->get_id() ) );
			exit;
		} catch ( \Exception $e ) {
			$this->log_order_handler_error(
				sprintf(
					'Unexpected order creation error for customer #%1$d: %2$s',
					$customer_id,
					$e->getMessage()
				)
			);

			$this->cleanup_failed_order_creation( $order );

			$this->redirect_with_error(
				$customer_id,
				__( 'Could not create the order. Please try again.', 'alynt-wc-customer-order-manager' )
			);
		}
	}
}
