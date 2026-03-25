<?php
/**
 * Edit-customer orders section partial.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="orders-section postbox">
	<h2 class="hndle"><?php esc_html_e( 'Orders', 'alynt-wc-customer-order-manager' ); ?></h2>
	<div class="inside">
		<?php
		$create_order_url = admin_url( 'admin.php?page=alynt-wc-customer-order-manager-create-order&customer_id=' . $customer_id );
		$orders = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		if ( $orders ) {
			echo '<p><a href="' . esc_url( $create_order_url ) . '" class="button button-primary">' . esc_html__( 'Create New Order', 'alynt-wc-customer-order-manager' ) . '</a></p>';
			echo '<h3>' . esc_html__( 'Recent Orders', 'alynt-wc-customer-order-manager' ) . '</h3>';
			echo '<table class="widefat" aria-label="' . esc_attr__( 'Recent Orders', 'alynt-wc-customer-order-manager' ) . '"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Order', 'alynt-wc-customer-order-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Date', 'alynt-wc-customer-order-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Status', 'alynt-wc-customer-order-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Total', 'alynt-wc-customer-order-manager' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $orders as $customer_order ) {
				echo '<tr>';
				echo '<td><a href="' . esc_url( awcom_get_order_edit_url( $customer_order->get_id() ) ) . '">#' . esc_html( $customer_order->get_order_number() ) . '</a></td>';
				echo '<td>' . esc_html( wc_format_datetime( $customer_order->get_date_created() ) ) . '</td>';
				echo '<td>' . esc_html( wc_get_order_status_name( $customer_order->get_status() ) ) . '</td>';
				echo '<td>' . wp_kses_post( $customer_order->get_formatted_order_total() ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<div class="awcom-empty-state awcom-orders-empty-state">';
			echo '<h3>' . esc_html__( 'No Orders Yet', 'alynt-wc-customer-order-manager' ) . '</h3>';
			echo '<p>' . esc_html__( 'Orders created for this customer will appear here.', 'alynt-wc-customer-order-manager' ) . '</p>';
			echo '<a href="' . esc_url( $create_order_url ) . '" class="button button-primary">' . esc_html__( 'Create First Order', 'alynt-wc-customer-order-manager' ) . '</a>';
			echo '</div>';
		}
		?>
	</div>
</div>
