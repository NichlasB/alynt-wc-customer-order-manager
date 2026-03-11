<?php
/**
 * Cart pricing application for OrderHandler.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Applies customer group pricing to cart items.
 *
 * @since 1.0.0
 */
trait OrderHandlerCartPricingCoreTrait {

	/**
	 * Adjust cart item prices based on the customer's group pricing rules.
	 *
	 * Checks product-level rules first, then falls back to category-level rules.
	 * Uses a static guard to prevent recursive calls. Skips non-AJAX admin requests.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Cart $cart The WooCommerce cart object.
	 * @return void
	 */
	public function apply_cart_customer_pricing( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		static $running = false;
		if ( $running ) {
			return;
		}
		$running = true;

		$customer_id = get_current_user_id();
		if ( ! $customer_id ) {
			$running = false;
			return;
		}

		$this->log( 'Cart pricing: Starting for customer #' . $customer_id );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pricing lookups require direct access to plugin-managed tables.
		$group_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
				$customer_id
			)
		);

		if ( ! $group_id ) {
			$this->log( 'Cart pricing: No group found for customer #' . $customer_id );
			$running = false;
			return;
		}

		$this->log( 'Cart pricing: Customer is in group #' . $group_id );

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product        = $cart_item['data'];
			$product_id     = $product->get_id();
			$original_price = $product->get_regular_price();

			$this->log( "Cart pricing: Processing product #{$product_id}, original price: {$original_price}" );

			if ( empty( $original_price ) || ! is_numeric( $original_price ) ) {
				continue;
			}

			$adjusted_price = $original_price;

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

			$group_name = '';
			if ( $product_rule ) {
				if ( 'percentage' === $product_rule->discount_type ) {
					$adjusted_price = $original_price - ( ( $product_rule->discount_value / 100 ) * $original_price );
				} else {
					$adjusted_price = $original_price - $product_rule->discount_value;
				}
				$group_name = $product_rule->group_name;
				$this->log( "Cart pricing: Applied product rule, adjusted price: {$adjusted_price}" );
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
						$group_name = $category_rule->group_name;
						$this->log( "Cart pricing: Applied category rule, adjusted price: {$adjusted_price}" );
					}
				}
			}

			if ( $adjusted_price < $original_price ) {
				$adjusted_price = max( 0, $adjusted_price );
				$product->set_price( $adjusted_price );

				$cart->cart_contents[ $cart_item_key ]['line_subtotal']      = $adjusted_price * $cart_item['quantity'];
				$cart->cart_contents[ $cart_item_key ]['line_total']         = $adjusted_price * $cart_item['quantity'];
				$cart->cart_contents[ $cart_item_key ]['line_tax']           = 0;
				$cart->cart_contents[ $cart_item_key ]['line_subtotal_tax']  = 0;
				$cart->cart_contents[ $cart_item_key ]['awcom_custom_price'] = $adjusted_price;
				$cart->cart_contents[ $cart_item_key ]['awcom_group_name']   = $group_name;

				$this->log( "Cart pricing: Set price to {$adjusted_price} for product #{$product_id} with {$group_name} pricing" );
				$this->log( 'Cart pricing: Set line_total to ' . ( $adjusted_price * $cart_item['quantity'] ) );
			}
		}

		$running = false;
		$this->log( 'Cart pricing: Completed' );
	}

	/**
	 * Restore custom pricing to a cart item when it is loaded from the session.
	 *
	 * Hooked to woocommerce_get_cart_item_from_session. Looks up the customer's
	 * group pricing rule and re-applies it to the product object so the price
	 * persists across page loads.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $session_data The cart item session data.
	 * @param array  $_values      Original cart item values.
	 * @param string $_key         Cart item key.
	 * @return array Modified session data with updated product price.
	 */
	public function apply_pricing_from_session( $session_data, $_values, $_key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WooCommerce session filter passes unused context arguments.
		if ( ! is_user_logged_in() ) {
			return $session_data;
		}

		$customer_id = get_current_user_id();
		if ( ! $customer_id ) {
			return $session_data;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pricing lookups require direct access to plugin-managed tables.
		$group_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
				$customer_id
			)
		);

		if ( ! $group_id ) {
			return $session_data;
		}

		$product        = $session_data['data'];
		$product_id     = $product->get_id();
		$original_price = $product->get_regular_price();

		if ( empty( $original_price ) || ! is_numeric( $original_price ) ) {
			return $session_data;
		}

		$adjusted_price = $original_price;
		$group_name     = '';

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
			$group_name = $product_rule->group_name;
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
					$group_name = $category_rule->group_name;
				}
			}
		}

		if ( $adjusted_price < $original_price ) {
			$adjusted_price = max( 0, $adjusted_price );
			$session_data['data']->set_price( $adjusted_price );
			$session_data['awcom_custom_price'] = $adjusted_price;
			$session_data['awcom_group_name']   = $group_name;

			$this->log( "Session pricing: Set price to {$adjusted_price} for product #{$product_id} with {$group_name} pricing" );
		}

		return $session_data;
	}
}
