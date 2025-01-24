<?php
/**
 * Plugin Name: Alynt WooCommerce Customer and Order Manager
 * Description: Provides a customer management interface for WooCommerce customers and orders.
 * Version: 1.0.2
 * Author: Alynt
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin Update Checker
require_once __DIR__ . '/vendor/autoload.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/NichlasB/alynt-wc-customer-order-manager',
        __FILE__,
        'alynt-wc-customer-order-manager'
    );
    
    // Set the branch that contains the stable release
    $myUpdateChecker->setBranch('main');
    // Enable GitHub releases
    $myUpdateChecker->getVcsApi()->enableReleaseAssets();
}

// Define plugin constants
define('AWCOM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AWCOM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AWCOM_VERSION', '1.0.2');

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Alynt WC Customer Order Manager requires WooCommerce to be installed and activated.', 'alynt-wc-customer-order-manager'); ?></p>
        </div>
        <?php
    });
    return;
}

// Autoloader for plugin classes
spl_autoload_register(function($class) {
    $prefix = 'AlyntWCOrderManager\\';
    $base_dir = AWCOM_PLUGIN_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function awcom_init() {
    // Load text domain for translations
    load_plugin_textdomain('alynt-wc-customer-order-manager', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Initialize admin pages
    if (is_admin()) {
        // Include required files
        require_once AWCOM_PLUGIN_PATH . 'includes/class-security.php';
        require_once AWCOM_PLUGIN_PATH . 'includes/class-admin-pages.php';
        require_once AWCOM_PLUGIN_PATH . 'includes/class-order-interface.php';
        require_once AWCOM_PLUGIN_PATH . 'includes/class-payment-link.php';
        require_once AWCOM_PLUGIN_PATH . 'includes/class-order-handler.php';
        
        new \AlyntWCOrderManager\AdminPages();
        new \AlyntWCOrderManager\OrderInterface();
        $payment_link = new \AlyntWCOrderManager\PaymentLink();
        new \AlyntWCOrderManager\OrderHandler();
    }
}
add_action('plugins_loaded', 'awcom_init');

// Activation hook
register_activation_hook(__FILE__, 'awcom_activate');
function awcom_activate() {
    // Create necessary directories
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/alynt-wc-customer-order-manager';
    
    if (!file_exists($plugin_dir)) {
        wp_mkdir_p($plugin_dir);
    }

    // Create index.php file in the plugin directory for security
    if (!file_exists($plugin_dir . '/index.php')) {
        $handle = @fopen($plugin_dir . '/index.php', 'w');
        if ($handle) {
            fwrite($handle, "<?php\n// Silence is golden.");
            fclose($handle);
        }
    }

    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'awcom_deactivate');
function awcom_deactivate() {
    flush_rewrite_rules();
}

// Add plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'awcom_add_plugin_action_links');
function awcom_add_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=alynt-wc-customer-order-manager') . '">' . __('Manage Customers', 'alynt-wc-customer-order-manager') . '</a>'
    );
    return array_merge($plugin_links, $links);
}

// Add documentation link in plugin meta
add_filter('plugin_row_meta', 'awcom_plugin_row_meta', 10, 2);
function awcom_plugin_row_meta($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $row_meta = array(
            'docs' => '<a href="#" target="_blank">' . __('Documentation', 'alynt-wc-customer-order-manager') . '</a>'
        );
        return array_merge($links, $row_meta);
    }
    return $links;
}