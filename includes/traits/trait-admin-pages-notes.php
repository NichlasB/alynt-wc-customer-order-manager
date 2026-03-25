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

	private function send_notes_ajax_error( $message, $status = 400 ) {
		wp_send_json_error( array( 'message' => $message ), $status );
	}

	private function get_valid_notes_customer( $customer_id ) {
		$customer_id = absint( $customer_id );

		if ( $customer_id <= 0 ) {
			return false;
		}

		$customer = get_user_by( 'id', $customer_id );

		if ( ! $customer || ! in_array( 'customer', (array) $customer->roles, true ) ) {
			return false;
		}

		return $customer;
	}

	private function validate_notes_ajax_request() {
		if ( false === check_ajax_referer( 'awcom_customer_notes', 'nonce', false ) ) {
			$this->send_notes_ajax_error( __( 'Your session expired. Refresh the page and try again.', 'alynt-wc-customer-order-manager' ), 403 );
		}

		if ( ! Security::user_can_access() ) {
			$this->send_notes_ajax_error( __( 'You do not have permission to manage customer notes.', 'alynt-wc-customer-order-manager' ), 403 );
		}
	}

	private function get_customer_notes( $customer_id ) {
		$stored_notes = get_user_meta( $customer_id, '_awcom_customer_notes', true );

		if ( ! is_array( $stored_notes ) ) {
			return array();
		}

		$normalized_notes = array();
		$notes_changed    = false;

		foreach ( $stored_notes as $stored_note ) {
			if ( ! is_array( $stored_note ) ) {
				$notes_changed = true;
				continue;
			}

			$note_content = isset( $stored_note['content'] ) ? sanitize_textarea_field( wp_unslash( (string) $stored_note['content'] ) ) : '';

			if ( '' === $note_content ) {
				$notes_changed = true;
				continue;
			}

			$note_id = isset( $stored_note['id'] ) ? sanitize_text_field( wp_unslash( (string) $stored_note['id'] ) ) : '';

			if ( '' === $note_id ) {
				$note_id       = wp_generate_uuid4();
				$notes_changed = true;
			}

			$note_author = isset( $stored_note['author'] ) ? sanitize_text_field( wp_unslash( (string) $stored_note['author'] ) ) : '';

			if ( '' === $note_author ) {
				$note_author   = __( 'Unknown', 'alynt-wc-customer-order-manager' );
				$notes_changed = true;
			}

			$note_date = isset( $stored_note['date'] ) && is_numeric( $stored_note['date'] ) ? (int) $stored_note['date'] : time();

			if ( ! isset( $stored_note['date'] ) || ! is_numeric( $stored_note['date'] ) ) {
				$notes_changed = true;
			}

			$normalized_note = array(
				'id'      => $note_id,
				'content' => $note_content,
				'author'  => $note_author,
				'date'    => $note_date,
			);

			if ( ! empty( $stored_note['edited'] ) ) {
				$normalized_note['edited'] = true;
			}

			if ( isset( $stored_note['edit_date'] ) && is_numeric( $stored_note['edit_date'] ) ) {
				$normalized_note['edit_date'] = (int) $stored_note['edit_date'];
			}

			$normalized_notes[] = $normalized_note;
		}

		if ( $notes_changed ) {
			update_user_meta( $customer_id, '_awcom_customer_notes', $normalized_notes );
		}

		return $normalized_notes;
	}

	private function find_customer_note_index( array $notes, $note_id ) {
		$note_id = sanitize_text_field( (string) $note_id );

		if ( '' === $note_id ) {
			return -1;
		}

		foreach ( $notes as $index => $note ) {
			if ( isset( $note['id'] ) && $note_id === $note['id'] ) {
				return (int) $index;
			}
		}

		return -1;
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
					'delete_note_title'    => __( 'Delete Note?', 'alynt-wc-customer-order-manager' ),
					'delete_note_action'   => __( 'Delete Note', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: note preview text. */
					'delete_note_message'  => __( 'This will permanently delete this note: "%s". This cannot be undone.', 'alynt-wc-customer-order-manager' ),
					'empty_note'           => __( 'Enter a note before you save it.', 'alynt-wc-customer-order-manager' ),
					'edit_label'           => __( 'Edit', 'alynt-wc-customer-order-manager' ),
					'delete_label'         => __( 'Delete', 'alynt-wc-customer-order-manager' ),
					'save_label'           => __( 'Save', 'alynt-wc-customer-order-manager' ),
					'cancel_label'         => __( 'Cancel', 'alynt-wc-customer-order-manager' ),
					'no_notes_title'       => __( 'No Notes Yet', 'alynt-wc-customer-order-manager' ),
					'no_notes_description' => __( 'Notes added for this customer will appear here for your team.', 'alynt-wc-customer-order-manager' ),
					'add_first_note'       => __( 'Add First Note', 'alynt-wc-customer-order-manager' ),
					'note_added'           => __( 'Note added successfully.', 'alynt-wc-customer-order-manager' ),
					'note_updated'         => __( 'Note updated successfully.', 'alynt-wc-customer-order-manager' ),
					/* translators: 1: customer note author, 2: customer note date. */
					'note_meta'            => __( 'By %1$s on %2$s', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: server-provided error details while adding a customer note. */
					'add_error'            => __( 'Could not add the note. %s', 'alynt-wc-customer-order-manager' ),
					'add_error_generic'    => __( 'Could not add the note. Please try again.', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: server-provided error details while updating a customer note. */
					'update_error'         => __( 'Could not update the note. %s', 'alynt-wc-customer-order-manager' ),
					'update_error_generic' => __( 'Could not update the note. Please try again.', 'alynt-wc-customer-order-manager' ),
					/* translators: %s: server-provided error details while deleting a customer note. */
					'delete_error'         => __( 'Could not delete the note. %s', 'alynt-wc-customer-order-manager' ),
					'delete_error_generic' => __( 'Could not delete the note. Please try again.', 'alynt-wc-customer-order-manager' ),
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
		$this->validate_notes_ajax_request();

		$customer_id  = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
		$note_content = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $this->get_valid_notes_customer( $customer_id ) || ! $note_content ) {
			$this->send_notes_ajax_error( __( 'The note could not be saved because some required information was missing. Refresh the page and try again.', 'alynt-wc-customer-order-manager' ) );
		}

		$current_user = wp_get_current_user();
		$note         = array(
			'id'      => wp_generate_uuid4(),
			'content' => $note_content,
			'author'  => $current_user->display_name,
			'date'    => time(),
		);

		$notes = $this->get_customer_notes( $customer_id );

		array_unshift( $notes, $note );
		update_user_meta( $customer_id, '_awcom_customer_notes', $notes );

		$formatted_date = date_i18n(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$note['date']
		);

		wp_send_json_success(
			array(
				'id'      => $note['id'],
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
		$this->validate_notes_ajax_request();

		$customer_id  = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
		$note_id      = isset( $_POST['note_id'] ) ? sanitize_text_field( wp_unslash( $_POST['note_id'] ) ) : '';
		$note_index   = isset( $_POST['note_index'] ) ? intval( wp_unslash( $_POST['note_index'] ) ) : -1;
		$note_content = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $this->get_valid_notes_customer( $customer_id ) || ! $note_content || ( '' === $note_id && $note_index < 0 ) ) {
			$this->send_notes_ajax_error( __( 'The note could not be updated because some required information was missing. Refresh the page and try again.', 'alynt-wc-customer-order-manager' ) );
		}

		$notes = $this->get_customer_notes( $customer_id );
		if ( '' !== $note_id ) {
			$note_index = $this->find_customer_note_index( $notes, $note_id );
		}

		if ( ! isset( $notes[ $note_index ] ) ) {
			$this->send_notes_ajax_error( __( 'This note could not be found. Refresh the page and try again.', 'alynt-wc-customer-order-manager' ), 404 );
		}

		$notes[ $note_index ]['content']   = $note_content;
		$notes[ $note_index ]['edited']    = true;
		$notes[ $note_index ]['edit_date'] = time();
		update_user_meta( $customer_id, '_awcom_customer_notes', $notes );

		wp_send_json_success(
			array(
				'id'      => $notes[ $note_index ]['id'],
				'content' => $note_content,
			)
		);
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
		$this->validate_notes_ajax_request();

		$customer_id = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
		$note_id     = isset( $_POST['note_id'] ) ? sanitize_text_field( wp_unslash( $_POST['note_id'] ) ) : '';
		$note_index  = isset( $_POST['note_index'] ) ? intval( wp_unslash( $_POST['note_index'] ) ) : -1;

		if ( ! $this->get_valid_notes_customer( $customer_id ) || ( '' === $note_id && $note_index < 0 ) ) {
			$this->send_notes_ajax_error( __( 'The note could not be deleted because some required information was missing. Refresh the page and try again.', 'alynt-wc-customer-order-manager' ) );
		}

		$notes = $this->get_customer_notes( $customer_id );
		if ( '' !== $note_id ) {
			$note_index = $this->find_customer_note_index( $notes, $note_id );
		}

		if ( ! isset( $notes[ $note_index ] ) ) {
			$this->send_notes_ajax_error( __( 'This note could not be found. Refresh the page and try again.', 'alynt-wc-customer-order-manager' ), 404 );
		}

		unset( $notes[ $note_index ] );
		$notes = array_values( $notes );
		update_user_meta( $customer_id, '_awcom_customer_notes', $notes );

		wp_send_json_success();
	}
}
