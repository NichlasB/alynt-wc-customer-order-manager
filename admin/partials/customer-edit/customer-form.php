<?php
/**
 * Edit-customer form partial.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

defined( 'ABSPATH' ) || exit;

$groups       = $this->get_customer_groups();
$countries_obj = new \WC_Countries();
$countries     = $countries_obj->get_countries();
$validation_errors = isset( $form_values['validation_errors'] ) && is_array( $form_values['validation_errors'] ) ? $form_values['validation_errors'] : array();
$first_invalid_field = ! empty( $validation_errors ) ? array_key_first( $validation_errors ) : '';
?>
<div class="postbox">
	<h2 class="hndle"><span><?php esc_html_e( 'Customer Information', 'alynt-wc-customer-order-manager' ); ?></span></h2>
	<div class="inside">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="awcom-customer-form">
			<?php wp_nonce_field( 'edit_customer', 'awcom_customer_nonce' ); ?>
			<input type="hidden" name="action" value="awcom_edit_customer">
			<input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer_id ); ?>">

			<h3><?php esc_html_e( 'Account Details', 'alynt-wc-customer-order-manager' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="customer_group"><?php esc_html_e( 'Customer Group (if any)', 'alynt-wc-customer-order-manager' ); ?></label>
					</th>
					<td>
						<select name="customer_group" id="customer_group" class="regular-text">
							<?php if ( ! empty( $groups ) ) : ?>
								<option value=""><?php esc_html_e( '--Select a group--', 'alynt-wc-customer-order-manager' ); ?></option>
							<?php else : ?>
								<option value=""><?php esc_html_e( 'No customer groups available', 'alynt-wc-customer-order-manager' ); ?></option>
							<?php endif; ?>
							<?php foreach ( $groups as $group ) : ?>
								<option value="<?php echo esc_attr( $group->group_id ); ?>" <?php selected( (int) $form_values['customer_group'], (int) $group->group_id ); ?>>
									<?php echo esc_html( $group->group_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( empty( $groups ) ) : ?>
							<p class="description"><?php esc_html_e( 'Customer groups will appear here after they are configured.', 'alynt-wc-customer-order-manager' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="first_name"><?php esc_html_e( 'First Name', 'alynt-wc-customer-order-manager' ); ?> *</label></th>
					<td>
						<input type="text" name="first_name" id="first_name" class="regular-text" value="<?php echo esc_attr( $form_values['first_name'] ); ?>" required aria-required="true" <?php echo isset( $validation_errors['first_name'] ) ? 'aria-invalid="true" aria-describedby="awcom-edit-first-name-error"' : ''; ?> <?php echo 'first_name' === $first_invalid_field ? 'autofocus' : ''; ?>>
						<?php if ( isset( $validation_errors['first_name'] ) ) : ?>
							<p id="awcom-edit-first-name-error" class="awcom-field-error description" role="alert"><?php echo esc_html( $validation_errors['first_name'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="last_name"><?php esc_html_e( 'Last Name', 'alynt-wc-customer-order-manager' ); ?> *</label></th>
					<td>
						<input type="text" name="last_name" id="last_name" class="regular-text" value="<?php echo esc_attr( $form_values['last_name'] ); ?>" required aria-required="true" <?php echo isset( $validation_errors['last_name'] ) ? 'aria-invalid="true" aria-describedby="awcom-edit-last-name-error"' : ''; ?> <?php echo 'last_name' === $first_invalid_field ? 'autofocus' : ''; ?>>
						<?php if ( isset( $validation_errors['last_name'] ) ) : ?>
							<p id="awcom-edit-last-name-error" class="awcom-field-error description" role="alert"><?php echo esc_html( $validation_errors['last_name'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="email"><?php esc_html_e( 'Account Email', 'alynt-wc-customer-order-manager' ); ?> *</label></th>
					<td>
						<input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr( $form_values['email'] ); ?>" required aria-required="true" <?php echo isset( $validation_errors['email'] ) ? 'aria-invalid="true" aria-describedby="awcom-edit-email-error"' : ''; ?> <?php echo 'email' === $first_invalid_field ? 'autofocus' : ''; ?>>
						<?php if ( isset( $validation_errors['email'] ) ) : ?>
							<p id="awcom-edit-email-error" class="awcom-field-error description" role="alert"><?php echo esc_html( $validation_errors['email'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Billing Address', 'alynt-wc-customer-order-manager' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="billing_email"><?php esc_html_e( 'Billing Email', 'alynt-wc-customer-order-manager' ); ?></label></th>
					<td><input type="email" name="billing_email" id="billing_email" class="regular-text" value="<?php echo esc_attr( $form_values['billing_email'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="company"><?php esc_html_e( 'Company Name', 'alynt-wc-customer-order-manager' ); ?></label></th>
					<td><input type="text" name="billing_company" id="company" class="regular-text" value="<?php echo esc_attr( $form_values['billing_company'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="phone"><?php esc_html_e( 'Phone', 'alynt-wc-customer-order-manager' ); ?></label></th>
					<td><input type="tel" name="billing_phone" id="phone" class="regular-text" value="<?php echo esc_attr( $form_values['billing_phone'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="billing_address_1"><?php esc_html_e( 'Billing Address 1', 'alynt-wc-customer-order-manager' ); ?></label></th>
					<td><input type="text" name="billing_address_1" id="billing_address_1" class="regular-text" value="<?php echo esc_attr( $form_values['billing_address_1'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="billing_address_2"><?php esc_html_e( 'Billing Address 2', 'alynt-wc-customer-order-manager' ); ?></label></th>
					<td><input type="text" name="billing_address_2" id="billing_address_2" class="regular-text" value="<?php echo esc_attr( $form_values['billing_address_2'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="billing_city"><?php esc_html_e( 'City', 'alynt-wc-customer-order-manager' ); ?></label></th>
					<td><input type="text" name="billing_city" id="billing_city" class="regular-text" value="<?php echo esc_attr( $form_values['billing_city'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="billing_state"><?php esc_html_e( 'State', 'alynt-wc-customer-order-manager' ); ?></label></th>
					<td><input type="text" name="billing_state" id="billing_state" class="regular-text" value="<?php echo esc_attr( $form_values['billing_state'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="billing_postcode"><?php esc_html_e( 'Postal Code', 'alynt-wc-customer-order-manager' ); ?></label></th>
					<td><input type="text" name="billing_postcode" id="billing_postcode" class="regular-text" value="<?php echo esc_attr( $form_values['billing_postcode'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="billing_country"><?php esc_html_e( 'Country', 'alynt-wc-customer-order-manager' ); ?></label></th>
					<td>
						<select name="billing_country" id="billing_country" class="regular-text">
							<option value=""><?php esc_html_e( '--Select--', 'alynt-wc-customer-order-manager' ); ?></option>
							<?php foreach ( $countries as $code => $name ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $form_values['billing_country'] ); ?>>
									<?php echo esc_html( $name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<?php require AWCOM_PLUGIN_PATH . 'admin/partials/customer-edit/customer-shipping-fields.php'; ?>

			<div class="submit-button-container">
				<?php submit_button( esc_html__( 'Update Customer', 'alynt-wc-customer-order-manager' ) ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alynt-wc-customer-order-manager' ) ); ?>" class="button">
					<?php esc_html_e( 'Back to List', 'alynt-wc-customer-order-manager' ); ?>
				</a>
			</div>
		</form>
	</div>
</div>
