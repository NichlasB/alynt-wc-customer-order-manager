<?php
/**
 * Plugin Name:       Alynt WooCommerce Customer and Order Manager
 * Description:       Provides a customer management interface for WooCommerce customers and orders.
 * Version:           1.0.6
 * Author:            Alynt
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Requires Plugins:  woocommerce
 * WC requires at least: 4.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       alynt-wc-customer-order-manager
 * Domain Path:       /languages
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

defined( 'ABSPATH' ) || exit;

// Plugin Update Checker.
$composer_autoloader_path = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $composer_autoloader_path ) ) {
	require_once $composer_autoloader_path;
} elseif ( is_admin() ) {
	add_action( 'admin_notices', 'awcom_render_missing_update_checker_notice' );
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
	$my_update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/NichlasB/alynt-wc-customer-order-manager',
		__FILE__,
		'alynt-wc-customer-order-manager'
	);

	// Set the branch that contains the stable release.
	$my_update_checker->setBranch( 'main' );
	// Enable GitHub releases.
	$my_update_checker->getVcsApi()->enableReleaseAssets();
}

// Define plugin constants.
define( 'AWCOM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AWCOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AWCOM_VERSION', '1.0.6' );

/**
 * Display a warning when the plugin update checker dependencies are missing.
 *
 * @since 1.0.6
 *
 * @return void
 */
function awcom_render_missing_update_checker_notice() {
	?>
	<div class="notice notice-warning">
		<p><?php esc_html_e( 'The plugin update checker could not be loaded because plugin dependencies are missing. GitHub update checks may be unavailable until they are restored.', 'alynt-wc-customer-order-manager' ); ?></p>
	</div>
	<?php
}

/**
 * Display setup warnings that were captured during activation.
 *
 * @since 1.0.6
 *
 * @return void
 */
function awcom_render_setup_notices() {
	$setup_notices = get_transient( 'awcom_setup_notices' );

	if ( empty( $setup_notices ) || ! is_array( $setup_notices ) ) {
		return;
	}

	delete_transient( 'awcom_setup_notices' );

	foreach ( $setup_notices as $notice ) {
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'awcom_render_setup_notices' );

/**
 * Display a warning when WooCommerce is not active.
 *
 * @since 1.0.6
 *
 * @return void
 */
function awcom_render_missing_woocommerce_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Alynt WC Customer Order Manager requires WooCommerce to be installed and activated.', 'alynt-wc-customer-order-manager' ); ?></p>
	</div>
	<?php
}

// Autoloader for plugin classes.
spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'AlyntWCOrderManager\\';
		$base_dir = AWCOM_PLUGIN_PATH . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialize the plugin after all plugins are loaded.
 *
 * Loads the text domain, requires core class files, and instantiates
 * all plugin classes. Admin-only classes are loaded conditionally.
 *
 * @since 1.0.0
 *
 * @return void
 */
function awcom_init() {
	// Load the text domain for translations.
	load_plugin_textdomain( 'alynt-wc-customer-order-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', 'awcom_render_missing_woocommerce_notice' );
		}

		return;
	}

	// Include the required files.
	require_once AWCOM_PLUGIN_PATH . 'includes/class-pricing-rule-lookup.php';
	require_once AWCOM_PLUGIN_PATH . 'includes/class-security.php';
	require_once AWCOM_PLUGIN_PATH . 'includes/class-order-payment-access.php';
	require_once AWCOM_PLUGIN_PATH . 'includes/class-order-handler.php';

	awcom_run_upgrade_tasks();

	// Initialize the classes that work on both frontend and admin.
	new \AlyntWCOrderManager\OrderHandler();
	new \AlyntWCOrderManager\OrderPaymentAccess();

	// Initialize admin pages only in admin.
	if ( is_admin() ) {
		require_once AWCOM_PLUGIN_PATH . 'includes/class-admin-pages.php';
		require_once AWCOM_PLUGIN_PATH . 'includes/class-order-interface.php';
		require_once AWCOM_PLUGIN_PATH . 'includes/class-payment-link.php';

		new \AlyntWCOrderManager\AdminPages();
		new \AlyntWCOrderManager\OrderInterface();
		new \AlyntWCOrderManager\PaymentLink();
	}
}
add_action( 'plugins_loaded', 'awcom_init' );

/**
 * Run one-time upgrade tasks when the plugin version changes.
 *
 * @since 1.0.6
 *
 * @return void
 */
function awcom_run_upgrade_tasks() {
	$stored_version = (string) get_option( 'awcom_version', '' );

	if ( AWCOM_VERSION === $stored_version ) {
		return;
	}

	\AlyntWCOrderManager\OrderPaymentAccess::grant_payment_capabilities();
	update_option( 'awcom_version', AWCOM_VERSION, false );
}

if ( ! function_exists( 'awcom_get_order_edit_url' ) ) {
	function awcom_get_order_edit_url( $order_id ) {
		$order_id = absint( $order_id );

		if ( $order_id <= 0 ) {
			return admin_url( 'edit.php?post_type=shop_order' );
		}

		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
		}

		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}
}

/**
 * Run on plugin activation.
 *
 * Creates the plugin upload directory and a blocking index.php file
 * to prevent direct directory listing. Also flushes rewrite rules.
 *
 * @since 1.0.0
 *
 * @return void
 */
register_activation_hook( __FILE__, 'awcom_activate' );
/**
 * Handle plugin activation tasks.
 *
 * @since 1.0.0
 *
 * @return void
 */
function awcom_activate() {
	// Create the necessary directories.
	$upload_dir    = wp_upload_dir();
	$plugin_dir    = $upload_dir['basedir'] . '/alynt-wc-customer-order-manager';
	$setup_notices = array();

	if ( ! empty( $upload_dir['error'] ) ) {
		$setup_notices[] = __( 'The plugin upload directory could not be prepared. Check your WordPress uploads configuration if generated files do not work as expected.', 'alynt-wc-customer-order-manager' );
	} elseif ( ! file_exists( $plugin_dir ) && ! wp_mkdir_p( $plugin_dir ) ) {
		$setup_notices[] = __( 'The plugin upload directory could not be created. Check file permissions if generated files do not work as expected.', 'alynt-wc-customer-order-manager' );
	}

	// Create an index.php file in the plugin directory for security.
	if ( file_exists( $plugin_dir ) && ! file_exists( $plugin_dir . '/index.php' ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Activation bootstrap writes a static guard file once.
		if ( ! is_writable( $plugin_dir ) || false === file_put_contents( $plugin_dir . '/index.php', "<?php\n// Silence is golden." ) ) {
			$setup_notices[] = __( 'The plugin security file could not be created in the uploads directory. Check folder permissions if directory protection is required.', 'alynt-wc-customer-order-manager' );
		}
	}

	if ( ! empty( $setup_notices ) ) {
		set_transient( 'awcom_setup_notices', $setup_notices, 5 * MINUTE_IN_SECONDS );
	}

	require_once AWCOM_PLUGIN_PATH . 'includes/class-order-payment-access.php';
	\AlyntWCOrderManager\OrderPaymentAccess::grant_payment_capabilities();
	update_option( 'awcom_version', AWCOM_VERSION, false );

	flush_rewrite_rules();
}

/**
 * Run on plugin deactivation.
 *
 * Flushes rewrite rules to clean up any registered endpoints.
 *
 * @since 1.0.0
 *
 * @return void
 */
register_deactivation_hook( __FILE__, 'awcom_deactivate' );
/**
 * Handle plugin deactivation tasks.
 *
 * @since 1.0.0
 *
 * @return void
 */
function awcom_deactivate() {
	flush_rewrite_rules();
}

/**
 * Add a "Manage Customers" link to the plugin list table action links.
 *
 * @since 1.0.0
 *
 * @param array $links Existing plugin action links.
 * @return array Modified action links with the Manage Customers link prepended.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'awcom_add_plugin_action_links' );
/**
 * Add plugin action links on the Plugins screen.
 *
 * @since 1.0.0
 *
 * @param array $links Existing plugin action links.
 * @return array
 */
function awcom_add_plugin_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=alynt-wc-customer-order-manager' ) . '">' . __( 'Manage Customers', 'alynt-wc-customer-order-manager' ) . '</a>',
	);
	return array_merge( $plugin_links, $links );
}

/**
 * Add a documentation link to the plugin row meta on the Plugins screen.
 *
 * @since 1.0.1
 *
 * @param array  $links Existing row meta links.
 * @param string $file  Plugin basename being evaluated.
 * @return array Modified row meta links.
 */
add_filter( 'plugin_row_meta', 'awcom_plugin_row_meta', 10, 2 );
/**
 * Add plugin row meta links on the Plugins screen.
 *
 * @since 1.0.1
 *
 * @param array  $links Existing row meta links.
 * @param string $file  Plugin basename being evaluated.
 * @return array
 */
function awcom_plugin_row_meta( $links, $file ) {
	if ( plugin_basename( __FILE__ ) === $file ) {
		$row_meta = array(
			'docs' => '<a href="https://github.com/NichlasB/alynt-wc-customer-order-manager" target="_blank">' . __( 'Documentation', 'alynt-wc-customer-order-manager' ) . '</a>',
		);
		return array_merge( $links, $row_meta );
	}
	return $links;
}
