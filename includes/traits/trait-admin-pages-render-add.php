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

		wp_enqueue_style(
			'awcom-edit-customer',
			AWCOM_PLUGIN_URL . 'assets/css/edit-customer.css',
			array( 'dashicons' ),
			AWCOM_VERSION
		);

		$form_state = $this->pull_form_state( 'awcom_add_customer_form' );
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
				'validation_errors' => array(),
			),
			$form_state
		);
		$validation_errors = isset( $form_values['validation_errors'] ) && is_array( $form_values['validation_errors'] ) ? $form_values['validation_errors'] : array();
		$first_invalid_field = ! empty( $validation_errors ) ? array_key_first( $validation_errors ) : '';
		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading redirect notice query args only. */
		$created_flag = isset( $_GET['created'] ) ? sanitize_key( wp_unslash( $_GET['created'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The decoded value is sanitized immediately for display.
		$error_message = isset( $_GET['error'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['error'] ) ) ) : '';
		/* phpcs:enable */
		$groups = $this->get_customer_groups();
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
			<hr class="wp-header-end">

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="awcom-customer-form">
				<?php wp_nonce_field( 'create_customer', 'awcom_customer_nonce' ); ?>
				<input type="hidden" name="action" value="awcom_create_customer">

				<h2><?php esc_html_e( 'Account Details', 'alynt-wc-customer-order-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="customer_group"><?php esc_html_e( 'Customer Group', 'alynt-wc-customer-order-manager' ); ?></label>
						</th>
						<td>
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
							<input type="text" name="first_name" id="first_name" class="regular-text" value="<?php echo esc_attr( $form_values['first_name'] ); ?>" required aria-required="true" <?php echo isset( $validation_errors['first_name'] ) ? 'aria-invalid="true" aria-describedby="awcom-first-name-error"' : ''; ?> <?php echo 'first_name' === $first_invalid_field ? 'autofocus' : ''; ?>>
							<?php if ( isset( $validation_errors['first_name'] ) ) : ?>
								<p id="awcom-first-name-error" class="awcom-field-error description" role="alert"><?php echo esc_html( $validation_errors['first_name'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="last_name"><?php esc_html_e( 'Last Name', 'alynt-wc-customer-order-manager' ); ?> *</label>
						</th>
						<td>
							<input type="text" name="last_name" id="last_name" class="regular-text" value="<?php echo esc_attr( $form_values['last_name'] ); ?>" required aria-required="true" <?php echo isset( $validation_errors['last_name'] ) ? 'aria-invalid="true" aria-describedby="awcom-last-name-error"' : ''; ?> <?php echo 'last_name' === $first_invalid_field ? 'autofocus' : ''; ?>>
							<?php if ( isset( $validation_errors['last_name'] ) ) : ?>
								<p id="awcom-last-name-error" class="awcom-field-error description" role="alert"><?php echo esc_html( $validation_errors['last_name'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="email"><?php esc_html_e( 'Account Email', 'alynt-wc-customer-order-manager' ); ?> *</label>
						</th>
						<td>
							<input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr( $form_values['email'] ); ?>" required aria-required="true" <?php echo isset( $validation_errors['email'] ) ? 'aria-invalid="true" aria-describedby="awcom-email-error"' : ''; ?> <?php echo 'email' === $first_invalid_field ? 'autofocus' : ''; ?>>
							<?php if ( isset( $validation_errors['email'] ) ) : ?>
								<p id="awcom-email-error" class="awcom-field-error description" role="alert"><?php echo esc_html( $validation_errors['email'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Billing Address', 'alynt-wc-customer-order-manager' ); ?></h2>
				<table class="form-table">
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
