<?php
/**
 * Plugin uninstall handler.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

// If uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

/**
 * Remove plugin data for the current site.
 *
 * @return void
 */
function awcom_uninstall_cleanup_site_data() {
	global $wpdb;

	// Delete plugin options.
	delete_option( 'alynt_wc_customer_order_manager_settings' );
	delete_option( 'awcom_login_email_template' );
	delete_option( 'awcom_version' );

	// Clean up any additional options.
	$option_names = array(
		'alynt_wc_customer_order_manager_version',
		'alynt_wc_customer_order_manager_db_version',
		'awcom_enable_customer_order_editing',
	);

	foreach ( $option_names as $option ) {
		delete_option( $option );
	}

	// Clear any transients.
	delete_transient( 'alynt_wc_customer_order_manager_cache' );
	delete_transient( 'awcom_setup_notices' );

	$transient_patterns = array(
		'_transient_awcom_ship_%',
		'_transient_timeout_awcom_ship_%',
		'_transient_awcom_add_customer_form_%',
		'_transient_timeout_awcom_add_customer_form_%',
		'_transient_awcom_edit_customer_form_%',
		'_transient_timeout_awcom_edit_customer_form_%',
	);

	foreach ( $transient_patterns as $pattern ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);
	}

	// Remove plugin-owned metadata from shared WooCommerce tables.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup removes plugin-owned rows from a shared WooCommerce table.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key IN ( %s, %s, %s, %s )",
			'_awcom_customer_group',
			'_awcom_custom_price',
			'_awcom_custom_subtotal_price',
			'_awcom_discount_description'
		)
	);

	delete_post_meta_by_key( '_awcom_has_custom_pricing' );
	delete_post_meta_by_key( '_awcom_pricing_locked' );
	delete_post_meta_by_key( '_awcom_locked_total' );

	$wc_orders_meta_table  = $wpdb->prefix . 'wc_orders_meta';
	$wc_orders_meta_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$wpdb->esc_like( $wc_orders_meta_table )
		)
	);

	if ( $wc_orders_meta_exists === $wc_orders_meta_table ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wc_orders_meta_table} WHERE meta_key IN ( %s, %s, %s )",
				'_awcom_has_custom_pricing',
				'_awcom_pricing_locked',
				'_awcom_locked_total'
			)
		);
	}

	// Remove plugin-managed customer notes user meta.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ( %s, %s )",
			'_awcom_customer_notes',
			'customer_notes'
		)
	);

	// Remove capability grants added by this plugin.
	$roles = array( 'administrator', 'shop_manager' );
	foreach ( $roles as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->remove_cap( 'pay_for_order' );
		}
	}

	// Clear legacy scheduled events.
	wp_clear_scheduled_hook( 'alynt_wc_customer_order_manager_scheduled_task' );

	// Remove any uploaded files or directories if they exist.
	$upload_dir        = wp_upload_dir();
	$plugin_upload_dir = $upload_dir['basedir'] . '/alynt-wc-customer-order-manager';
	if ( is_dir( $plugin_upload_dir ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$filesystem = new WP_Filesystem_Direct( null );
		$filesystem->rmdir( $plugin_upload_dir, true );
	}
}

if ( function_exists( 'current_user_can' ) && ! current_user_can( 'activate_plugins' ) ) {
	return;
}

if ( is_multisite() ) {
	$site_ids        = get_sites(
		array(
			'fields' => 'ids',
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		awcom_uninstall_cleanup_site_data();
		restore_current_blog();
	}
} else {
	awcom_uninstall_cleanup_site_data();
}
