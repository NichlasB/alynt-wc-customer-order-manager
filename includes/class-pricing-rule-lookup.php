<?php

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

class PricingRuleLookup {
	private static $table_exists = array();
	private static $customer_group_ids = array();
	private static $product_category_maps = array();
	private static $product_contexts = array();
	private static $rule_lookups = array();
	private static $group_display_titles = array();

	public static function get_product_base_price( $product ) {
		if ( ! is_object( $product ) ) {
			return 0.0;
		}

		$sale_price = method_exists( $product, 'get_sale_price' ) ? $product->get_sale_price() : '';
		if ( ! empty( $sale_price ) && is_numeric( $sale_price ) && $sale_price > 0 ) {
			return (float) $sale_price;
		}

		$regular_price = method_exists( $product, 'get_regular_price' ) ? $product->get_regular_price() : '';
		if ( ! empty( $regular_price ) && is_numeric( $regular_price ) && $regular_price > 0 ) {
			return (float) $regular_price;
		}

		$current_price = method_exists( $product, 'get_price' ) ? $product->get_price() : '';

		return ( ! empty( $current_price ) && is_numeric( $current_price ) ) ? (float) $current_price : 0.0;
	}

	public static function get_adjusted_price( $original_price, $matching_rule ) {
		$original_price = is_numeric( $original_price ) ? (float) $original_price : 0.0;

		if ( $original_price <= 0 || ! is_object( $matching_rule ) ) {
			return max( 0, $original_price );
		}

		$discount_type  = isset( $matching_rule->discount_type ) ? (string) $matching_rule->discount_type : '';
		$discount_value = isset( $matching_rule->discount_value ) && is_numeric( $matching_rule->discount_value )
			? (float) $matching_rule->discount_value
			: 0.0;

		if ( 'percentage' === $discount_type ) {
			$adjusted_price = $original_price - ( ( $discount_value / 100 ) * $original_price );
		} else {
			$adjusted_price = $original_price - $discount_value;
		}

		return max( 0, $adjusted_price );
	}

	public static function get_customer_group_id( $customer_id, $use_default = false ) {
		$customer_id = absint( $customer_id );
		$cache_key   = ( $use_default ? 'default:' : 'direct:' ) . $customer_id;

		if ( array_key_exists( $cache_key, self::$customer_group_ids ) ) {
			return self::$customer_group_ids[ $cache_key ];
		}

		global $wpdb;
		$group_id          = 0;
		$user_groups_table = $wpdb->prefix . 'user_groups';

		if ( $customer_id > 0 && self::table_exists( $user_groups_table ) ) {
			$group_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT group_id FROM {$user_groups_table} WHERE user_id = %d",
					$customer_id
				)
			);
		}

		if ( $group_id <= 0 && $use_default ) {
			$group_id = (int) get_option( 'wccg_default_group_id', 0 );
		}

		self::$customer_group_ids[ $cache_key ] = $group_id;

		return $group_id;
	}

	public static function customer_group_exists( $group_id ) {
		$group_id = absint( $group_id );

		if ( $group_id <= 0 ) {
			return false;
		}

		global $wpdb;
		$customer_groups_table = $wpdb->prefix . 'customer_groups';

		if ( ! self::table_exists( $customer_groups_table ) ) {
			return false;
		}

		$matching_group = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$customer_groups_table} WHERE group_id = %d LIMIT 1",
				$group_id
			)
		);

		return (bool) $matching_group;
	}

	public static function get_product_category_map( array $product_ids ) {
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		sort( $product_ids );

		if ( empty( $product_ids ) ) {
			return array();
		}

		$cache_key = md5( wp_json_encode( $product_ids ) );
		if ( isset( self::$product_category_maps[ $cache_key ] ) ) {
			return self::$product_category_maps[ $cache_key ];
		}

		$product_contexts    = self::get_product_contexts( $product_ids );
		$product_category_map = array();

		foreach ( $product_contexts as $product_id => $context ) {
			$product_category_map[ $product_id ] = self::get_cached_product_categories( $context['category_source_id'] );
		}

		self::$product_category_maps[ $cache_key ] = $product_category_map;

		return $product_category_map;
	}

	public static function get_rule_lookup( $group_id, array $product_ids, array $product_category_map, $active_only = false, $include_group_name = false ) {
		$group_id    = absint( $group_id );
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		sort( $product_ids );

		if ( $group_id <= 0 || empty( $product_ids ) ) {
			return array(
				'product_rules'         => array(),
				'category_rules'        => array(),
				'product_category_map'  => $product_category_map,
				'resolved_rules'        => array(),
			);
		}

		$cache_key = md5(
			wp_json_encode(
				array(
					$group_id,
					$product_ids,
					$product_category_map,
					(bool) $active_only,
					(bool) $include_group_name,
				)
			)
		);

		if ( isset( self::$rule_lookups[ $cache_key ] ) ) {
			return self::$rule_lookups[ $cache_key ];
		}

		$lookup = array(
			'product_rules'        => array(),
			'category_rules'       => array(),
			'product_category_map' => $product_category_map,
			'resolved_rules'       => array(),
		);

		if ( empty( $product_category_map ) ) {
			$product_category_map = self::get_product_category_map( $product_ids );
			$lookup['product_category_map'] = $product_category_map;
		}

		$product_contexts = self::get_product_contexts( $product_ids );
		$product_rules    = self::get_product_specific_rules_by_match_id( $product_contexts, $group_id, $active_only, $include_group_name );
		$unresolved_ids   = array();

		foreach ( $product_contexts as $product_id => $context ) {
			$product_rule = self::select_product_specific_rule( $context, $product_rules );
			if ( $product_rule ) {
				$lookup['resolved_rules'][ $product_id ] = $product_rule;
				continue;
			}

			$unresolved_ids[] = $product_id;
		}

		if ( ! empty( $unresolved_ids ) ) {
			$category_rules = self::get_category_rules_by_product_id( $unresolved_ids, $product_category_map, $group_id, $active_only, $include_group_name );

			foreach ( $unresolved_ids as $product_id ) {
				$lookup['resolved_rules'][ $product_id ] = ! empty( $category_rules[ $product_id ] )
					? self::determine_best_rule( $category_rules[ $product_id ] )
					: null;
			}
		}

		self::$rule_lookups[ $cache_key ] = $lookup;

		return $lookup;
	}

	public static function get_matching_rule( $product_id, array $rule_lookup ) {
		$product_id = absint( $product_id );

		if ( isset( $rule_lookup['resolved_rules'] ) && array_key_exists( $product_id, $rule_lookup['resolved_rules'] ) ) {
			return $rule_lookup['resolved_rules'][ $product_id ];
		}

		return null;
	}

	public static function get_customer_group_display_title( $customer_id ) {
		$customer_id = absint( $customer_id );

		if ( array_key_exists( $customer_id, self::$group_display_titles ) ) {
			return self::$group_display_titles[ $customer_id ];
		}

		global $wpdb;
		$display_title         = '';
		$customer_groups_table = $wpdb->prefix . 'customer_groups';
		$user_groups_table     = $wpdb->prefix . 'user_groups';
		$pricing_rules_table   = $wpdb->prefix . 'pricing_rules';

		if ( $customer_id > 0 && self::table_exists( $customer_groups_table ) && self::table_exists( $user_groups_table ) ) {
			$display_title = (string) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT g.group_name
					FROM {$customer_groups_table} g
					JOIN {$user_groups_table} ug ON g.group_id = ug.group_id
					WHERE ug.user_id = %d",
					$customer_id
				)
			);
		}

		if ( '' === $display_title ) {
			$default_group_id = (int) get_option( 'wccg_default_group_id', 0 );

			if ( $default_group_id > 0 && self::table_exists( $pricing_rules_table ) && self::group_has_active_pricing_rule( $default_group_id ) ) {
				$custom_title = (string) get_option( 'wccg_default_group_custom_title', '' );
				if ( '' !== $custom_title ) {
					$display_title = $custom_title;
				} elseif ( self::table_exists( $customer_groups_table ) ) {
					$display_title = (string) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT group_name FROM {$customer_groups_table} WHERE group_id = %d",
							$default_group_id
						)
					);
				}
			}
		}

		self::$group_display_titles[ $customer_id ] = $display_title;

		return $display_title;
	}

	private static function table_exists( $table_name ) {
		if ( array_key_exists( $table_name, self::$table_exists ) ) {
			return self::$table_exists[ $table_name ];
		}

		global $wpdb;
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table_name )
			)
		);

		self::$table_exists[ $table_name ] = ( $exists === $table_name );

		return self::$table_exists[ $table_name ];
	}

	private static function group_has_active_pricing_rule( $group_id ) {
		$group_id = absint( $group_id );
		if ( $group_id <= 0 ) {
			return false;
		}

		global $wpdb;
		$pricing_rules_table = $wpdb->prefix . 'pricing_rules';

		if ( ! self::table_exists( $pricing_rules_table ) ) {
			return false;
		}

		$has_active_rule = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$pricing_rules_table} WHERE group_id = %d AND is_active = 1 AND (start_date IS NULL OR start_date <= UTC_TIMESTAMP()) AND (end_date IS NULL OR end_date >= UTC_TIMESTAMP()) LIMIT 1",
				$group_id
			)
		);

		return (bool) $has_active_rule;
	}

	private static function get_product_contexts( array $product_ids ) {
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		sort( $product_ids );

		if ( empty( $product_ids ) ) {
			return array();
		}

		$cache_key = md5( wp_json_encode( $product_ids ) );
		if ( isset( self::$product_contexts[ $cache_key ] ) ) {
			return self::$product_contexts[ $cache_key ];
		}

		$contexts = array();

		foreach ( $product_ids as $product_id ) {
			$product            = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			$category_source_id = $product_id;
			$match_ids          = array( $product_id );

			if ( $product && method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) && method_exists( $product, 'get_parent_id' ) ) {
				$parent_id = absint( $product->get_parent_id() );
				if ( $parent_id > 0 ) {
					$match_ids[]        = $parent_id;
					$category_source_id = $parent_id;
				}
			}

			$contexts[ $product_id ] = array(
				'product_id'         => $product_id,
				'match_ids'          => array_values( array_unique( array_filter( array_map( 'absint', $match_ids ) ) ) ),
				'category_source_id' => absint( $category_source_id ),
			);
		}

		self::$product_contexts[ $cache_key ] = $contexts;

		return $contexts;
	}

	private static function get_cached_product_categories( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return array();
		}

		$cache_key = 'product:' . $product_id;
		if ( isset( self::$product_category_maps[ $cache_key ] ) ) {
			return self::$product_category_maps[ $cache_key ];
		}

		$category_ids = array();
		$terms        = wp_get_object_terms( array( $product_id ), 'product_cat', array( 'fields' => 'all_with_object_id' ) );

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_id = isset( $term->term_id ) ? absint( $term->term_id ) : 0;
				if ( $term_id <= 0 ) {
					continue;
				}

				$category_ids[] = $term_id;

				if ( function_exists( 'get_ancestors' ) ) {
					$ancestors = get_ancestors( $term_id, 'product_cat', 'taxonomy' );
					if ( ! empty( $ancestors ) ) {
						$category_ids = array_merge( $category_ids, array_map( 'absint', $ancestors ) );
					}
				}
			}
		}

		self::$product_category_maps[ $cache_key ] = array_values( array_unique( array_filter( $category_ids ) ) );

		return self::$product_category_maps[ $cache_key ];
	}

	private static function get_product_specific_rules_by_match_id( array $product_contexts, $group_id, $active_only, $include_group_name ) {
		$match_ids = array();
		foreach ( $product_contexts as $context ) {
			$match_ids = array_merge( $match_ids, $context['match_ids'] );
		}

		$match_ids = array_values( array_unique( array_filter( array_map( 'absint', $match_ids ) ) ) );
		if ( empty( $match_ids ) ) {
			return array();
		}

		global $wpdb;
		$pricing_rules_table   = $wpdb->prefix . 'pricing_rules';
		$rule_products_table   = $wpdb->prefix . 'rule_products';
		$customer_groups_table = $wpdb->prefix . 'customer_groups';

		if ( ! self::table_exists( $pricing_rules_table ) || ! self::table_exists( $rule_products_table ) ) {
			return array();
		}

		$group_name_select = '';
		$group_name_join   = '';
		if ( $include_group_name ) {
			if ( self::table_exists( $customer_groups_table ) ) {
				$group_name_select = ', g.group_name';
				$group_name_join   = " JOIN {$customer_groups_table} g ON pr.group_id = g.group_id";
			} else {
				$group_name_select = ", '' AS group_name";
			}
		}

		$where_sql = 'pr.group_id = %d';
		if ( $active_only ) {
			$where_sql .= ' AND pr.is_active = 1 AND (pr.start_date IS NULL OR pr.start_date <= UTC_TIMESTAMP()) AND (pr.end_date IS NULL OR pr.end_date >= UTC_TIMESTAMP())';
		}

		$placeholders = implode( ',', array_fill( 0, count( $match_ids ), '%d' ) );
		$query        = $wpdb->prepare(
			"SELECT pr.*, rp.product_id AS matched_product_id{$group_name_select}
			FROM {$pricing_rules_table} pr
			JOIN {$rule_products_table} rp ON pr.rule_id = rp.rule_id{$group_name_join}
			WHERE {$where_sql} AND rp.product_id IN ({$placeholders})
			ORDER BY pr.sort_order ASC, pr.rule_id ASC",
			array_merge( array( absint( $group_id ) ), $match_ids )
		);
		$rules        = $wpdb->get_results( $query );

		$rules_by_match_id = array();
		if ( is_array( $rules ) ) {
			foreach ( $rules as $rule ) {
				$matched_product_id = isset( $rule->matched_product_id ) ? absint( $rule->matched_product_id ) : 0;
				if ( $matched_product_id <= 0 ) {
					continue;
				}

				if ( ! isset( $rules_by_match_id[ $matched_product_id ] ) ) {
					$rules_by_match_id[ $matched_product_id ] = array();
				}

				$rules_by_match_id[ $matched_product_id ][] = $rule;
			}
		}

		return $rules_by_match_id;
	}

	private static function select_product_specific_rule( array $product_context, array $rules_by_match_id ) {
		foreach ( $product_context['match_ids'] as $match_id ) {
			if ( ! empty( $rules_by_match_id[ $match_id ] ) ) {
				return $rules_by_match_id[ $match_id ][0];
			}
		}

		return null;
	}

	private static function get_category_rules_by_product_id( array $product_ids, array $product_category_map, $group_id, $active_only, $include_group_name ) {
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		if ( empty( $product_ids ) ) {
			return array();
		}

		$all_category_ids = array();
		foreach ( $product_ids as $product_id ) {
			$all_category_ids = array_merge(
				$all_category_ids,
				isset( $product_category_map[ $product_id ] ) ? (array) $product_category_map[ $product_id ] : array()
			);
		}

		$all_category_ids = array_values( array_unique( array_filter( array_map( 'absint', $all_category_ids ) ) ) );
		if ( empty( $all_category_ids ) ) {
			return array();
		}

		global $wpdb;
		$pricing_rules_table   = $wpdb->prefix . 'pricing_rules';
		$rule_categories_table = $wpdb->prefix . 'rule_categories';
		$customer_groups_table = $wpdb->prefix . 'customer_groups';

		if ( ! self::table_exists( $pricing_rules_table ) || ! self::table_exists( $rule_categories_table ) ) {
			return array();
		}

		$group_name_select = '';
		$group_name_join   = '';
		if ( $include_group_name ) {
			if ( self::table_exists( $customer_groups_table ) ) {
				$group_name_select = ', g.group_name';
				$group_name_join   = " JOIN {$customer_groups_table} g ON pr.group_id = g.group_id";
			} else {
				$group_name_select = ", '' AS group_name";
			}
		}

		$where_sql = 'pr.group_id = %d';
		if ( $active_only ) {
			$where_sql .= ' AND pr.is_active = 1 AND (pr.start_date IS NULL OR pr.start_date <= UTC_TIMESTAMP()) AND (pr.end_date IS NULL OR pr.end_date >= UTC_TIMESTAMP())';
		}

		$placeholders = implode( ',', array_fill( 0, count( $all_category_ids ), '%d' ) );
		$query        = $wpdb->prepare(
			"SELECT pr.*, rc.category_id{$group_name_select}
			FROM {$pricing_rules_table} pr
			JOIN {$rule_categories_table} rc ON pr.rule_id = rc.rule_id{$group_name_join}
			WHERE {$where_sql} AND rc.category_id IN ({$placeholders})
			ORDER BY pr.created_at DESC, pr.rule_id DESC",
			array_merge( array( absint( $group_id ) ), $all_category_ids )
		);
		$rules        = $wpdb->get_results( $query );

		$rules_by_category_id = array();
		if ( is_array( $rules ) ) {
			foreach ( $rules as $rule ) {
				$category_id = isset( $rule->category_id ) ? absint( $rule->category_id ) : 0;
				if ( $category_id <= 0 ) {
					continue;
				}

				if ( ! isset( $rules_by_category_id[ $category_id ] ) ) {
					$rules_by_category_id[ $category_id ] = array();
				}

				$rules_by_category_id[ $category_id ][] = $rule;
			}
		}

		$rules_by_product_id = array();
		foreach ( $product_ids as $product_id ) {
			$product_rules = array();
			$category_ids  = isset( $product_category_map[ $product_id ] ) ? (array) $product_category_map[ $product_id ] : array();

			foreach ( $category_ids as $category_id ) {
				$category_id = absint( $category_id );
				if ( ! empty( $rules_by_category_id[ $category_id ] ) ) {
					$product_rules = array_merge( $product_rules, $rules_by_category_id[ $category_id ] );
				}
			}

			$rules_by_product_id[ $product_id ] = $product_rules;
		}

		return $rules_by_product_id;
	}

	private static function determine_best_rule( array $rules ) {
		$best_rule = null;

		foreach ( $rules as $rule ) {
			if ( ! is_object( $rule ) ) {
				continue;
			}

			if ( null === $best_rule ) {
				$best_rule = $rule;
				continue;
			}

			if ( self::compare_discount_rules( $rule, $best_rule ) > 0 ) {
				$best_rule = $rule;
			}
		}

		return $best_rule;
	}

	private static function compare_discount_rules( $rule1, $rule2 ) {
		if ( $rule1->discount_type !== $rule2->discount_type ) {
			return 'fixed' === $rule1->discount_type ? 1 : -1;
		}

		if ( (float) $rule1->discount_value === (float) $rule2->discount_value ) {
			$rule1_created_at = isset( $rule1->created_at ) ? strtotime( (string) $rule1->created_at ) : 0;
			$rule2_created_at = isset( $rule2->created_at ) ? strtotime( (string) $rule2->created_at ) : 0;

			if ( $rule1_created_at === $rule2_created_at ) {
				$rule1_id = isset( $rule1->rule_id ) ? absint( $rule1->rule_id ) : 0;
				$rule2_id = isset( $rule2->rule_id ) ? absint( $rule2->rule_id ) : 0;
				return $rule1_id > $rule2_id ? 1 : -1;
			}

			return $rule1_created_at > $rule2_created_at ? 1 : -1;
		}

		return (float) $rule1->discount_value > (float) $rule2->discount_value ? 1 : -1;
	}
}
