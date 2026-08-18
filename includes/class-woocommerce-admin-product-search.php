<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * WooCommerce admin product search compatibility.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.1.3
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Improves WooCommerce admin AJAX product search relevance.
 *
 * @since 1.1.3
 */
class WooCommerceAdminProductSearch {

	/**
	 * WooCommerce product-search AJAX actions that should use the override.
	 *
	 * @since 1.1.3
	 *
	 * @var string[]
	 */
	private $ajax_actions = array(
		'woocommerce_json_search_products',
		'woocommerce_json_search_products_and_variations',
		'woocommerce_json_search_downloadable_products_and_variations',
	);

	/**
	 * Register hooks.
	 *
	 * @since 1.1.3
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_pre_search_products', array( $this, 'filter_admin_product_search' ), 10, 6 );
	}

	/**
	 * Replace WooCommerce's broad admin product search with title/SKU-first results.
	 *
	 * WooCommerce's default search also scans excerpts and product content, which
	 * can push direct title matches out of the limited admin dropdown results.
	 *
	 * @since 1.1.3
	 *
	 * @param bool|array $custom_results     Existing short-circuit results.
	 * @param string     $term               Search term.
	 * @param string     $type               Product type filter.
	 * @param bool       $include_variations Include product variations.
	 * @param bool       $all_statuses       Whether all statuses should be searched.
	 * @param int|null   $limit              Result limit.
	 * @return bool|array
	 */
	public function filter_admin_product_search( $custom_results, $term, $type = '', $include_variations = false, $all_statuses = false, $limit = null ) {
		if ( is_array( $custom_results ) || ! $this->should_filter_search( $term ) ) {
			return $custom_results;
		}

		global $wpdb;

		$term       = wc_clean( wp_unslash( $term ) );
		$limit      = $limit ? absint( $limit ) : absint( apply_filters( 'woocommerce_json_search_limit', 30 ) );
		$post_types = $include_variations ? array( 'product', 'product_variation' ) : array( 'product' );

		$post_statuses = $all_statuses
			? get_post_stati()
			: apply_filters(
				'woocommerce_search_products_post_statuses',
				// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this product capability.
				current_user_can( 'edit_private_products' ) ? array( 'private', 'publish' ) : array( 'publish' )
			);

		$include_ids = $this->get_request_id_list( 'include' );
		$exclude_ids = $this->get_request_id_list( 'exclude' );

		$where_parts = array(
			"posts.post_type IN ('" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "')",
			"posts.post_status IN ('" . implode( "','", array_map( 'esc_sql', $post_statuses ) ) . "')",
		);

		if ( ! empty( $include_ids ) ) {
			$where_parts[] = 'posts.ID IN (' . implode( ',', array_map( 'absint', $include_ids ) ) . ')';
		}

		if ( ! empty( $exclude_ids ) ) {
			$where_parts[] = 'posts.ID NOT IN (' . implode( ',', array_map( 'absint', $exclude_ids ) ) . ')';
		}

		if ( 'virtual' === $type ) {
			$where_parts[] = 'wc_product_meta_lookup.virtual = 1';
		} elseif ( 'downloadable' === $type ) {
			$where_parts[] = 'wc_product_meta_lookup.downloadable = 1';
		}

		$like         = '%' . $wpdb->esc_like( $term ) . '%';
		$prefix_like  = $wpdb->esc_like( $term ) . '%';
		$join_query   = '';
		$search_parts = array(
			$wpdb->prepare( 'posts.post_title LIKE %s', $like ),
			$wpdb->prepare( 'wc_product_meta_lookup.sku LIKE %s', $like ),
			$wpdb->prepare( 'wc_product_meta_lookup.global_unique_id LIKE %s', $like ),
		);

		if ( $include_variations ) {
			$join_query     = " LEFT JOIN {$wpdb->wc_product_meta_lookup} parent_wc_product_meta_lookup
				ON posts.post_type = 'product_variation' AND parent_wc_product_meta_lookup.product_id = posts.post_parent ";
			$search_parts[] = $wpdb->prepare( '( wc_product_meta_lookup.sku = "" AND parent_wc_product_meta_lookup.sku LIKE %s )', $like );
			$search_parts[] = $wpdb->prepare( '( wc_product_meta_lookup.global_unique_id = "" AND parent_wc_product_meta_lookup.global_unique_id LIKE %s )', $like );
		}

		$where_parts[] = '( ' . implode( ' OR ', $search_parts ) . ' )';
		$limit_query   = $limit ? $wpdb->prepare( ' LIMIT %d ', $limit ) : '';
		$where_sql     = implode( ' AND ', $where_parts );
		$orderby_sql   = $wpdb->prepare(
			'CASE
				WHEN posts.post_title = %s THEN 0
				WHEN posts.post_title LIKE %s THEN 1
				WHEN wc_product_meta_lookup.sku LIKE %s THEN 2
				WHEN wc_product_meta_lookup.global_unique_id LIKE %s THEN 3
				ELSE 4
			END ASC',
			$term,
			$prefix_like,
			$prefix_like,
			$prefix_like
		);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$search_results = $wpdb->get_results(
			"SELECT DISTINCT posts.ID AS product_id, posts.post_parent AS parent_id
			FROM {$wpdb->posts} posts
			LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup ON posts.ID = wc_product_meta_lookup.product_id
			{$join_query}
			WHERE {$where_sql}
			ORDER BY
				{$orderby_sql},
				posts.post_parent ASC,
				posts.post_title ASC
			{$limit_query}"
		);
		// phpcs:enable

		if ( ! empty( $wpdb->last_error ) ) {
			Diagnostics::log(
				'database',
				'error',
				'wc_admin_product_search_failed',
				'WooCommerce admin product search query failed.',
				array(
					'term'  => $term,
					'error' => $wpdb->last_error,
				)
			);
		}

		$product_ids = array_filter( wp_parse_id_list( array_merge( wp_list_pluck( $search_results, 'product_id' ), wp_list_pluck( $search_results, 'parent_id' ) ) ) );

		if ( is_numeric( $term ) ) {
			$product_ids = array_merge( $product_ids, $this->get_numeric_term_ids( $term, (bool) $include_variations ) );
		}

		return array_filter( wp_parse_id_list( $product_ids ) );
	}

	/**
	 * Determine whether the current request is a WooCommerce admin product search.
	 *
	 * @since 1.1.3
	 *
	 * @param string $term Search term.
	 * @return bool
	 */
	private function should_filter_search( $term ) {
		if ( ! is_admin() || ! wp_doing_ajax() || '' === trim( (string) $term ) ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce verifies the AJAX nonce before searching.

		if ( ! in_array( $action, $this->ajax_actions, true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers these order/product management capabilities.
		return current_user_can( 'edit_shop_orders' ) || current_user_can( 'edit_products' ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Read an include/exclude product ID list from the AJAX request.
	 *
	 * @since 1.1.3
	 *
	 * @param string $key Request key.
	 * @return int[]
	 */
	private function get_request_id_list( $key ) {
		if ( empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce verifies the AJAX nonce before searching.
			return array();
		}

		return array_filter( array_map( 'absint', (array) wp_unslash( $_GET[ $key ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce verifies the AJAX nonce before searching.
	}

	/**
	 * Include direct product IDs when the search term is numeric.
	 *
	 * @since 1.1.3
	 *
	 * @param string $term               Search term.
	 * @param bool   $include_variations Include product variations.
	 * @return int[]
	 */
	private function get_numeric_term_ids( $term, $include_variations ) {
		$post_id   = absint( $term );
		$post_type = get_post_type( $post_id );
		$ids       = array();

		if ( 'product_variation' === $post_type && $include_variations ) {
			$ids[] = $post_id;
		} elseif ( 'product' === $post_type ) {
			$ids[] = $post_id;
		}

		$ids[] = wp_get_post_parent_id( $post_id );

		return array_filter( array_map( 'absint', $ids ) );
	}
}
