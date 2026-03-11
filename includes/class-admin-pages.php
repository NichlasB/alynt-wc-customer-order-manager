<?php // phpcs:disable WordPress.Files.FileName -- Legacy file naming retained for compatibility.
/**
 * Admin pages controller for customer management.
 *
 * Registers admin menu pages, handles form submissions for creating and
 * editing customers, processes bulk actions, and manages AJAX endpoints
 * for customer notes and email templates.
 *
 * @package    Alynt_WC_Customer_Order_Manager
 * @subpackage Alynt_WC_Customer_Order_Manager/includes
 * @since      1.0.0
 */

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/traits/trait-admin-pages-menu-main.php';
require_once __DIR__ . '/traits/trait-admin-pages-render-edit.php';
require_once __DIR__ . '/traits/trait-admin-pages-render-add.php';
require_once __DIR__ . '/traits/trait-admin-pages-notes.php';
require_once __DIR__ . '/traits/trait-admin-pages-email.php';
require_once __DIR__ . '/traits/trait-admin-pages-actions-create-bulk.php';
require_once __DIR__ . '/traits/trait-admin-pages-actions-edit.php';

/**
 * Provides the admin interface for managing WooCommerce customers.
 *
 * @since 1.0.0
 */
class AdminPages {
	use AdminPagesMenuMainTrait;
	use AdminPagesRenderEditTrait;
	use AdminPagesRenderAddTrait;
	use AdminPagesNotesTrait;
	use AdminPagesEmailTrait;
	use AdminPagesActionsCreateBulkTrait;
	use AdminPagesActionsEditTrait;

	/**
	 * Register all admin hooks for menus, form processing, scripts, and AJAX handlers.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_post_awcom_create_customer', array( $this, 'handle_customer_form_submission' ) );
		add_action( 'admin_post_awcom_edit_customer', array( $this, 'handle_customer_edit_submission' ) );
		add_action( 'admin_post_awcom_send_login_details', array( $this, 'handle_customer_edit_submission' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_customer_list_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_customer_notes_scripts' ) );
		add_action( 'wp_ajax_awcom_add_customer_note', array( $this, 'handle_add_customer_note' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
		add_action( 'wp_ajax_awcom_save_login_email_template', array( $this, 'save_login_email_template' ) );
		add_action( 'wp_ajax_awcom_edit_customer_note', array( $this, 'handle_edit_customer_note' ) );
		add_action( 'wp_ajax_awcom_delete_customer_note', array( $this, 'handle_delete_customer_note' ) );
		add_action( 'delete_user', array( $this, 'cleanup_customer_group_assignment' ) );
		add_action( 'wpmu_delete_user', array( $this, 'cleanup_customer_group_assignment' ) );
	}

	/**
	 * Redirect to an admin page handled by this plugin.
	 *
	 * @since 1.0.6
	 *
	 * @param string $page_slug Menu page slug.
	 * @param array  $args      Additional query arguments.
	 * @return void
	 */
	protected function redirect_to_admin_page( $page_slug, $args = array() ) {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array( 'page' => $page_slug ),
					$args
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Persist form input briefly so it can be restored after a redirect.
	 *
	 * @since 1.0.6
	 *
	 * @param string $key  Transient key prefix.
	 * @param array  $data Form values to store.
	 * @return void
	 */
	protected function store_form_state( $key, array $data ) {
		set_transient( $key . '_' . get_current_user_id(), $data, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Retrieve and clear stored form input after a redirect.
	 *
	 * @since 1.0.6
	 *
	 * @param string $key Transient key prefix.
	 * @return array
	 */
	protected function pull_form_state( $key ) {
		$transient_key = $key . '_' . get_current_user_id();
		$data          = get_transient( $transient_key );

		if ( false !== $data ) {
			delete_transient( $transient_key );
		}

		return is_array( $data ) ? $data : array();
	}

	public function cleanup_customer_group_assignment( $user_id ) {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 || ! PricingRuleLookup::get_customer_group_id( $user_id ) ) {
			return;
		}

		global $wpdb;
		$user_groups_table = $wpdb->prefix . 'user_groups';

		$wpdb->delete(
			$user_groups_table,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);
	}

	/**
	 * Log plugin errors without exposing technical details in the UI.
	 *
	 * @since 1.0.6
	 *
	 * @param string $message Error details for debugging.
	 * @return void
	 */
	protected function log_admin_error( $message ) {
		// Logging removed in pre-release cleanup.
	}
}
