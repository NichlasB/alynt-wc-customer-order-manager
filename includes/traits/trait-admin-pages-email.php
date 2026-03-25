<?php
/**
 * Email template management for AdminPages.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handles saving and using the login-details email template.
 *
 * @since 1.0.0
 */
trait AdminPagesEmailTrait {

	/**
	 * Return the HTML MIME type for use with wp_mail_content_type filter.
	 *
	 * Added and removed inline around wp_mail() calls to avoid affecting
	 * other emails sent during the same request.
	 *
	 * @since 1.0.0
	 *
	 * @return string Always 'text/html'.
	 */
	public function set_html_content_type() {
		return 'text/html';
	}

	/**
	 * Enqueue the wp_editor and email template scripts on the Edit Customer page.
	 *
	 * Passes available merge tags ({customer_first_name}, {password_reset_link})
	 * to JavaScript via wp_localize_script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_editor_scripts( $hook ) {
		if ( 'admin_page_alynt-wc-customer-order-manager-edit' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_editor();

		wp_enqueue_script(
			'awcom-email-template',
			AWCOM_PLUGIN_URL . 'assets/js/email-template.js',
			array( 'jquery', 'jquery-ui-dialog' ),
			AWCOM_VERSION,
			true
		);

		wp_localize_script(
			'awcom-email-template',
			'awcomEmailVars',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'awcom_email_template' ),
				'mergeTags' => array(
					'{customer_first_name}' => __( 'Customer First Name', 'alynt-wc-customer-order-manager' ),
					'{password_reset_link}' => __( 'Password Reset Link', 'alynt-wc-customer-order-manager' ),
				),
				'i18n'      => array(
					'dialog_title'     => __( 'Edit Email Template', 'alynt-wc-customer-order-manager' ),
					'empty_content'    => __( 'Please enter some content for the email template.', 'alynt-wc-customer-order-manager' ),
					'save_success'     => __( 'Template saved successfully.', 'alynt-wc-customer-order-manager' ),
					'unknown_error'    => __( 'Something unexpected happened while saving the template. Please try again.', 'alynt-wc-customer-order-manager' ),
					'server_error'     => __( 'The email template could not be saved. Please try again.', 'alynt-wc-customer-order-manager' ),
					'saving'           => __( 'Saving...', 'alynt-wc-customer-order-manager' ),
					'discard_title'    => __( 'Discard Changes?', 'alynt-wc-customer-order-manager' ),
					'discard_action'   => __( 'Discard Changes', 'alynt-wc-customer-order-manager' ),
					'cancel_label'     => __( 'Cancel', 'alynt-wc-customer-order-manager' ),
					'unsaved_changes'  => __( 'Discard your unsaved email template changes?', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: error message returned while saving the email template. */
					'save_error'       => __( 'The email template could not be saved. %s', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: error message thrown while preparing the email template request. */
					'prepare_error'    => __( 'The email template could not be prepared for saving. %s', 'alynt-wc-customer-order-manager' ),
					'insert_merge_tag' => __( 'Insert Merge Tag...', 'alynt-wc-customer-order-manager' ),
				),
			)
		);
	}

	/**
	 * Handle the AJAX request to save the login-details email template.
	 *
	 * Verifies nonce, checks manage_woocommerce capability, sanitizes the
	 * template HTML with wp_kses_post, and stores it in the
	 * awcom_login_email_template option.
	 *
	 * @since 1.0.0
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function save_login_email_template() {
		if ( false === check_ajax_referer( 'awcom_email_template', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Refresh the page and try again.', 'alynt-wc-customer-order-manager' ) ), 403 );
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this email template.', 'alynt-wc-customer-order-manager' ) ), 403 );
		}

		if ( ! isset( $_POST['template'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The email template could not be saved because no content was received. Refresh the page and try again.', 'alynt-wc-customer-order-manager' ) ), 400 );
		}

		$template         = wp_kses_post( wp_unslash( $_POST['template'] ) );
		$current_template = (string) get_option( 'awcom_login_email_template', '' );
		$updated          = ( $template === $current_template ) ? true : update_option( 'awcom_login_email_template', $template, false );

		if ( $updated ) {
			wp_send_json_success( __( 'Template saved successfully.', 'alynt-wc-customer-order-manager' ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'The email template could not be saved. Please try again.', 'alynt-wc-customer-order-manager' ) ), 500 );
		}
	}
}
