<?php

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

class PricingRuleLookup {
	private static $table_exists = array();
	private static $customer_group_ids = array();
	private static $product_category_maps = array();
	private static $rule_lookups = array();
	private static $group_display_titles = array();

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

		$product_category_map = array_fill_keys( $product_ids, array() );
		$terms                = wp_get_object_terms( $product_ids, 'product_cat', array( 'fields' => 'all_with_object_id' ) );

		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$product_id = isset( $term->object_id ) ? absint( $term->object_id ) : 0;
				$term_id    = isset( $term->term_id ) ? absint( $term->term_id ) : 0;

				if ( $product_id > 0 && $term_id > 0 ) {
					if ( ! isset( $product_category_map[ $product_id ] ) ) {
						$product_category_map[ $product_id ] = array();
					}

					$product_category_map[ $product_id ][] = $term_id;
				}
			}
		}

		foreach ( $product_category_map as $product_id => $category_ids ) {
			$product_category_map[ $product_id ] = array_values( array_unique( array_map( 'absint', $category_ids ) ) );
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
			);
		}

		$category_ids = array();
		foreach ( $product_category_map as $mapped_category_ids ) {
			foreach ( (array) $mapped_category_ids as $category_id ) {
				$category_ids[] = absint( $category_id );
			}
		}
		$category_ids = array_values( array_unique( array_filter( $category_ids ) ) );
		sort( $category_ids );

		$cache_key = md5(
			wp_json_encode(
				array(
					$group_id,
					$product_ids,
					$category_ids,
					(bool) $active_only,
					(bool) $include_group_name,
				)
			)
		);

		if ( isset( self::$rule_lookups[ $cache_key ] ) ) {
			return self::$rule_lookups[ $cache_key ];
		}

		global $wpdb;
		$pricing_rules_table  = $wpdb->prefix . 'pricing_rules';
		$rule_products_table  = $wpdb->prefix . 'rule_products';
		$rule_categories_table = $wpdb->prefix . 'rule_categories';
		$customer_groups_table = $wpdb->prefix . 'customer_groups';

		$lookup = array(
			'product_rules'        => array(),
			'category_rules'       => array(),
			'product_category_map' => $product_category_map,
		);

		if ( ! self::table_exists( $pricing_rules_table ) || ! self::table_exists( $rule_products_table ) || ! self::table_exists( $rule_categories_table ) ) {
			self::$rule_lookups[ $cache_key ] = $lookup;
			return $lookup;
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

		$product_placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$product_query        = $wpdb->prepare(
			"SELECT pr.*, rp.product_id{$group_name_select}
			FROM {$pricing_rules_table} pr
			JOIN {$rule_products_table} rp ON pr.rule_id = rp.rule_id{$group_name_join}
			WHERE {$where_sql} AND rp.product_id IN ({$product_placeholders})
			ORDER BY rp.product_id ASC, pr.created_at DESC, pr.rule_id DESC",
			array_merge( array( $group_id ), $product_ids )
		);
		$product_rows         = $wpdb->get_results( $product_query );

		if ( is_array( $product_rows ) ) {
			foreach ( $product_rows as $product_row ) {
				$product_id = isset( $product_row->product_id ) ? absint( $product_row->product_id ) : 0;
				if ( $product_id > 0 && ! isset( $lookup['product_rules'][ $product_id ] ) ) {
					$lookup['product_rules'][ $product_id ] = $product_row;
				}
			}
		}

		if ( ! empty( $category_ids ) ) {
			$category_placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
			$category_query        = $wpdb->prepare(
				"SELECT pr.*, rc.category_id{$group_name_select}
				FROM {$pricing_rules_table} pr
				JOIN {$rule_categories_table} rc ON pr.rule_id = rc.rule_id{$group_name_join}
				WHERE {$where_sql} AND rc.category_id IN ({$category_placeholders})
				ORDER BY rc.category_id ASC, pr.created_at DESC, pr.rule_id DESC",
				array_merge( array( $group_id ), $category_ids )
			);
			$category_rows         = $wpdb->get_results( $category_query );

			if ( is_array( $category_rows ) ) {
				foreach ( $category_rows as $category_row ) {
					$category_id = isset( $category_row->category_id ) ? absint( $category_row->category_id ) : 0;
					if ( $category_id > 0 && ! isset( $lookup['category_rules'][ $category_id ] ) ) {
						$lookup['category_rules'][ $category_id ] = $category_row;
					}
				}
			}
		}

		self::$rule_lookups[ $cache_key ] = $lookup;

		return $lookup;
	}

	public static function get_matching_rule( $product_id, array $rule_lookup ) {
		$product_id = absint( $product_id );

		if ( isset( $rule_lookup['product_rules'][ $product_id ] ) ) {
			return $rule_lookup['product_rules'][ $product_id ];
		}

		$matching_rule = null;
		$category_ids  = isset( $rule_lookup['product_category_map'][ $product_id ] ) ? (array) $rule_lookup['product_category_map'][ $product_id ] : array();

		foreach ( $category_ids as $category_id ) {
			$category_id = absint( $category_id );
			if ( $category_id <= 0 || ! isset( $rule_lookup['category_rules'][ $category_id ] ) ) {
				continue;
			}

			$category_rule = $rule_lookup['category_rules'][ $category_id ];
			if ( null === $matching_rule ) {
				$matching_rule = $category_rule;
				continue;
			}

			$current_created_at = isset( $category_rule->created_at ) ? (string) $category_rule->created_at : '';
			$best_created_at    = isset( $matching_rule->created_at ) ? (string) $matching_rule->created_at : '';

			if ( $current_created_at > $best_created_at ) {
				$matching_rule = $category_rule;
				continue;
			}

			if ( $current_created_at === $best_created_at && isset( $category_rule->rule_id, $matching_rule->rule_id ) && (int) $category_rule->rule_id > (int) $matching_rule->rule_id ) {
				$matching_rule = $category_rule;
			}
		}

		return $matching_rule;
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

			if ( $default_group_id > 0 && self::table_exists( $pricing_rules_table ) ) {
				$has_active_rule = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT 1 FROM {$pricing_rules_table} WHERE group_id = %d AND is_active = 1 LIMIT 1",
						$default_group_id
					)
				);

				if ( $has_active_rule ) {
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
}
