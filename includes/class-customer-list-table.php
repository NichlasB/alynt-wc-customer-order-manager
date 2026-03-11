<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * WP_List_Table implementation for the Customer Manager screen.
 *
 * Displays a paginated, sortable table of WooCommerce customers with
 * columns for name, company, email, phone, address, registration date,
 * and order count.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the customer list table in the WordPress admin.
 *
 * @since 1.0.0
 */
class CustomerListTable extends \WP_List_Table {
	private $order_counts = array();

	/**
	 * Set up list table configuration: singular/plural labels and no AJAX.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => _x( 'customer', 'customer list table singular label', 'alynt-wc-customer-order-manager' ),
				'plural'   => _x( 'customers', 'customer list table plural label', 'alynt-wc-customer-order-manager' ),
				'ajax'     => false,
			)
		);
	}

	private function prime_order_counts( array $customer_ids ) {
		$customer_ids = array_values( array_unique( array_filter( array_map( 'absint', $customer_ids ) ) ) );
		$this->order_counts = array_fill_keys( $customer_ids, 0 );

		if ( empty( $customer_ids ) ) {
			return;
		}

		global $wpdb;
		$statuses            = array_keys( wc_get_order_statuses() );
		$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$customer_placeholders = implode( ',', array_fill( 0, count( $customer_ids ), '%d' ) );

		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$query = $wpdb->prepare(
				"SELECT customer_id, COUNT(*) AS order_count
				FROM {$wpdb->prefix}wc_orders
				WHERE type = %s
				AND status IN ({$status_placeholders})
				AND customer_id IN ({$customer_placeholders})
				GROUP BY customer_id",
				array_merge( array( 'shop_order' ), $statuses, $customer_ids )
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT CAST(pm.meta_value AS UNSIGNED) AS customer_id, COUNT(DISTINCT p.ID) AS order_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status IN ({$status_placeholders})
				AND pm.meta_key = %s
				AND CAST(pm.meta_value AS UNSIGNED) IN ({$customer_placeholders})
				GROUP BY customer_id",
				array_merge( array( 'shop_order' ), $statuses, array( '_customer_user' ), $customer_ids )
			);
		}

		$results = $wpdb->get_results( $query );
		if ( ! is_array( $results ) ) {
			return;
		}

		foreach ( $results as $result ) {
			$customer_id = isset( $result->customer_id ) ? absint( $result->customer_id ) : 0;
			if ( $customer_id > 0 ) {
				$this->order_counts[ $customer_id ] = isset( $result->order_count ) ? (int) $result->order_count : 0;
			}
		}
	}

	private function get_order_count( $customer_id ) {
		$customer_id = absint( $customer_id );

		return isset( $this->order_counts[ $customer_id ] ) ? (int) $this->order_counts[ $customer_id ] : 0;
	}

	/**
	 * Define the table columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array Column slug => label pairs.
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'customer_name' => __( 'Name', 'alynt-wc-customer-order-manager' ),
			'company'       => __( 'Company', 'alynt-wc-customer-order-manager' ),
			'email'         => __( 'Email', 'alynt-wc-customer-order-manager' ),
			'phone'         => __( 'Phone', 'alynt-wc-customer-order-manager' ),
			'address'       => __( 'Address', 'alynt-wc-customer-order-manager' ),
			'created'       => __( 'Created', 'alynt-wc-customer-order-manager' ),
			'orders'        => __( 'Orders', 'alynt-wc-customer-order-manager' ),
		);
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @since 1.0.0
	 *
	 * @return array Column slug => [orderby_value, is_currently_sorted] pairs.
	 */
	public function get_sortable_columns() {
		return array(
			'customer_name' => array( 'customer_name', true ),
			'email'         => array( 'email', false ),
			'created'       => array( 'created', false ),
		);
	}

	/**
	 * Render the checkbox column for bulk actions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item The current user row object.
	 * @return string HTML checkbox input.
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="customers[]" value="%s" />',
			$item->ID
		);
	}

	/**
	 * Render the customer name column with row action links.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item The current user row object.
	 * @return string HTML for the name cell including Edit and Delete row actions.
	 */
	protected function column_customer_name( $item ) {
		$name      = $item->first_name . ' ' . $item->last_name;
		$edit_link = admin_url( 'admin.php?page=alynt-wc-customer-order-manager-edit&id=' . $item->ID );

		$actions = array(
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_link ),
				__( 'Edit', 'alynt-wc-customer-order-manager' )
			),
			'delete' => sprintf(
				'<a href="#" class="delete-customer" data-id="%s">%s</a>',
				$item->ID,
				__( 'Delete', 'alynt-wc-customer-order-manager' )
			),
		);

		return sprintf(
			'<a href="%1$s"><strong>%2$s</strong></a> %3$s',
			esc_url( $edit_link ),
			esc_html( $name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render the order count column linking to the customer's orders.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item The current user row object.
	 * @return string HTML anchor with order count.
	 */
	protected function column_orders( $item ) {
		$count = $this->get_order_count( $item->ID );
		$url   = admin_url( 'edit.php?post_type=shop_order&_customer_user=' . $item->ID );

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			/* translators: %d: number of customer orders. */
			sprintf( _n( '%d order', '%d orders', $count, 'alynt-wc-customer-order-manager' ), $count )
		);
	}

	/**
	 * Render any column not handled by a dedicated method.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item        The current user row object.
	 * @param string   $column_name The column slug being rendered.
	 * @return string Cell HTML.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'company':
				return get_user_meta( $item->ID, 'billing_company', true );

			case 'email':
				return '<a href="mailto:' . esc_attr( $item->user_email ) . '">' .
					esc_html( $item->user_email ) . '</a>';

			case 'phone':
				$phone = get_user_meta( $item->ID, 'billing_phone', true );
				return $phone ? '<a href="tel:' . esc_attr( $phone ) . '">' .
					esc_html( $phone ) . '</a>' : '';

			case 'address':
				$address_parts = array_filter(
					array(
						get_user_meta( $item->ID, 'billing_address_1', true ),
						get_user_meta( $item->ID, 'billing_city', true ),
						get_user_meta( $item->ID, 'billing_state', true ),
						get_user_meta( $item->ID, 'billing_postcode', true ),
					)
				);
				return implode( ', ', $address_parts );

			case 'created':
				return date_i18n(
					get_option( 'date_format' ),
					strtotime( $item->user_registered )
				);

			default:
				return '';
		}
	}

	/**
	 * Query customers and prepare table data, pagination, and column headers.
	 *
	 * Supports search by name/email, sorting by name/email/registration date,
	 * and paginates at 20 customers per page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Set the column headers.
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading list table search and sort query args only. */
		// Build the user query arguments.
		$search_term = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$args        = array(
			'role'   => 'customer',
			'number' => $per_page,
			'offset' => ( $current_page - 1 ) * $per_page,
			'search' => ! empty( $search_term ) ? '*' . $search_term . '*' : '',
		);

		// Handle sorting.
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : '';
		if ( ! empty( $orderby ) ) {
			switch ( $orderby ) {
				case 'customer_name':
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- WP_User_Query sorting by first name requires meta_key.
					$args['meta_key'] = 'first_name';
					$args['orderby']  = 'meta_value';
					break;
				default:
					$args['orderby'] = $orderby;
			}

			$order         = isset( $_REQUEST['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) : 'ASC';
			$args['order'] = $order;
		} else {
			$args['orderby'] = 'registered';
			$args['order']   = 'DESC';
		}
		/* phpcs:enable */

		// Get the customer results.
		$user_query  = new \WP_User_Query( $args );
		$this->items = $user_query->get_results();
		$customer_ids = wp_list_pluck( $this->items, 'ID' );
		if ( ! empty( $customer_ids ) ) {
			update_meta_cache( 'user', $customer_ids );
			$this->prime_order_counts( $customer_ids );
		}

		// Set the pagination arguments.
		$total_items = $user_query->get_total();
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Register available bulk actions for the customer list.
	 *
	 * @since 1.0.0
	 *
	 * @return array Action slug => label pairs.
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'alynt-wc-customer-order-manager' ),
		);
	}

	/**
	 * Build the status filter view links shown above the table.
	 *
	 * Currently only an "All" view is provided.
	 *
	 * @since 1.0.0
	 *
	 * @return array View slug => HTML link pairs.
	 */
	protected function get_views() {
		$views = array();

		// Count customers.
		$role_counts     = count_users();
		$total_customers = isset( $role_counts['avail_roles']['customer'] ) ? (int) $role_counts['avail_roles']['customer'] : 0;

		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading list table filter query args only. */
		$current = isset( $_REQUEST['customer_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['customer_status'] ) ) : 'all';
		/* phpcs:enable */
		$all_class    = 'all' === $current ? ' class="current"' : '';
		$views['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			admin_url( 'admin.php?page=alynt-wc-customer-order-manager' ),
			$all_class,
			__( 'All', 'alynt-wc-customer-order-manager' ),
			$total_customers
		);

		return $views;
	}
}
