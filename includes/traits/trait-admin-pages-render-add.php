<?php
/**
 * Add new customer page rendering for AdminPages.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Add New Customer form inline.
 *
 * @since 1.0.0
 */
trait AdminPagesRenderAddTrait {

	/**
	 * Render the Add New Customer admin page.
	 *
	 * Outputs a form with customer group, name, email, and billing address
	 * fields. Displays success/error notices from URL query parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_add_page() {
		if ( ! Security::user_can_access() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'alynt-wc-customer-order-manager' ) );
		}

		$form_values = array_merge(
			array(
				'customer_group'    => 0,
				'first_name'        => '',
				'last_name'         => '',
				'email'             => '',
				'billing_email'     => '',
				'billing_company'   => '',
				'billing_phone'     => '',
				'billing_address_1' => '',
				'billing_address_2' => '',
				'billing_city'      => '',
				'billing_state'     => '',
				'billing_postcode'  => '',
				'billing_country'   => 'US',
			),
			$this->pull_form_state( 'awcom_add_customer_form' )
		);
		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading redirect notice query args only. */
		$created_flag = isset( $_GET['created'] ) ? sanitize_key( wp_unslash( $_GET['created'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The decoded value is sanitized immediately for display.
		$error_message = isset( $_GET['error'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['error'] ) ) ) : '';
		/* phpcs:enable */
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Add New Customer', 'alynt-wc-customer-order-manager' ); ?></h1>

			<?php
			if ( '1' === $created_flag ) {
				echo '<div class="notice notice-success is-dismissible" role="status"><p>' .
					esc_html__( 'Customer created successfully.', 'alynt-wc-customer-order-manager' ) . '</p></div>';
			}
			if ( '' !== $error_message ) {
				echo '<div class="notice notice-error is-dismissible" role="alert"><p>' .
					esc_html( $error_message ) . '</p></div>';
			}
			?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="awcom-customer-form">
				<?php wp_nonce_field( 'create_customer', 'awcom_customer_nonce' ); ?>
				<input type="hidden" name="action" value="awcom_create_customer">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="customer_group"><?php esc_html_e( 'Customer Group', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<?php
							global $wpdb;
							$groups_table = $wpdb->prefix . 'customer_groups';
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only lookup for configured customer groups.
							$table_exists = $wpdb->get_var(
								$wpdb->prepare(
									'SHOW TABLES LIKE %s',
									$wpdb->esc_like( $groups_table )
								)
							);
							$groups       = array();
							if ( $table_exists === $groups_table ) {
								// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only lookup for configured customer groups.
								$groups = $wpdb->get_results(
									$wpdb->prepare(
										"SELECT group_id, group_name FROM {$groups_table} WHERE group_id >= %d ORDER BY group_name ASC",
										0
									)
								);
							}
							if ( ! is_array( $groups ) ) {
								$groups = array();
							}
							?>
							<select name="customer_group" id="customer_group" class="regular-text">
								<?php if ( ! empty( $groups ) ) : ?>
									<option value=""><?php esc_html_e( '- None (unassigned) -', 'alynt-wc-customer-order-manager' ); ?></option>
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
						<th scope="row">
							<label for="first_name"><?php esc_html_e( 'First Name', 'alynt-wc-customer-order-manager' ); ?> *</label>
						</th>
						<td>
							<input type="text" name="first_name" id="first_name" class="regular-text" value="<?php echo esc_attr( $form_values['first_name'] ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="last_name"><?php esc_html_e( 'Last Name', 'alynt-wc-customer-order-manager' ); ?> *</label>
						</th>
						<td>
							<input type="text" name="last_name" id="last_name" class="regular-text" value="<?php echo esc_attr( $form_values['last_name'] ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="email"><?php esc_html_e( 'Account Email', 'alynt-wc-customer-order-manager' ); ?> *</label>
						</th>
						<td>
							<input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr( $form_values['email'] ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="billing_email"><?php esc_html_e( 'Billing Email', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<input type="email" name="billing_email" id="billing_email" class="regular-text" value="<?php echo esc_attr( $form_values['billing_email'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="company"><?php esc_html_e( 'Company Name', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<input type="text" name="billing_company" id="company" class="regular-text" value="<?php echo esc_attr( $form_values['billing_company'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="phone"><?php esc_html_e( 'Phone', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<input type="tel" name="billing_phone" id="phone" class="regular-text" value="<?php echo esc_attr( $form_values['billing_phone'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="billing_address_1"><?php esc_html_e( 'Billing Address 1', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<input type="text" name="billing_address_1" id="billing_address_1" class="regular-text" value="<?php echo esc_attr( $form_values['billing_address_1'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="billing_address_2"><?php esc_html_e( 'Billing Address 2', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<input type="text" name="billing_address_2" id="billing_address_2" class="regular-text" value="<?php echo esc_attr( $form_values['billing_address_2'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="billing_city"><?php esc_html_e( 'City', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<input type="text" name="billing_city" id="billing_city" class="regular-text" value="<?php echo esc_attr( $form_values['billing_city'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="billing_state"><?php esc_html_e( 'State', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<input type="text" name="billing_state" id="billing_state" class="regular-text" value="<?php echo esc_attr( $form_values['billing_state'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="billing_postcode"><?php esc_html_e( 'Postal Code', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<input type="text" name="billing_postcode" id="billing_postcode" class="regular-text" value="<?php echo esc_attr( $form_values['billing_postcode'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="billing_country"><?php esc_html_e( 'Country', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
							<select name="billing_country" id="billing_country" class="regular-text">
								<?php
								$countries_obj = new \WC_Countries();
								$countries     = $countries_obj->get_countries();
								foreach ( $countries as $code => $name ) {
									echo '<option value="' . esc_attr( $code ) . '"' .
										selected( $code, $form_values['billing_country'], false ) . '>' .
										esc_html( $name ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Create Customer', 'alynt-wc-customer-order-manager' ) ); ?>
			</form>
		</div>
		<?php
	}
}
