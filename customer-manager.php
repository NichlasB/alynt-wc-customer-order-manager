<?php
/**
 * Plugin Name: Customer and Order Manager for WooCommerce
 * Description: Provides a customer management interface for WooCommerce customers and orders.
 * Version: 1.0.1
 * Author: CueFox
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
        'https://github.com/NichlasB/woocommerce-customer-manager',
        __FILE__,
        'woocommerce-customer-manager'
    );
    
    // Set the branch that contains the stable release
    $myUpdateChecker->setBranch('main');
    // Enable GitHub releases
    $myUpdateChecker->getVcsApi()->enableReleaseAssets();
}

// Define plugin constants
define('CM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CM_VERSION', '1.0.1');

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Customer Manager requires WooCommerce to be installed and activated.', 'customer-manager'); ?></p>
        </div>
        <?php
    });
    return;
}

// Autoloader for plugin classes
spl_autoload_register(function($class) {
    $prefix = 'CustomerManager\\';
    $base_dir = CM_PLUGIN_PATH . 'includes/';

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
function cm_init() {
    // Load text domain for translations
    load_plugin_textdomain('customer-manager', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Initialize admin pages
    if (is_admin()) {
        // Include required files
        require_once CM_PLUGIN_PATH . 'includes/class-security.php';
        require_once CM_PLUGIN_PATH . 'includes/class-admin-pages.php';
        require_once CM_PLUGIN_PATH . 'includes/class-order-interface.php';
        require_once CM_PLUGIN_PATH . 'includes/class-order-handler.php';
        
        new \CustomerManager\AdminPages();
        new \CustomerManager\OrderInterface();
        new \CustomerManager\OrderHandler();
    }
}
add_action('plugins_loaded', 'cm_init');

// Activation hook
register_activation_hook(__FILE__, 'cm_activate');
function cm_activate() {
    // Create necessary directories
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/customer-manager';
    
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
register_deactivation_hook(__FILE__, 'cm_deactivate');
function cm_deactivate() {
    flush_rewrite_rules();
}

// Add plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cm_add_plugin_action_links');
function cm_add_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=customer-manager') . '">' . __('Manage Customers', 'customer-manager') . '</a>'
    );
    return array_merge($plugin_links, $links);
}

// Add documentation link in plugin meta
add_filter('plugin_row_meta', 'cm_plugin_row_meta', 10, 2);
function cm_plugin_row_meta($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $row_meta = array(
            'docs' => '<a href="#" target="_blank">' . __('Documentation', 'customer-manager') . '</a>'
        );
        return array_merge($links, $row_meta);
    }
    return $links;
}