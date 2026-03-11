<?php
/**
 * Bulk action and customer creation form handlers for AdminPages.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Processes bulk customer deletions and the Add New Customer form.
 *
 * @since 1.0.0
 */
trait AdminPagesActionsCreateBulkTrait {

	/**
	 * Process bulk actions submitted from the customer list table.
	 *
	 * Currently supports the 'delete' action. Verifies nonce and permissions
	 * before deleting selected users, then redirects with a count.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_bulk_actions() {
		if ( ! isset( $_POST['customers'] ) ) {
			return;
		}

		$primary_action   = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		$secondary_action = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '';
		$action           = '';
		if ( ! empty( $primary_action ) && '-1' !== $primary_action ) {
			$action = $primary_action;
		} elseif ( ! empty( $secondary_action ) && '-1' !== $secondary_action ) {
			$action = $secondary_action;
		}

		if ( 'delete' !== $action ) {
			return;
		}

		$bulk_nonce = isset( $_POST['alynt-wc-customer-order-manager-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['alynt-wc-customer-order-manager-nonce'] ) ) : '';
		if ( '' === $bulk_nonce || ! wp_verify_nonce( $bulk_nonce, 'bulk-customers' ) ) {
			$this->redirect_to_admin_page(
				'alynt-wc-customer-order-manager',
				array(
					'error' => rawurlencode( __( 'Your request could not be verified. Please try again.', 'alynt-wc-customer-order-manager' ) ),
				)
			);
		}

		if ( ! Security::user_can_access() ) {
			$this->redirect_to_admin_page(
				'alynt-wc-customer-order-manager',
				array(
					'error' => rawurlencode( __( 'You do not have permission to delete customers.', 'alynt-wc-customer-order-manager' ) ),
				)
			);
		}

		$customer_ids = array_values( array_filter( array_unique( array_map( 'intval', (array) wp_unslash( $_POST['customers'] ) ) ) ) );
		if ( empty( $customer_ids ) ) {
			$this->redirect_to_admin_page(
				'alynt-wc-customer-order-manager',
				array(
					'error' => rawurlencode( __( 'Select at least one customer to delete.', 'alynt-wc-customer-order-manager' ) ),
				)
			);
		}

		$deleted_count = 0;
		$failed_count  = 0;

		foreach ( $customer_ids as $customer_id ) {
			if ( $customer_id <= 0 ) {
				++$failed_count;
				continue;
			}

			$deleted = wp_delete_user( $customer_id );
			if ( $deleted ) {
				++$deleted_count;
			} else {
				++$failed_count;
				$this->log_admin_error( sprintf( 'Failed to delete customer #%d during bulk action.', $customer_id ) );
			}
		}

		$redirect_args = array();
		if ( $deleted_count > 0 ) {
			$redirect_args['deleted'] = $deleted_count;
		}

		if ( $failed_count > 0 ) {
			$redirect_args['delete_failed'] = $failed_count;
		}

		if ( 0 === $deleted_count && 0 === $failed_count ) {
			$redirect_args['error'] = rawurlencode( __( 'No customers were deleted.', 'alynt-wc-customer-order-manager' ) );
		}

		$this->redirect_to_admin_page( 'alynt-wc-customer-order-manager', $redirect_args );
	}

	/**
	 * Process the Add New Customer form submission.
	 *
	 * Validates required fields, checks for duplicate email addresses, creates
	 * the WordPress user with the customer role, saves billing meta fields, and
	 * optionally assigns a customer group. Redirects to the edit page on success.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_customer_form_submission() {
		$request_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( 'awcom_create_customer' !== $request_action ) {
			return;
		}

		$customer_nonce = isset( $_POST['awcom_customer_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['awcom_customer_nonce'] ) ) : '';
		if ( '' === $customer_nonce || ! wp_verify_nonce( $customer_nonce, 'create_customer' ) ) {
			$this->redirect_to_add_customer_with_error(
				__( 'Your request could not be verified. Please try again.', 'alynt-wc-customer-order-manager' )
			);
		}

		$form_data = $this->get_add_customer_form_data_from_request();

		if ( ! Security::user_can_access() ) {
			$this->redirect_to_add_customer_with_error(
				__( 'You do not have permission to create customers.', 'alynt-wc-customer-order-manager' ),
				$form_data
			);
		}

		if ( empty( $form_data['first_name'] ) || empty( $form_data['last_name'] ) || empty( $form_data['email'] ) ) {
			$this->redirect_to_add_customer_with_error(
				__( 'Please fill in all required fields.', 'alynt-wc-customer-order-manager' ),
				$form_data
			);
		}

		if ( email_exists( $form_data['email'] ) ) {
			$this->redirect_to_add_customer_with_error(
				__( 'This email address is already registered.', 'alynt-wc-customer-order-manager' ),
				$form_data
			);
		}

		$user_data = array(
			'user_login' => $form_data['email'],
			'user_email' => $form_data['email'],
			'first_name' => $form_data['first_name'],
			'last_name'  => $form_data['last_name'],
			'role'       => 'customer',
			'user_pass'  => wp_generate_password(),
		);

		$user_id = wp_insert_user( $user_data );
		if ( is_wp_error( $user_id ) ) {
			$this->log_admin_error(
				sprintf(
					'Failed to create customer account for %s: %s',
					$form_data['email'],
					$user_id->get_error_message()
				)
			);

			$this->redirect_to_add_customer_with_error(
				__( 'Could not create the customer account. Please review the details and try again.', 'alynt-wc-customer-order-manager' ),
				$form_data
			);
		}

		$billing_fields = array(
			'billing_email',
			'billing_company',
			'billing_phone',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
		);

		foreach ( $billing_fields as $field ) {
			update_user_meta( $user_id, $field, $form_data[ $field ] );
		}

		$group_warning = '';
		if ( $form_data['customer_group'] > 0 ) {
			global $wpdb;
			$user_groups_table = $wpdb->prefix . 'user_groups';
			$group_id          = absint( $form_data['customer_group'] );

			if ( ! PricingRuleLookup::customer_group_exists( $group_id ) ) {
				$group_warning = __( 'Customer created, but the selected customer group no longer exists.', 'alynt-wc-customer-order-manager' );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin form submission updates a plugin-managed relationship table.
			if ( empty( $group_warning ) ) {
				$inserted = $wpdb->insert(
					$user_groups_table,
					array(
						'user_id'  => $user_id,
						'group_id' => $group_id,
					),
					array( '%d', '%d' )
				);

				if ( false === $inserted ) {
					$group_warning = __( 'Customer created, but the customer group could not be assigned. Please try again.', 'alynt-wc-customer-order-manager' );
					$this->log_admin_error(
						sprintf(
							'Failed to assign group #%1$d to customer #%2$d: %3$s',
							$group_id,
							$user_id,
							$wpdb->last_error
						)
					);
				}
			}
		}

		$redirect_args = array(
			'id'      => $user_id,
			'created' => '1',
		);

		if ( ! empty( $group_warning ) ) {
			$redirect_args['warning'] = rawurlencode( $group_warning );
		}

		$this->redirect_to_admin_page( 'alynt-wc-customer-order-manager-edit', $redirect_args );
	}

	/**
	 * Build the add-customer form state from the current POST request.
	 *
	 * @since 1.0.6
	 *
	 * @return array
	 */
	private function get_add_customer_form_data_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Called only after an authenticated admin-post nonce check.
		$text_fields = array(
			'first_name',
			'last_name',
			'billing_company',
			'billing_phone',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
		);

		$form_data = array(
			'customer_group' => isset( $_POST['customer_group'] ) ? absint( wp_unslash( $_POST['customer_group'] ) ) : 0,
			'email'          => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'billing_email'  => isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '',
		);

		foreach ( $text_fields as $field ) {
			$form_data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		// phpcs:enable

		return $form_data;
	}

	/**
	 * Redirect back to the Add Customer screen and keep submitted form values.
	 *
	 * @since 1.0.6
	 *
	 * @param string $message   User-facing error message.
	 * @param array  $form_data Submitted form values.
	 * @return void
	 */
	private function redirect_to_add_customer_with_error( $message, array $form_data = array() ) {
		if ( ! empty( $form_data ) ) {
			$this->store_form_state( 'awcom_add_customer_form', $form_data );
		}

		$this->redirect_to_admin_page(
			'alynt-wc-customer-order-manager-add',
			array(
				'error' => rawurlencode( $message ),
			)
		);
	}
}
