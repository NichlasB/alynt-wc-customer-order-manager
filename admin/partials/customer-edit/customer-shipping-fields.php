<?php
/**
 * Edit-customer shipping fields partial.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

defined( 'ABSPATH' ) || exit;

$same_as_billing = '1' === $form_values['same_as_billing'];
$shipping_fields_class = $same_as_billing ? 'awcom-is-hidden' : '';
?>
<h3><?php esc_html_e( 'Shipping Address', 'alynt-wc-customer-order-manager' ); ?></h3>

<table class="form-table">
	<tr>
		<th scope="row"><label for="same_as_billing"><?php esc_html_e( 'Same as billing address', 'alynt-wc-customer-order-manager' ); ?></label></th>
		<td>
			<input type="checkbox" name="same_as_billing" id="same_as_billing" value="1" aria-describedby="same-as-billing-description" <?php checked( $same_as_billing ); ?>>
			<p id="same-as-billing-description" class="description"><?php esc_html_e( 'Select this option to copy the billing address into the shipping address fields.', 'alynt-wc-customer-order-manager' ); ?></p>
		</td>
	</tr>
</table>

<div id="shipping-address-fields" class="<?php echo esc_attr( $shipping_fields_class ); ?>">
	<table class="form-table">
		<tr>
			<th scope="row"><label for="shipping_address_1"><?php esc_html_e( 'Shipping Address 1', 'alynt-wc-customer-order-manager' ); ?></label></th>
			<td><input type="text" name="shipping_address_1" id="shipping_address_1" class="regular-text" value="<?php echo esc_attr( $form_values['shipping_address_1'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="shipping_address_2"><?php esc_html_e( 'Shipping Address 2', 'alynt-wc-customer-order-manager' ); ?></label></th>
			<td><input type="text" name="shipping_address_2" id="shipping_address_2" class="regular-text" value="<?php echo esc_attr( $form_values['shipping_address_2'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="shipping_phone"><?php esc_html_e( 'Phone', 'alynt-wc-customer-order-manager' ); ?></label></th>
			<td><input type="tel" name="shipping_phone" id="shipping_phone" class="regular-text" value="<?php echo esc_attr( $form_values['shipping_phone'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="shipping_city"><?php esc_html_e( 'City', 'alynt-wc-customer-order-manager' ); ?></label></th>
			<td><input type="text" name="shipping_city" id="shipping_city" class="regular-text" value="<?php echo esc_attr( $form_values['shipping_city'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="shipping_state"><?php esc_html_e( 'State/Province', 'alynt-wc-customer-order-manager' ); ?></label></th>
			<td><input type="text" name="shipping_state" id="shipping_state" class="regular-text" value="<?php echo esc_attr( $form_values['shipping_state'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="shipping_postcode"><?php esc_html_e( 'Postal Code', 'alynt-wc-customer-order-manager' ); ?></label></th>
			<td><input type="text" name="shipping_postcode" id="shipping_postcode" class="regular-text" value="<?php echo esc_attr( $form_values['shipping_postcode'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="shipping_country"><?php esc_html_e( 'Country', 'alynt-wc-customer-order-manager' ); ?></label></th>
			<td>
				<select name="shipping_country" id="shipping_country" class="regular-text">
					<option value=""><?php esc_html_e( '--Select--', 'alynt-wc-customer-order-manager' ); ?></option>
					<?php foreach ( $countries as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $form_values['shipping_country'] ); ?>>
							<?php echo esc_html( $name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>
</div>
