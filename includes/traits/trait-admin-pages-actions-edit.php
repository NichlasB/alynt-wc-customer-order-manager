<?php
/**
 * Customer edit and login-details email form handlers for AdminPages.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handles customer profile updates and login-details email dispatch.
 *
 * @since 1.0.0
 */
trait AdminPagesActionsEditTrait {

	/**
	 * Process the Edit Customer form and the Send Login Details action.
	 *
	 * Handles two admin-post actions via the action field:
	 * - awcom_edit_customer: updates core user data, billing/shipping meta, and group.
	 * - awcom_send_login_details: generates a password reset key and emails the customer.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_customer_edit_submission() {
		if ( ! isset( $_POST['action'] ) ) {
			return;
		}

		$request_action = sanitize_key( wp_unslash( $_POST['action'] ) );

		if ( 'awcom_edit_customer' === $request_action ) {
			$customer_id    = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
			$customer_nonce = isset( $_POST['awcom_customer_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['awcom_customer_nonce'] ) ) : '';

			if ( '' === $customer_nonce || ! wp_verify_nonce( $customer_nonce, 'edit_customer' ) ) {
				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'Your request could not be verified. Please try again.', 'alynt-wc-customer-order-manager' )
				);
			}

			$form_data = $this->get_edit_customer_form_data_from_request();

			if ( ! Security::user_can_access() ) {
				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'You do not have permission to update this customer.', 'alynt-wc-customer-order-manager' ),
					$form_data
				);
			}

			if ( $customer_id <= 0 ) {
				$this->redirect_to_admin_page(
					'alynt-wc-customer-order-manager',
					array(
						'error' => rawurlencode( __( 'Invalid customer.', 'alynt-wc-customer-order-manager' ) ),
					)
				);
			}

			$customer = get_user_by( 'id', $customer_id );
			if ( ! $customer || ! in_array( 'customer', (array) $customer->roles, true ) ) {
				$this->redirect_to_admin_page(
					'alynt-wc-customer-order-manager',
					array(
						'error' => rawurlencode( __( 'Invalid customer.', 'alynt-wc-customer-order-manager' ) ),
					)
				);
			}

			if ( empty( $form_data['first_name'] ) || empty( $form_data['last_name'] ) || empty( $form_data['email'] ) ) {
				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'Please fill in all required fields.', 'alynt-wc-customer-order-manager' ),
					$form_data
				);
			}

			$existing_user = get_user_by( 'email', $form_data['email'] );
			if ( $existing_user && (int) $existing_user->ID !== $customer_id ) {
				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'This email address is already registered to another user.', 'alynt-wc-customer-order-manager' ),
					$form_data
				);
			}

			$user_data = array(
				'ID'         => $customer_id,
				'user_email' => $form_data['email'],
				'first_name' => $form_data['first_name'],
				'last_name'  => $form_data['last_name'],
			);

			$user_id = wp_update_user( $user_data );
			if ( is_wp_error( $user_id ) ) {
				$this->log_admin_error(
					sprintf(
						'Failed to update customer #%1$d: %2$s',
						$customer_id,
						$user_id->get_error_message()
					)
				);

				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'Could not update the customer. Please review the details and try again.', 'alynt-wc-customer-order-manager' ),
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
				update_user_meta( $customer_id, $field, $form_data[ $field ] );
			}

			if ( '1' === $form_data['same_as_billing'] ) {
				$shipping_fields = array(
					'shipping_phone'     => 'billing_phone',
					'shipping_address_1' => 'billing_address_1',
					'shipping_address_2' => 'billing_address_2',
					'shipping_city'      => 'billing_city',
					'shipping_state'     => 'billing_state',
					'shipping_postcode'  => 'billing_postcode',
					'shipping_country'   => 'billing_country',
				);

				foreach ( $shipping_fields as $shipping_field => $billing_field ) {
					update_user_meta( $customer_id, $shipping_field, $form_data[ $billing_field ] );
				}
			} else {
				$shipping_fields = array(
					'shipping_address_1',
					'shipping_address_2',
					'shipping_phone',
					'shipping_city',
					'shipping_state',
					'shipping_postcode',
					'shipping_country',
				);

				foreach ( $shipping_fields as $field ) {
					update_user_meta( $customer_id, $field, $form_data[ $field ] );
				}
			}

			$group_warning = '';
			if ( array_key_exists( 'customer_group', $form_data ) ) {
				global $wpdb;
				$user_groups_table = $wpdb->prefix . 'user_groups';
				$group_id          = $form_data['customer_group'];

				if ( $group_id > 0 && ! PricingRuleLookup::customer_group_exists( $group_id ) ) {
					$group_warning = __( 'Customer details were updated, but the selected customer group no longer exists.', 'alynt-wc-customer-order-manager' );
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin form submission updates a plugin-managed relationship table.
				if ( empty( $group_warning ) ) {
					$deleted = $wpdb->delete(
						$user_groups_table,
						array( 'user_id' => $customer_id ),
						array( '%d' )
					);

					if ( false === $deleted && ! empty( $wpdb->last_error ) ) {
						$group_warning = __( 'Customer details were updated, but the customer group could not be saved. Please try again.', 'alynt-wc-customer-order-manager' );
						$this->log_admin_error(
							sprintf(
								'Failed clearing existing group for customer #%1$d: %2$s',
								$customer_id,
								$wpdb->last_error
							)
						);
					}

					if ( $group_id > 0 && empty( $group_warning ) ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin form submission updates a plugin-managed relationship table.
						$inserted = $wpdb->insert(
							$user_groups_table,
							array(
								'user_id'  => $customer_id,
								'group_id' => $group_id,
							),
							array( '%d', '%d' )
						);

						if ( false === $inserted ) {
							$group_warning = __( 'Customer details were updated, but the customer group could not be saved. Please try again.', 'alynt-wc-customer-order-manager' );
							$this->log_admin_error(
								sprintf(
									'Failed assigning group #%1$d to customer #%2$d: %3$s',
									$group_id,
									$customer_id,
									$wpdb->last_error
								)
							);
						}
					}
				}
			}

			$redirect_args = array(
				'id'      => $customer_id,
				'updated' => '1',
			);

			if ( ! empty( $group_warning ) ) {
				$redirect_args['warning'] = rawurlencode( $group_warning );
			}

			$this->redirect_to_admin_page( 'alynt-wc-customer-order-manager-edit', $redirect_args );
		}

		if ( 'awcom_send_login_details' === $request_action ) {
			$customer_id      = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
			$send_login_nonce = isset( $_POST['send_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['send_login_nonce'] ) ) : '';

			if ( '' === $send_login_nonce || ! wp_verify_nonce( $send_login_nonce, 'send_login_details' ) ) {
				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'Your request could not be verified. Please try again.', 'alynt-wc-customer-order-manager' )
				);
			}

			if ( ! Security::user_can_access() ) {
				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'You do not have permission to send login details.', 'alynt-wc-customer-order-manager' )
				);
			}

			$user = get_user_by( 'id', $customer_id );

			if ( ! $user || ! in_array( 'customer', (array) $user->roles, true ) ) {
				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'Invalid customer.', 'alynt-wc-customer-order-manager' )
				);
			}

			if ( empty( $user->user_email ) ) {
				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'This customer does not have an email address saved.', 'alynt-wc-customer-order-manager' )
				);
			}

			$key = get_password_reset_key( $user );
			if ( is_wp_error( $key ) ) {
				$this->log_admin_error(
					sprintf(
						'Could not generate password reset key for customer #%1$d: %2$s',
						$customer_id,
						$key->get_error_message()
					)
				);

				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'Could not prepare the login email. Please try again.', 'alynt-wc-customer-order-manager' )
				);
			}

			$reset_link = network_site_url(
				"wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ),
				'login'
			);

			$to      = $user->user_email;
			$subject = sprintf(
				/* translators: %s: site name. */
				__( '[%s] Your login details', 'alynt-wc-customer-order-manager' ),
				get_bloginfo( 'name' )
			);

			$template = get_option( 'awcom_login_email_template', '' );
			if ( ! empty( $template ) ) {
				$message = str_replace(
					array( '{customer_first_name}', '{password_reset_link}' ),
					array( $user->first_name, '<a href="' . esc_url( $reset_link ) . '">' . esc_url( $reset_link ) . '</a>' ),
					$template
				);
			} else {
				$message = sprintf(
					/* translators: 1: customer display name, 2: password reset URL, 3: site name. */
					__(
						'Hello %1$s,<br><br>
                        You can set your password and login to your account by visiting the following address:<br><br>
                        <a href="%2$s">%2$s</a><br><br>
                        This link will expire in 24 hours.<br><br>
                        Regards,<br>
                        %3$s',
						'alynt-wc-customer-order-manager'
					),
					$user->display_name,
					esc_url( $reset_link ),
					get_bloginfo( 'name' )
				);
			}

			$message = '<!DOCTYPE html><html><body>' . wpautop( $message ) . '</body></html>';

			add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
			try {
				$mail_sent = wp_mail( $to, $subject, $message );
			} finally {
				remove_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
			}

			if ( ! $mail_sent ) {
				$this->log_admin_error(
					sprintf(
						'wp_mail returned false while sending login details to customer #%1$d (%2$s).',
						$customer_id,
						$to
					)
				);

				$this->redirect_to_edit_customer_with_error(
					$customer_id,
					__( 'The login email could not be sent. Please try again.', 'alynt-wc-customer-order-manager' )
				);
			}

			$this->redirect_to_admin_page(
				'alynt-wc-customer-order-manager-edit',
				array(
					'id'         => $customer_id,
					'email_sent' => '1',
				)
			);
		}
	}

	/**
	 * Build the edit-customer form state from the current POST request.
	 *
	 * @since 1.0.6
	 *
	 * @return array
	 */
	private function get_edit_customer_form_data_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Called only after an authenticated admin-post nonce check.
		$text_fields = array(
			'first_name',
			'last_name',
			'billing_email',
			'billing_company',
			'billing_phone',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_phone',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
			'shipping_country',
		);

		$form_data = array(
			'customer_id'     => isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0,
			'customer_group'  => isset( $_POST['customer_group'] ) ? absint( wp_unslash( $_POST['customer_group'] ) ) : 0,
			'same_as_billing' => isset( $_POST['same_as_billing'] ) ? '1' : '0',
			'email'           => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		);

		foreach ( $text_fields as $field ) {
			if ( 'billing_email' === $field ) {
				$form_data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_email( wp_unslash( $_POST[ $field ] ) ) : '';
				continue;
			}

			$form_data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		// phpcs:enable

		return $form_data;
	}

	/**
	 * Redirect back to the Edit Customer screen and restore submitted values.
	 *
	 * @since 1.0.6
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $message     User-facing error message.
	 * @param array  $form_data   Submitted form values.
	 * @return void
	 */
	private function redirect_to_edit_customer_with_error( $customer_id, $message, array $form_data = array() ) {
		if ( $customer_id > 0 && ! empty( $form_data ) ) {
			$this->store_form_state( 'awcom_edit_customer_form_' . $customer_id, $form_data );
		}

		if ( $customer_id > 0 ) {
			$this->redirect_to_admin_page(
				'alynt-wc-customer-order-manager-edit',
				array(
					'id'    => $customer_id,
					'error' => rawurlencode( $message ),
				)
			);
		}

		$this->redirect_to_admin_page(
			'alynt-wc-customer-order-manager',
			array(
				'error' => rawurlencode( $message ),
			)
		);
	}
}
