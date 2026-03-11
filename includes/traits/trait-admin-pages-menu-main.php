<?php
/**
 * Admin menu registration and main page rendering for AdminPages.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes/traits
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

/**
 * Registers top-level and submenu pages and renders the customer list.
 *
 * @since 1.0.0
 */
trait AdminPagesMenuMainTrait {

	/**
	 * Enqueue customer list behaviors on the main admin page.
	 *
	 * @since 1.0.6
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_customer_list_scripts( $hook ) {
		if ( 'toplevel_page_alynt-wc-customer-order-manager' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'awcom-customer-list',
			AWCOM_PLUGIN_URL . 'assets/js/customer-list.js',
			array( 'jquery' ),
			AWCOM_VERSION,
			true
		);

		wp_localize_script(
			'awcom-customer-list',
			'awcomCustomerListVars',
			array(
				'i18n' => array(
					'confirm_delete_single' => __( 'Delete this customer permanently? This cannot be undone.', 'alynt-wc-customer-order-manager' ),
					/* translators: %d: number of selected customers. */
					'confirm_delete_bulk'   => __( 'Delete %d customers permanently? This cannot be undone.', 'alynt-wc-customer-order-manager' ),
				),
			)
		);
	}

	/**
	 * Register the Customer Manager top-level menu and its subpages.
	 *
	 * Adds "Customer Manager" to the WordPress admin menu at position 56,
	 * with an "Add New Customer" submenu and a hidden "Edit Customer" page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		if ( ! Security::user_can_access() ) {
			return;
		}

		// phpcs:disable WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability.
		$main_page_hook = add_menu_page(
			__( 'Customer Manager', 'alynt-wc-customer-order-manager' ),
			__( 'Customer Manager', 'alynt-wc-customer-order-manager' ),
			'manage_woocommerce',
			'alynt-wc-customer-order-manager',
			array( $this, 'render_main_page' ),
			'dashicons-groups',
			56
		);

		add_action( 'load-' . $main_page_hook, array( $this, 'handle_bulk_actions' ) );

		add_submenu_page(
			'alynt-wc-customer-order-manager',
			__( 'Add New Customer', 'alynt-wc-customer-order-manager' ),
			__( 'Add New Customer', 'alynt-wc-customer-order-manager' ),
			'manage_woocommerce',
			'alynt-wc-customer-order-manager-add',
			array( $this, 'render_add_page' )
		);

		add_submenu_page(
			null,
			__( 'Edit Customer', 'alynt-wc-customer-order-manager' ),
			__( 'Edit Customer', 'alynt-wc-customer-order-manager' ),
			'manage_woocommerce',
			'alynt-wc-customer-order-manager-edit',
			array( $this, 'render_edit_page' )
		);
		// phpcs:enable
	}

	/**
	 * Render the Customer Manager main list page.
	 *
	 * Instantiates CustomerListTable, prepares its items, and outputs the
	 * search box, bulk action form, and table HTML.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_main_page() {
		if ( ! Security::user_can_access() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'alynt-wc-customer-order-manager' ) );
		}

		require_once AWCOM_PLUGIN_PATH . 'includes/class-customer-list-table.php';
		$customer_table = new CustomerListTable();
		$customer_table->prepare_items();
		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading redirect notice query args only. */
		$deleted_count       = isset( $_GET['deleted'] ) ? absint( wp_unslash( $_GET['deleted'] ) ) : 0;
		$delete_failed_count = isset( $_GET['delete_failed'] ) ? absint( wp_unslash( $_GET['delete_failed'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The decoded value is sanitized immediately for display.
		$error_message = isset( $_GET['error'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['error'] ) ) ) : '';
		/* phpcs:enable */
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Customer Manager', 'alynt-wc-customer-order-manager' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=alynt-wc-customer-order-manager-add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New Customer', 'alynt-wc-customer-order-manager' ); ?>
			</a>

			<?php
			if ( $deleted_count > 0 ) {
				echo '<div class="notice notice-success is-dismissible" role="status"><p>' .
					sprintf(
						esc_html(
							/* translators: %d: number of deleted customers. */
							_n( '%d customer deleted.', '%d customers deleted.', $deleted_count, 'alynt-wc-customer-order-manager' )
						),
						esc_html( number_format_i18n( $deleted_count ) )
					) .
					'</p></div>';
			}

			if ( $delete_failed_count > 0 ) {
				echo '<div class="notice notice-warning is-dismissible" role="alert"><p>' .
					sprintf(
						esc_html(
							/* translators: %d: number of customers that could not be deleted. */
							_n(
								'%d customer could not be deleted. Please try again.',
								'%d customers could not be deleted. Please try again.',
								$delete_failed_count,
								'alynt-wc-customer-order-manager'
							)
						),
						esc_html( number_format_i18n( $delete_failed_count ) )
					) .
					'</p></div>';
			}

			if ( '' !== $error_message ) {
				echo '<div class="notice notice-error is-dismissible" role="alert"><p>' .
					esc_html( $error_message ) . '</p></div>';
			}
			?>

			<form method="post">
				<?php
				$customer_table->search_box( esc_html__( 'Search Customers', 'alynt-wc-customer-order-manager' ), 'customer-search' );
				wp_nonce_field( 'bulk-customers', 'alynt-wc-customer-order-manager-nonce' );
				$customer_table->display();
				?>
			</form>
		</div>
		<?php
	}
}
