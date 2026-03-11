<?php
/**
 * Edit-customer page wrapper partial.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

/**
 * Customer edit page wrapper.
 *
 * Variables expected:
 * - WP_User $customer
 * - int $customer_id
 * - string $current_group_name
 */
/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading redirect notice query args only. */
$created_flag = isset( $_GET['created'] ) ? sanitize_key( wp_unslash( $_GET['created'] ) ) : '';
$updated_flag = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '';
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The decoded value is sanitized immediately for display.
$error_message = isset( $_GET['error'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['error'] ) ) ) : '';
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The decoded value is sanitized immediately for display.
$warning_message    = isset( $_GET['warning'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['warning'] ) ) ) : '';
$email_sent_flag    = isset( $_GET['email_sent'] ) ? sanitize_key( wp_unslash( $_GET['email_sent'] ) ) : '';
$notes_updated_flag = isset( $_GET['notes_updated'] ) ? sanitize_key( wp_unslash( $_GET['notes_updated'] ) ) : '';
/* phpcs:enable */
$partial_base = AWCOM_PLUGIN_PATH . 'admin/partials/customer-edit/';
?>
<div class="wrap">
	<h1>
	<?php
	printf(
		/* translators: 1: customer's first name, 2: customer's last name. */
		esc_html__( 'Edit Customer - %1$s %2$s', 'alynt-wc-customer-order-manager' ),
		esc_html( $customer->first_name ),
		esc_html( $customer->last_name )
	);
	?>
	</h1>

	<?php if ( '1' === $created_flag ) : ?>
		<div class="notice notice-success is-dismissible" role="status">
			<p><?php esc_html_e( 'Customer created successfully.', 'alynt-wc-customer-order-manager' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '1' === $updated_flag ) : ?>
		<div class="notice notice-success is-dismissible" role="status">
			<p>
				<?php
				if ( $current_group_name ) {
					/* translators: %s: current customer group name. */
					printf(
						/* translators: %s: current customer group name. */
						esc_html__( 'Customer updated successfully. Current group: %s', 'alynt-wc-customer-order-manager' ),
						esc_html( $current_group_name )
					);
				} else {
					esc_html_e( 'Customer updated successfully.', 'alynt-wc-customer-order-manager' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $error_message ) : ?>
		<div class="notice notice-error is-dismissible" role="alert"><p><?php echo esc_html( $error_message ); ?></p></div>
	<?php endif; ?>

	<?php if ( '' !== $warning_message ) : ?>
		<div class="notice notice-warning is-dismissible" role="alert"><p><?php echo esc_html( $warning_message ); ?></p></div>
	<?php endif; ?>

	<?php if ( '1' === $email_sent_flag ) : ?>
		<div class="notice notice-success is-dismissible" role="status">
			<p><?php esc_html_e( 'Login details email sent successfully.', 'alynt-wc-customer-order-manager' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '1' === $notes_updated_flag ) : ?>
		<div class="notice notice-success is-dismissible" role="status">
			<p><?php esc_html_e( 'Customer notes updated successfully.', 'alynt-wc-customer-order-manager' ); ?></p>
		</div>
	<?php endif; ?>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<?php require $partial_base . 'customer-form.php'; ?>
				<?php require $partial_base . 'orders-section.php'; ?>
			</div>

			<div id="postbox-container-1" class="postbox-container">
				<?php require $partial_base . 'login-details-box.php'; ?>
				<?php require $partial_base . 'email-template-modal.php'; ?>
				<?php require $partial_base . 'customer-notes-box.php'; ?>
			</div>
		</div>
	</div>
</div>
