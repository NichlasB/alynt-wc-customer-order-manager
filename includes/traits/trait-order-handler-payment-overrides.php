<?php
/**
 * Payment gateway cart total overrides for OrderHandler.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

use Exception;

/**
 * Ensures custom prices are honoured at checkout and by payment gateways.
 *
 * @since 1.0.0
 */
trait OrderHandlerPaymentOverridesTrait {

	/**
	 * Override the cart item subtotal HTML to use the custom price.
	 *
	 * @since 1.0.0
	 *
	 * @param string $subtotal      The original formatted subtotal.
	 * @param array  $cart_item     Cart item data.
	 * @param string $_cart_item_key Unique cart item key.
	 * @return string Modified subtotal HTML.
	 */
	public function ensure_cart_item_uses_custom_price( $subtotal, $cart_item, $_cart_item_key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WooCommerce filter signature includes an unused cart item key.
		if ( ! is_user_logged_in() ) {
			return $subtotal;
		}

		if ( isset( $cart_item['awcom_custom_price'] ) ) {
			$quantity        = $cart_item['quantity'];
			$custom_subtotal = $cart_item['awcom_custom_price'] * $quantity;

			if ( isset( $cart_item['data'] ) ) {
				$cart_item['data']->set_price( $cart_item['awcom_custom_price'] );
			}

			return wc_price( $custom_subtotal );
		}

		return $subtotal;
	}

	/**
	 * Re-apply the custom price to the product object within a cart item.
	 *
	 * Ensures the WC_Product price is in sync with awcom_custom_price when the
	 * cart item is accessed after session restore.
	 *
	 * @since 1.0.0
	 *
	 * @param array $cart_item Cart item data.
	 * @return array The cart item with the product price updated.
	 */
	public function ensure_custom_price_on_cart_item( $cart_item ) {
		if ( ! is_user_logged_in() ) {
			return $cart_item;
		}

		if ( isset( $cart_item['awcom_custom_price'] ) && isset( $cart_item['data'] ) ) {
			$cart_item['data']->set_price( $cart_item['awcom_custom_price'] );
			$this->log( 'Cart Item: Set price to ' . $cart_item['awcom_custom_price'] . ' for product #' . $cart_item['data']->get_id() );
		}

		return $cart_item;
	}

	/**
	 * Override the WooCommerce cart total with the correct custom-priced total.
	 *
	 * Only overrides when at least one cart item has a custom price set.
	 * Includes shipping, tax, and fees in the recalculated total.
	 *
	 * @since 1.0.0
	 *
	 * @param float $total The existing cart total.
	 * @return float The corrected cart total.
	 */
	public function override_cart_total( $total ) {
		if ( ! is_user_logged_in() ) {
			return $total;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return $total;
		}

		$correct_total      = 0;
		$has_custom_pricing = false;

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['awcom_custom_price'] ) ) {
				$correct_total     += $cart_item['awcom_custom_price'] * $cart_item['quantity'];
				$has_custom_pricing = true;
			} else {
				$correct_total += $cart_item['data']->get_price() * $cart_item['quantity'];
			}
		}

		if ( $has_custom_pricing ) {
			if ( $cart->needs_shipping() ) {
				$correct_total += $cart->get_shipping_total();
			}

			$correct_total += $cart->get_total_tax();

			foreach ( $cart->get_fees() as $fee ) {
				$correct_total += $fee->total;
			}

			$this->log( 'Cart Total Override: Changing from ' . $total . ' to ' . $correct_total );
			return $correct_total;
		}

		return $total;
	}

	/**
	 * Correct the cart total passed to the PayPal PPCP gateway.
	 *
	 * The PPCP gateway reads cart data directly before WooCommerce filters
	 * have applied custom pricing, so this hook corrects the total.
	 *
	 * @since 1.0.0
	 *
	 * @param array $cart_data The PPCP cart data array containing a 'total' key.
	 * @return array Modified cart data with the corrected total.
	 */
	public function fix_paypal_cart_data( $cart_data ) {
		if ( ! is_user_logged_in() ) {
			return $cart_data;
		}

		$this->log( 'PayPal: Original cart data total = ' . $cart_data['total'] );

		$cart = WC()->cart;
		if ( $cart && ! $cart->is_empty() ) {
			$correct_total = 0;

			foreach ( $cart->get_cart() as $cart_item ) {
				if ( isset( $cart_item['awcom_custom_price'] ) ) {
					$correct_total += $cart_item['awcom_custom_price'] * $cart_item['quantity'];
				} else {
					$correct_total += $cart_item['data']->get_price() * $cart_item['quantity'];
				}
			}

			if ( $cart->needs_shipping() ) {
				$correct_total += $cart->get_shipping_total();
			}

			$correct_total += $cart->get_total_tax();

			foreach ( $cart->get_fees() as $fee ) {
				$correct_total += $fee->total;
			}

			$cart_data['total'] = round( $correct_total, 2 );
			$this->log( 'PayPal: Corrected cart data total = ' . $cart_data['total'] );
		}

		return $cart_data;
	}

	/**
	 * Filter the product price to apply customer group pricing on the front end.
	 *
	 * Checks product-level rules first, then category-level rules. Uses a
	 * per-customer/product static cache to prevent infinite recursion.
	 *
	 * @since 1.0.0
	 *
	 * @param float       $price   The current product price.
	 * @param \WC_Product $product The product being evaluated.
	 * @return float The adjusted price if a rule applies, otherwise the original price.
	 */
	public function apply_customer_group_pricing( $price, $product ) {
		try {
			if ( ! is_user_logged_in() ) {
				return $price;
			}

			if ( is_admin() && ! wp_doing_ajax() ) {
				return $price;
			}

			static $processing = array();

			$customer_id = get_current_user_id();
			if ( ! $customer_id ) {
				return $price;
			}

			$product_id = $product->get_id();
			if ( ! $product_id ) {
				return $price;
			}

			$cache_key = $customer_id . '_' . $product_id;
			if ( isset( $processing[ $cache_key ] ) ) {
				return $price;
			}
			$processing[ $cache_key ] = true;

			$original_price = $product->get_regular_price();
			if ( empty( $original_price ) || ! is_numeric( $original_price ) ) {
				unset( $processing[ $cache_key ] );
				return $price;
			}

			static $user_groups = array();
			if ( ! isset( $user_groups[ $customer_id ] ) ) {
				global $wpdb;
				$table_name = $wpdb->prefix . 'user_groups';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only table existence check for plugin-managed data.
				$table_exists = $wpdb->get_var(
					$wpdb->prepare(
						'SHOW TABLES LIKE %s',
						$wpdb->esc_like( $table_name )
					)
				);

				if ( $table_exists !== $table_name ) {
					unset( $processing[ $cache_key ] );
					return $price;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pricing lookups require direct access to plugin-managed tables.
				$user_groups[ $customer_id ] = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
						$customer_id
					)
				);
			}

			$group_id = $user_groups[ $customer_id ];
			if ( ! $group_id ) {
				unset( $processing[ $cache_key ] );
				return $price;
			}

			$adjusted_price = $original_price;

			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pricing lookups require direct access to plugin-managed tables.
			$product_rule = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT pr.*, g.group_name
                FROM {$wpdb->prefix}pricing_rules pr
                JOIN {$wpdb->prefix}rule_products rp ON pr.rule_id = rp.rule_id
                JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
                WHERE pr.group_id = %d AND rp.product_id = %d
                ORDER BY pr.created_at DESC
                LIMIT 1",
					$group_id,
					$product_id
				)
			);

			if ( $product_rule ) {
				if ( 'percentage' === $product_rule->discount_type ) {
					$adjusted_price = $original_price - ( ( $product_rule->discount_value / 100 ) * $original_price );
				} else {
					$adjusted_price = $original_price - $product_rule->discount_value;
				}
			} else {
				$category_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
				if ( ! empty( $category_ids ) && ! is_wp_error( $category_ids ) ) {
					// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic placeholder list built from sanitized category IDs.
					$placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
					$query        = $wpdb->prepare(
						"SELECT pr.*, g.group_name
                        FROM {$wpdb->prefix}pricing_rules pr
                        JOIN {$wpdb->prefix}rule_categories rc ON pr.rule_id = rc.rule_id
                        JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
                        WHERE pr.group_id = %d AND rc.category_id IN ($placeholders)
                        ORDER BY pr.created_at DESC
                        LIMIT 1",
						array_merge( array( $group_id ), $category_ids )
					);
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query prepared above with a dynamic placeholder list.
					$category_rule = $wpdb->get_row( $query );
					// phpcs:enable

					if ( $category_rule ) {
						if ( 'percentage' === $category_rule->discount_type ) {
							$adjusted_price = $original_price - ( ( $category_rule->discount_value / 100 ) * $original_price );
						} else {
							$adjusted_price = $original_price - $category_rule->discount_value;
						}
					}
				}
			}

			$adjusted_price = max( 0, $adjusted_price );
			unset( $processing[ $cache_key ] );

			if ( $adjusted_price < $original_price ) {
				return $adjusted_price;
			}

			return $price;
		} catch ( Exception $e ) {
			return $price;
		}
	}
}
