<?php
/**
 * Edit-customer shipping fields partial.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

$same_as_billing = '1' === $form_values['same_as_billing'];
?>
<h3><?php esc_html_e( 'Shipping Address', 'alynt-wc-customer-order-manager' ); ?></h3>

<table class="form-table">
	<tr>
		<th scope="row"><label for="same_as_billing"><?php esc_html_e( 'Same as billing address', 'alynt-wc-customer-order-manager' ); ?></label></th>
		<td>
			<input type="checkbox" name="same_as_billing" id="same_as_billing" value="1" <?php checked( $same_as_billing ); ?>>
			<span class="description"><?php esc_html_e( 'Check this box if the shipping address is the same as the billing address', 'alynt-wc-customer-order-manager' ); ?></span>
		</td>
	</tr>
</table>

<div id="shipping-address-fields" style="<?php echo esc_attr( $same_as_billing ? 'display: none;' : '' ); ?>">
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

<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#same_as_billing').change(function() {
		if ($(this).is(':checked')) {
			$('#shipping-address-fields').hide();
			$('#shipping_address_1').val($('#billing_address_1').val());
			$('#shipping_address_2').val($('#billing_address_2').val());
			$('#shipping_phone').val($('#phone').val());
			$('#shipping_city').val($('#billing_city').val());
			$('#shipping_state').val($('#billing_state').val());
			$('#shipping_postcode').val($('#billing_postcode').val());
			$('#shipping_country').val($('#billing_country').val());
		} else {
			$('#shipping-address-fields').show();
		}
	});
});
</script>
