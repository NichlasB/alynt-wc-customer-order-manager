<?php
/**
 * Customer notes AJAX handlers and script enqueuing for AdminPages.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Manages per-customer admin notes stored in user meta.
 *
 * @since 1.0.0
 */
trait AdminPagesNotesTrait {

	/**
	 * Migrate legacy customer_notes meta to the new _awcom_customer_notes array format.
	 *
	 * Runs once per customer: if _awcom_customer_notes already exists the migration is
	 * skipped. Old customer_notes meta is deleted after migration.
	 *
	 * @since 1.0.0
	 *
	 * @param int $customer_id The WordPress user ID.
	 * @return void
	 */
	private function migrate_old_customer_notes( $customer_id ) {
		$new_notes = get_user_meta( $customer_id, '_awcom_customer_notes', true );
		if ( $new_notes && is_array( $new_notes ) && ! empty( $new_notes ) ) {
			return;
		}

		$old_notes = get_user_meta( $customer_id, 'customer_notes', true );
		if ( empty( $old_notes ) ) {
			return;
		}

		$migrated_note = array(
			'content' => wp_unslash( $old_notes ),
			'author'  => 'System Migration',
			'date'    => time(),
		);

		update_user_meta( $customer_id, '_awcom_customer_notes', array( $migrated_note ) );
		delete_user_meta( $customer_id, 'customer_notes' );
	}

	/**
	 * Enqueue the customer notes script and edit-customer stylesheet.
	 *
	 * Only loads on the Edit Customer admin page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_customer_notes_scripts( $hook ) {
		if ( 'admin_page_alynt-wc-customer-order-manager-edit' !== $hook ) {
			return;
		}

		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading current admin page customer ID only. */
		$customer_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		/* phpcs:enable */

		wp_enqueue_style(
			'awcom-edit-customer',
			AWCOM_PLUGIN_URL . 'assets/css/edit-customer.css',
			array( 'dashicons' ),
			AWCOM_VERSION
		);

		wp_enqueue_script(
			'awcom-customer-notes',
			AWCOM_PLUGIN_URL . 'assets/js/customer-notes.js',
			array( 'jquery' ),
			AWCOM_VERSION,
			true
		);

		wp_localize_script(
			'awcom-customer-notes',
			'awcomCustomerNotes',
			array(
				'nonce'       => wp_create_nonce( 'awcom_customer_notes' ),
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'customer_id' => $customer_id,
				'i18n'        => array(
					'confirm_delete'       => __( 'Are you sure you want to delete this note?', 'alynt-wc-customer-order-manager' ),
					'empty_note'           => __( 'Please enter a note.', 'alynt-wc-customer-order-manager' ),
					'edit_label'           => __( 'Edit', 'alynt-wc-customer-order-manager' ),
					'delete_label'         => __( 'Delete', 'alynt-wc-customer-order-manager' ),
					'save_label'           => __( 'Save', 'alynt-wc-customer-order-manager' ),
					'cancel_label'         => __( 'Cancel', 'alynt-wc-customer-order-manager' ),
					'no_notes'             => __( 'No notes found.', 'alynt-wc-customer-order-manager' ),
					/* translators: 1: customer note author, 2: customer note date. */
					'note_meta'            => __( 'By %1$s on %2$s', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: server-provided error details while adding a customer note. */
					'add_error'            => __( 'Error adding note: %s', 'alynt-wc-customer-order-manager' ),
					'add_error_generic'    => __( 'Error adding note. Please try again.', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: server-provided error details while updating a customer note. */
					'update_error'         => __( 'Error updating note: %s', 'alynt-wc-customer-order-manager' ),
					'update_error_generic' => __( 'Error updating note. Please try again.', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: server-provided error details while deleting a customer note. */
					'delete_error'         => __( 'Error deleting note: %s', 'alynt-wc-customer-order-manager' ),
					'delete_error_generic' => __( 'Error deleting note. Please try again.', 'alynt-wc-customer-order-manager' ),
				),
			)
		);
	}

	/**
	 * Handle the AJAX request to add a new customer note.
	 *
	 * Verifies nonce and permissions, creates a note array with content,
	 * author, and timestamp, prepends it to the _awcom_customer_notes user meta,
	 * and returns the formatted note data as JSON.
	 *
	 * @since 1.0.0
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function handle_add_customer_note() {
		check_ajax_referer( 'awcom_customer_notes', 'nonce' );

		if ( ! Security::user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'alynt-wc-customer-order-manager' ) ) );
		}

		$customer_id  = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
		$note_content = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $customer_id || ! $note_content ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'alynt-wc-customer-order-manager' ) ) );
		}

		$current_user = wp_get_current_user();
		$note         = array(
			'content' => $note_content,
			'author'  => $current_user->display_name,
			'date'    => time(),
		);

		$notes = get_user_meta( $customer_id, '_awcom_customer_notes', true );
		if ( ! is_array( $notes ) ) {
			$notes = array();
		}

		array_unshift( $notes, $note );
		update_user_meta( $customer_id, '_awcom_customer_notes', $notes );

		$formatted_date = date_i18n(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$note['date']
		);

		wp_send_json_success(
			array(
				'content' => $note['content'],
				'author'  => $note['author'],
				'date'    => $formatted_date,
			)
		);
	}

	/**
	 * Handle the AJAX request to edit an existing customer note.
	 *
	 * Updates the note content at the given index, marks it as edited,
	 * and records the edit timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function handle_edit_customer_note() {
		check_ajax_referer( 'awcom_customer_notes', 'nonce' );

		if ( ! Security::user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'alynt-wc-customer-order-manager' ) ) );
		}

		$customer_id  = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
		$note_index   = isset( $_POST['note_index'] ) ? intval( $_POST['note_index'] ) : -1;
		$note_content = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $customer_id || $note_index < 0 || ! $note_content ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'alynt-wc-customer-order-manager' ) ) );
		}

		$notes = get_user_meta( $customer_id, '_awcom_customer_notes', true );
		if ( ! is_array( $notes ) ) {
			wp_send_json_error( array( 'message' => __( 'Notes not found.', 'alynt-wc-customer-order-manager' ) ) );
		}

		if ( ! isset( $notes[ $note_index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Note not found.', 'alynt-wc-customer-order-manager' ) ) );
		}

		$notes[ $note_index ]['content']   = $note_content;
		$notes[ $note_index ]['edited']    = true;
		$notes[ $note_index ]['edit_date'] = time();
		update_user_meta( $customer_id, '_awcom_customer_notes', $notes );

		wp_send_json_success( array( 'content' => $note_content ) );
	}

	/**
	 * Handle the AJAX request to delete a customer note.
	 *
	 * Removes the note at the given index, re-indexes the array, and saves.
	 *
	 * @since 1.0.0
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function handle_delete_customer_note() {
		check_ajax_referer( 'awcom_customer_notes', 'nonce' );

		if ( ! Security::user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'alynt-wc-customer-order-manager' ) ) );
		}

		$customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
		$note_index  = isset( $_POST['note_index'] ) ? intval( $_POST['note_index'] ) : -1;

		if ( ! $customer_id || $note_index < 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'alynt-wc-customer-order-manager' ) ) );
		}

		$notes = get_user_meta( $customer_id, '_awcom_customer_notes', true );
		if ( ! is_array( $notes ) ) {
			wp_send_json_error( array( 'message' => __( 'Notes not found.', 'alynt-wc-customer-order-manager' ) ) );
		}

		if ( ! isset( $notes[ $note_index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Note not found.', 'alynt-wc-customer-order-manager' ) ) );
		}

		unset( $notes[ $note_index ] );
		$notes = array_values( $notes );
		update_user_meta( $customer_id, '_awcom_customer_notes', $notes );

		wp_send_json_success();
	}
}
