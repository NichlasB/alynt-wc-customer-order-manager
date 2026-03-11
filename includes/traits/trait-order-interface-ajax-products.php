<?php
/**
 * Product search AJAX endpoint for OrderInterface.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the AJAX product search request for the Create Order interface.
 *
 * @since 1.0.0
 */
trait OrderInterfaceAjaxProductsTrait {

	/**
	 * Handle the AJAX product search request.
	 *
	 * Searches published products by title and SKU, applies customer group
	 * pricing to results, and returns a JSON array of product data including
	 * price, discount, and stock information.
	 *
	 * @since 1.0.0
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function ajax_search_products() {
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
				__( 'You do not have permission to search products.', 'alynt-wc-customer-order-manager' ),
				403
			);
		}

		$customer_id = isset( $_GET['customer_id'] ) ? absint( wp_unslash( $_GET['customer_id'] ) ) : 0;
		$term        = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( empty( $term ) ) {
			wp_send_json_success( array( 'products' => array() ) );
		}

		try {
			global $wpdb;
			$search_term = '%' . $wpdb->esc_like( $term ) . '%';

			$title_sql = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
                WHERE post_type IN ('product', 'product_variation')
                AND post_status = 'publish'
                AND post_title LIKE %s
                ORDER BY post_title ASC
                LIMIT 25",
				$search_term
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query prepared above.
			$title_ids = array_values( array_unique( array_map( 'absint', $wpdb->get_col( $title_sql ) ) ) );

			$sku_args = array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'publish',
				'posts_per_page' => 25,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- SKU search fallback requires a meta query.
				'meta_query'     => array(
					array(
						'key'     => '_sku',
						'value'   => $term,
						'compare' => 'LIKE',
					),
				),
				'orderby'        => 'title',
				'order'          => 'ASC',
			);

			$sku_query = new \WP_Query( $sku_args );
			$sku_ids   = array_values( array_unique( array_map( 'absint', $sku_query->posts ) ) );
			$found_ids = array_values( array_unique( array_merge( $title_ids, $sku_ids ) ) );
			$found_posts = array();
			if ( ! empty( $found_ids ) ) {
				$found_posts = get_posts(
					array(
						'post_type'              => array( 'product', 'product_variation' ),
						'post_status'            => 'publish',
						'post__in'               => $found_ids,
						'orderby'                => 'post__in',
						'posts_per_page'         => count( $found_ids ),
						'no_found_rows'          => true,
						'update_post_meta_cache' => true,
						'update_post_term_cache' => false,
					)
				);
			}
			wp_reset_postdata();

			usort(
				$found_posts,
				function ( $a, $b ) {
					return strcmp( $a->post_title, $b->post_title );
				}
			);

			$product_ids          = array_map(
				static function ( $post ) {
					return (int) $post->ID;
				},
				$found_posts
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
				$rule_lookup          = PricingRuleLookup::get_rule_lookup( $group_id, $product_ids, $product_category_map, true, false );
			}

			$products = array();
			foreach ( $found_posts as $post ) {
				$product = wc_get_product( $post->ID );
				if ( ! $product || ! $product->is_purchasable() ) {
					continue;
				}

				$sale_price = $product->get_sale_price();
				if ( ! empty( $sale_price ) && is_numeric( $sale_price ) && $sale_price > 0 ) {
					$original_price = (float) $sale_price;
				} else {
					$regular_price = $product->get_regular_price();
					if ( ! empty( $regular_price ) && is_numeric( $regular_price ) && $regular_price > 0 ) {
						$original_price = (float) $regular_price;
					} else {
						$current_price  = $product->get_price();
						$original_price = ( ! empty( $current_price ) && is_numeric( $current_price ) ) ? (float) $current_price : 0.0;
					}
				}

				if ( $original_price <= 0 ) {
					continue;
				}

				$adjusted_price = $original_price;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pricing lookups require direct access to plugin-managed tables.
				$matching_rule = $group_id ? PricingRuleLookup::get_matching_rule( $product->get_id(), $rule_lookup ) : null;

				if ( $matching_rule ) {
					if ( 'percentage' === $matching_rule->discount_type ) {
						$adjusted_price = $original_price - ( ( $matching_rule->discount_value / 100 ) * $original_price );
					} else {
						$adjusted_price = $original_price - $matching_rule->discount_value;
					}
				}

				$adjusted_price = max( 0, $adjusted_price );

				$stock_quantity = $product->get_stock_quantity();
				$stock_status   = $product->get_stock_status();
				$manage_stock   = $product->get_manage_stock();

				$stock_display = '';
				if ( $manage_stock && null !== $stock_quantity ) {
					if ( 0 < $stock_quantity ) {
						$stock_display = sprintf(
							/* translators: %d: number of items currently in stock. */
							_n( '%d in stock', '%d in stock', $stock_quantity, 'alynt-wc-customer-order-manager' ),
							$stock_quantity
						);
					} else {
						$stock_display = __( 'Out of stock', 'alynt-wc-customer-order-manager' );
					}
				} elseif ( 'instock' === $stock_status ) {
					$stock_display = __( 'In stock', 'alynt-wc-customer-order-manager' );
				} elseif ( 'outofstock' === $stock_status ) {
					$stock_display = __( 'Out of stock', 'alynt-wc-customer-order-manager' );
				} elseif ( 'onbackorder' === $stock_status ) {
					$stock_display = __( 'On backorder', 'alynt-wc-customer-order-manager' );
				}

				$products[] = array(
					'id'                       => $product->get_id(),
					'text'                     => $product->get_formatted_name(),
					'price'                    => $adjusted_price,
					'formatted_price'          => wc_price( $adjusted_price ),
					'original_price'           => $original_price,
					'formatted_original_price' => wc_price( $original_price ),
					'has_discount'             => $adjusted_price < $original_price,
					'stock_quantity'           => $stock_quantity,
					'stock_status'             => $stock_status,
					'stock_display'            => $stock_display,
					'manage_stock'             => $manage_stock,
				);
			}

			wp_reset_postdata();
			wp_send_json_success( array( 'products' => $products ) );
		} catch ( \Throwable $throwable ) {
			wp_reset_postdata();
			$this->log_order_interface_error( 'Product search failed: ' . $throwable->getMessage() );
			$this->send_order_interface_error(
				__( 'We could not load products right now. Please try again.', 'alynt-wc-customer-order-manager' ),
				500,
				true
			);
		}
	}
}
