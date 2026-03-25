<?php
/**
 * Create Order page rendering for OrderInterface.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the admin Create Order page HTML.
 *
 * @since 1.0.0
 */
trait OrderInterfaceRenderTrait {

	/**
	 * Render the Create Order admin page for a specific customer.
	 *
	 * Validates the customer_id query parameter and the customer's role,
	 * then outputs the two-column order builder UI with product search,
	 * order items table, shipping selector, and customer address summary.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_create_order_page() {
		if ( ! Security::user_can_access() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'alynt-wc-customer-order-manager' ) );
		}

		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading routing and notice query args only. */
		$this->customer_id = isset( $_GET['customer_id'] ) ? absint( wp_unslash( $_GET['customer_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The decoded value is sanitized immediately for display.
		$error_message = isset( $_GET['error'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['error'] ) ) ) : '';
		/* phpcs:enable */
		if ( ! $this->customer_id ) {
			wp_die( esc_html__( 'Invalid customer ID.', 'alynt-wc-customer-order-manager' ) );
		}

		$customer = get_user_by( 'id', $this->customer_id );
		if ( ! $customer || ! in_array( 'customer', (array) $customer->roles, true ) ) {
			wp_die( esc_html__( 'Invalid customer.', 'alynt-wc-customer-order-manager' ) );
		}

		?>
		<div class="wrap">
			<h1>
			<?php
			printf(
				/* translators: %s: customer full name. */
				esc_html__( 'Create Order for %s', 'alynt-wc-customer-order-manager' ),
				esc_html( $customer->first_name . ' ' . $customer->last_name )
			);
			?>
				</h1>

			<?php
			if ( '' !== $error_message ) {
				echo '<div class="notice notice-error is-dismissible" role="alert"><p>' .
				esc_html( $error_message ) . '</p></div>';
			}
			?>
			<hr class="wp-header-end">

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="awcom-create-order-form">
				<?php wp_nonce_field( 'create_order', 'awcom_order_nonce' ); ?>
				<input type="hidden" name="action" value="awcom_create_order">
				<input type="hidden" name="customer_id" value="<?php echo esc_attr( $this->customer_id ); ?>">

				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content">
							<div class="postbox">
								<h2 class="hndle"><span><?php esc_html_e( 'Order Items', 'alynt-wc-customer-order-manager' ); ?></span></h2>
								<div class="inside">
									<div class="awcom-product-search">
										<label for="awcom-add-product" class="screen-reader-text"><?php esc_html_e( 'Search for a product to add', 'alynt-wc-customer-order-manager' ); ?></label>
										<select id="awcom-add-product" class="awcom-product-search-field">
											<option></option>
										</select>
									</div>
									<table class="widefat awcom-order-items" aria-label="<?php esc_attr_e( 'Order Items', 'alynt-wc-customer-order-manager' ); ?>">
										<thead>
											<tr>
												<th scope="col"><?php esc_html_e( 'Item', 'alynt-wc-customer-order-manager' ); ?></th>
												<th scope="col" class="quantity"><?php esc_html_e( 'Qty', 'alynt-wc-customer-order-manager' ); ?></th>
												<th scope="col" class="price"><?php esc_html_e( 'Price', 'alynt-wc-customer-order-manager' ); ?></th>
												<th scope="col" class="total"><?php esc_html_e( 'Total', 'alynt-wc-customer-order-manager' ); ?></th>
												<th scope="col" class="actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'alynt-wc-customer-order-manager' ); ?></span></th>
											</tr>
										</thead>
										<tbody></tbody>
										<tfoot>
											<tr>
												<td colspan="2"></td>
												<td><?php esc_html_e( 'Subtotal:', 'alynt-wc-customer-order-manager' ); ?></td>
												<td class="subtotal">0.00</td>
												<td></td>
											</tr>
											<tr>
												<td colspan="2"></td>
												<td><?php esc_html_e( 'Shipping:', 'alynt-wc-customer-order-manager' ); ?></td>
												<td class="shipping-total">0.00</td>
												<td></td>
											</tr>
											<tr>
												<td colspan="2"></td>
												<td><strong><?php esc_html_e( 'Total:', 'alynt-wc-customer-order-manager' ); ?></strong></td>
												<td class="order-total"><strong>0.00</strong></td>
												<td></td>
											</tr>
										</tfoot>
									</table>
								</div>
							</div>

							<div class="postbox">
								<h2 class="hndle"><span><?php esc_html_e( 'Order Notes', 'alynt-wc-customer-order-manager' ); ?></span></h2>
								<div class="inside">
									<label for="order_notes" class="screen-reader-text"><?php esc_html_e( 'Order Notes (optional)', 'alynt-wc-customer-order-manager' ); ?></label>
									<p id="awcom-order-notes-description" class="description"><?php esc_html_e( 'Add internal notes for this order if your team needs extra context.', 'alynt-wc-customer-order-manager' ); ?></p>
									<textarea name="order_notes" id="order_notes" rows="5" class="awcom-order-notes"
									aria-describedby="awcom-order-notes-description" placeholder="<?php esc_attr_e( 'Add any notes about this order (optional)', 'alynt-wc-customer-order-manager' ); ?>"></textarea>
								</div>
							</div>
						</div>

						<div id="postbox-container-1" class="postbox-container">
							<div class="postbox">
								<h2 class="hndle"><span><?php esc_html_e( 'Order Actions', 'alynt-wc-customer-order-manager' ); ?></span></h2>
								<div class="inside">
									<?php submit_button( esc_html__( 'Create Order', 'alynt-wc-customer-order-manager' ), 'primary', 'submit', false ); ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=alynt-wc-customer-order-manager-edit&id=' . $this->customer_id ) ); ?>"
										class="button"><?php esc_html_e( 'Cancel', 'alynt-wc-customer-order-manager' ); ?></a>
								</div>
							</div>

							<div class="postbox">
								<h2 class="hndle"><span><?php esc_html_e( 'Customer Details', 'alynt-wc-customer-order-manager' ); ?></span></h2>
								<div class="inside">
									<div class="billing-details">
										<h4><?php esc_html_e( 'Billing Address', 'alynt-wc-customer-order-manager' ); ?></h4>
										<?php
										$billing_address = array(
											'first_name' => $customer->first_name,
											'last_name'  => $customer->last_name,
											'company'    => get_user_meta( $this->customer_id, 'billing_company', true ),
											'address_1'  => get_user_meta( $this->customer_id, 'billing_address_1', true ),
											'address_2'  => get_user_meta( $this->customer_id, 'billing_address_2', true ),
											'city'       => get_user_meta( $this->customer_id, 'billing_city', true ),
											'state'      => get_user_meta( $this->customer_id, 'billing_state', true ),
											'postcode'   => get_user_meta( $this->customer_id, 'billing_postcode', true ),
											'country'    => get_user_meta( $this->customer_id, 'billing_country', true ),
										);

										echo '<div class="address">';
										echo wp_kses_post( WC()->countries->get_formatted_address( $billing_address ) );
										echo '</div>';
										?>
									</div>

									<?php
									$shipping_address_1 = get_user_meta( $this->customer_id, 'shipping_address_1', true );
									if ( ! empty( $shipping_address_1 ) ) {
										$shipping_address = array(
											'first_name' => $customer->first_name,
											'last_name'  => $customer->last_name,
											'company'    => get_user_meta( $this->customer_id, 'shipping_company', true ),
											'address_1'  => get_user_meta( $this->customer_id, 'shipping_address_1', true ),
											'address_2'  => get_user_meta( $this->customer_id, 'shipping_address_2', true ),
											'city'       => get_user_meta( $this->customer_id, 'shipping_city', true ),
											'state'      => get_user_meta( $this->customer_id, 'shipping_state', true ),
											'postcode'   => get_user_meta( $this->customer_id, 'shipping_postcode', true ),
											'country'    => get_user_meta( $this->customer_id, 'shipping_country', true ),
										);

										$is_different = (
											$shipping_address['address_1'] !== $billing_address['address_1'] ||
											$shipping_address['city'] !== $billing_address['city'] ||
											$shipping_address['country'] !== $billing_address['country']
										);

										if ( $is_different ) {
											echo '<div class="shipping-details awcom-shipping-details">';
											echo '<h4>' . esc_html__( 'Shipping Address', 'alynt-wc-customer-order-manager' ) . '</h4>';
											echo '<div class="address">';
											echo wp_kses_post( WC()->countries->get_formatted_address( $shipping_address ) );
											echo '</div>';
											echo '</div>';
										}
									}
									?>
								</div>
							</div>

							<div class="postbox">
								<h2 class="hndle"><span><?php esc_html_e( 'Shipping', 'alynt-wc-customer-order-manager' ); ?></span></h2>
								<div class="inside">
									<div id="shipping-methods" aria-live="polite" aria-atomic="true">
										<p class="loading"><?php esc_html_e( 'Calculating available shipping methods...', 'alynt-wc-customer-order-manager' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}
}
