<?php
/**
 * Customer notes metabox partial.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="postbox">
	<h2 class="hndle"><span><?php esc_html_e( 'Customer Notes', 'alynt-wc-customer-order-manager' ); ?></span></h2>
	<div class="inside">
		<div class="customer-notes-list" aria-live="polite" aria-atomic="false">
			<?php
			$this->migrate_old_customer_notes( $customer_id );
			$notes = $this->get_customer_notes( $customer_id );
			if ( $notes && is_array( $notes ) ) {
				foreach ( $notes as $note ) {
					echo '<div class="customer-note" data-note-id="' . esc_attr( $note['id'] ) . '">';
					echo '<div class="note-content">' . wp_kses_post( $note['content'] ) . '</div>';
					echo '<div class="note-actions">';
					echo '<button type="button" class="button button-small edit-note"><span class="dashicons dashicons-edit" aria-hidden="true"></span> ' . esc_html__( 'Edit', 'alynt-wc-customer-order-manager' ) . '</button> ';
					echo '<button type="button" class="button-link-delete delete-note"><span class="dashicons dashicons-trash" aria-hidden="true"></span> ' . esc_html__( 'Delete', 'alynt-wc-customer-order-manager' ) . '</button>';
					echo '<span class="spinner awcom-note-spinner" aria-hidden="true"></span>';
					echo '</div>';
					$formatted_note_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $note['date'] );
					/* translators: 1: customer note author, 2: customer note date. */
					echo '<div class="note-meta">' . esc_html( sprintf( __( 'By %1$s on %2$s', 'alynt-wc-customer-order-manager' ), $note['author'], $formatted_note_date ) ) . '</div>';
					echo '</div>';
				}
			} else {
				echo '<div class="awcom-empty-state awcom-notes-empty-state">';
				echo '<h3>' . esc_html__( 'No Notes Yet', 'alynt-wc-customer-order-manager' ) . '</h3>';
				echo '<p>' . esc_html__( 'Notes added for this customer will appear here for your team.', 'alynt-wc-customer-order-manager' ) . '</p>';
				echo '<a href="#customer_note" class="button">' . esc_html__( 'Add First Note', 'alynt-wc-customer-order-manager' ) . '</a>';
				echo '</div>';
			}
			?>
		</div>
		<div id="awcom-notes-feedback" class="awcom-note-feedback" hidden></div>
		<div class="add-note">
			<label for="customer_note" class="screen-reader-text"><?php esc_html_e( 'Add a note about this customer', 'alynt-wc-customer-order-manager' ); ?></label>
			<textarea name="customer_note" id="customer_note" aria-describedby="customer-note-description" placeholder="<?php esc_attr_e( 'Add a note about this customer...', 'alynt-wc-customer-order-manager' ); ?>"></textarea>
			<p id="customer-note-description" class="description"><?php esc_html_e( 'Add an internal note for staff working with this customer record.', 'alynt-wc-customer-order-manager' ); ?></p>
			<button type="button" class="button add-note-button" data-customer-id="<?php echo esc_attr( $customer_id ); ?>">
				<?php esc_html_e( 'Add Note', 'alynt-wc-customer-order-manager' ); ?>
			</button>
			<span class="spinner awcom-add-note-spinner" aria-hidden="true"></span>
		</div>
	</div>
</div>
