<?php
// If uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;

// Delete plugin options
delete_option('alynt_wc_customer_order_manager_settings');

// Clean up any additional options
$option_names = array(
    'alynt_wc_customer_order_manager_version',
    'alynt_wc_customer_order_manager_db_version',
    'awcom_enable_customer_order_editing'
);

foreach ($option_names as $option) {
    delete_option($option);
}

// Clear any transients
delete_transient('alynt_wc_customer_order_manager_cache');

// Drop custom database tables for customer groups and pricing
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}user_groups");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}customer_groups");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pricing_rules");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}rule_products");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}rule_categories");

// Remove customer group metadata from order items
$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_customer_group'");

// Clear any scheduled events
wp_clear_scheduled_hook('alynt_wc_customer_order_manager_scheduled_task');

// Remove any uploaded files or directories if they exist
$upload_dir = wp_upload_dir();
$plugin_upload_dir = $upload_dir['basedir'] . '/alynt-wc-customer-order-manager';
if (is_dir($plugin_upload_dir)) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
    require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
    $filesystem = new WP_Filesystem_Direct(null);
    $filesystem->rmdir($plugin_upload_dir, true);
}