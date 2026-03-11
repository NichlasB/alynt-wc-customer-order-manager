<?php
/**
 * Edit-customer login details metabox partial.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

?>
<div class="postbox">
	<h2 class="hndle"><span><?php esc_html_e( 'Login Details', 'alynt-wc-customer-order-manager' ); ?></span></h2>
	<div class="inside">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'send_login_details', 'send_login_nonce' ); ?>
			<input type="hidden" name="action" value="awcom_send_login_details">
			<input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer_id ); ?>">
			<?php submit_button( esc_html__( 'Send Login Details Email', 'alynt-wc-customer-order-manager' ), 'secondary' ); ?>
		</form>
		<button type="button" class="button" id="edit-email-template" aria-expanded="false" aria-controls="email-template-modal">
			<?php esc_html_e( 'Edit Email Template', 'alynt-wc-customer-order-manager' ); ?>
		</button>
	</div>
</div>
