<?php
// If uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Delete plugin options
delete_option('wc_customer_manager_settings');

// Clean up any additional options
$option_names = array(
    'wc_customer_manager_version',
    'wc_customer_manager_db_version'
);

foreach ($option_names as $option) {
    delete_option($option);
}

// Clear any transients
delete_transient('wc_customer_manager_cache');

// If using custom tables, you might want to remove them
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wc_customer_manager_data");

// Clear any scheduled events
wp_clear_scheduled_hook('wc_customer_manager_scheduled_task');

// Remove any uploaded files or directories if they exist
$upload_dir = wp_upload_dir();
$plugin_upload_dir = $upload_dir['basedir'] . '/wc-customer-manager';
if (is_dir($plugin_upload_dir)) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
    require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
    $filesystem = new WP_Filesystem_Direct(null);
    $filesystem->rmdir($plugin_upload_dir, true);
}