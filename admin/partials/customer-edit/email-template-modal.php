<?php
/**
 * Edit-customer email template modal partial.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

defined( 'ABSPATH' ) || exit;

?>
<div id="email-template-modal" class="awcom-email-template-modal">
	<div class="email-template-editor">
		<h2><?php esc_html_e( 'Edit Login Email Template', 'alynt-wc-customer-order-manager' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Available merge tags:', 'alynt-wc-customer-order-manager' ); ?><br>
			<code>{customer_first_name}</code> - <?php esc_html_e( 'Customer\'s first name', 'alynt-wc-customer-order-manager' ); ?><br>
			<code>{password_reset_link}</code> - <?php esc_html_e( 'Password reset link', 'alynt-wc-customer-order-manager' ); ?>
		</p>
		<?php
		$template = get_option( 'awcom_login_email_template', '' );
		if ( empty( $template ) ) {
			$template = sprintf(
				/* translators: %s: site name. */
				__( "Hello {customer_first_name},\n\nYou can set your password and login to your account by visiting the following address:\n\n{password_reset_link}\n\nThis link will expire in 24 hours.\n\nRegards,\n%s", 'alynt-wc-customer-order-manager' ),
				get_bloginfo( 'name' )
			);
		}
		wp_editor( $template, 'login_email_template', array( 'textarea_rows' => 15 ) );
		?>
		<div class="submit-buttons">
			<button type="button" class="button button-primary save-template">
				<?php esc_html_e( 'Save Template', 'alynt-wc-customer-order-manager' ); ?>
			</button>
			<button type="button" class="button cancel-edit">
				<?php esc_html_e( 'Cancel', 'alynt-wc-customer-order-manager' ); ?>
			</button>
		</div>
	</div>
</div>
