<?php
/**
 * Edit customer page rendering for AdminPages.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the customer edit admin page via partial templates.
 *
 * @since 1.0.0
 */
trait AdminPagesRenderEditTrait {

	/**
	 * Render the Edit Customer admin page.
	 *
	 * Validates the customer_id query parameter, loads the customer,
	 * and includes the page.php partial template from admin/partials/customer-edit/.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_edit_page() {
		if ( ! Security::user_can_access() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'alynt-wc-customer-order-manager' ) );
		}

		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading routing query args only. */
		$customer_id  = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$updated_flag = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '';
		/* phpcs:enable */
		if ( ! $customer_id ) {
			wp_die( esc_html__( 'Invalid customer ID.', 'alynt-wc-customer-order-manager' ) );
		}

		$customer = get_user_by( 'id', $customer_id );
		if ( ! $customer || ! in_array( 'customer', (array) $customer->roles, true ) ) {
			wp_die( esc_html__( 'Invalid customer.', 'alynt-wc-customer-order-manager' ) );
		}

		$current_group_name = '';
		$current_group_id   = 0;
		global $wpdb;
		$groups_table      = $wpdb->prefix . 'customer_groups';
		$user_groups_table = $wpdb->prefix . 'user_groups';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only table existence check.
		$groups_table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $groups_table )
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only table existence check.
		$user_groups_table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $user_groups_table )
			)
		);

		if ( $groups_table_exists === $groups_table && $user_groups_table_exists === $user_groups_table ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic table names built from $wpdb->prefix.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic table name built from $wpdb->prefix.
			$current_group_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT group_id FROM $user_groups_table WHERE user_id = %d",
					$customer_id
				)
			);
			// phpcs:enable

			if ( '1' === $updated_flag && $current_group_id > 0 ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic table names built from $wpdb->prefix.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic table names built from $wpdb->prefix.
				$current_group_name = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT g.group_name
                    FROM $groups_table g
                    JOIN $user_groups_table ug ON g.group_id = ug.group_id
                    WHERE ug.user_id = %d",
						$customer_id
					)
				);
				// phpcs:enable
			}
		}

		$form_values = array_merge(
			array(
				'customer_group'     => $current_group_id,
				'first_name'         => $customer->first_name,
				'last_name'          => $customer->last_name,
				'email'              => $customer->user_email,
				'billing_email'      => get_user_meta( $customer_id, 'billing_email', true ),
				'billing_company'    => get_user_meta( $customer_id, 'billing_company', true ),
				'billing_phone'      => get_user_meta( $customer_id, 'billing_phone', true ),
				'billing_address_1'  => get_user_meta( $customer_id, 'billing_address_1', true ),
				'billing_address_2'  => get_user_meta( $customer_id, 'billing_address_2', true ),
				'billing_city'       => get_user_meta( $customer_id, 'billing_city', true ),
				'billing_state'      => get_user_meta( $customer_id, 'billing_state', true ),
				'billing_postcode'   => get_user_meta( $customer_id, 'billing_postcode', true ),
				'billing_country'    => get_user_meta( $customer_id, 'billing_country', true ),
				'same_as_billing'    => empty( get_user_meta( $customer_id, 'shipping_address_1', true ) ) ? '1' : '0',
				'shipping_address_1' => get_user_meta( $customer_id, 'shipping_address_1', true ),
				'shipping_address_2' => get_user_meta( $customer_id, 'shipping_address_2', true ),
				'shipping_phone'     => get_user_meta( $customer_id, 'shipping_phone', true ),
				'shipping_city'      => get_user_meta( $customer_id, 'shipping_city', true ),
				'shipping_state'     => get_user_meta( $customer_id, 'shipping_state', true ),
				'shipping_postcode'  => get_user_meta( $customer_id, 'shipping_postcode', true ),
				'shipping_country'   => get_user_meta( $customer_id, 'shipping_country', true ),
				'validation_errors'  => array(),
			),
			$this->pull_form_state( 'awcom_edit_customer_form_' . $customer_id )
		);

		$partial_base = AWCOM_PLUGIN_PATH . 'admin/partials/customer-edit/';
		$page_partial = $partial_base . 'page.php';

		if ( ! file_exists( $page_partial ) ) {
			wp_die( esc_html__( 'Customer edit template not found.', 'alynt-wc-customer-order-manager' ) );
		}

		include $page_partial;
	}
}
