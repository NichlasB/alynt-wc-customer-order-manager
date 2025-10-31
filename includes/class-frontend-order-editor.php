<?php
namespace AlyntWCOrderManager;

class FrontendOrderEditor {
    
    public function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_order_edit_request'));
        add_action('template_redirect', array($this, 'intercept_order_pay_page'), 5);
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_frontend_assets'));
        add_action('wp_ajax_awcom_frontend_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_nopriv_awcom_frontend_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_awcom_frontend_get_shipping_methods', array($this, 'ajax_get_shipping_methods'));
        add_action('wp_ajax_nopriv_awcom_frontend_get_shipping_methods', array($this, 'ajax_get_shipping_methods'));
        add_action('wp_ajax_awcom_frontend_update_order', array($this, 'ajax_update_order'));
        add_action('wp_ajax_nopriv_awcom_frontend_update_order', array($this, 'ajax_update_order'));
        add_action('wp_ajax_awcom_frontend_create_additional_order', array($this, 'ajax_create_additional_order'));
        add_action('wp_ajax_nopriv_awcom_frontend_create_additional_order', array($this, 'ajax_create_additional_order'));
        add_filter('woocommerce_order_pay_url', array($this, 'modify_order_pay_url'), 10, 2);
    }

    /**
     * Add rewrite rules for order editing
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^order-edit/([0-9]+)/?$',
            'index.php?awcom_order_edit=1&order_id=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%awcom_order_edit%', '([^&]+)');
        add_rewrite_tag('%order_id%', '([0-9]+)');
    }

    /**
     * Modify order pay URL to redirect to our edit page if editing is enabled
     */
    public function modify_order_pay_url($pay_url, $order) {
        // Check if this is a direct request to proceed to payment (bypass editing)
        if (isset($_GET['skip_edit']) && $_GET['skip_edit'] === '1') {
            return $pay_url;
        }
        
        // Check if order editing is enabled
        $enable_editing = get_option('awcom_enable_customer_order_editing', 'yes');
        
        if ($enable_editing === 'yes' && $order->get_status() === 'pending') {
            $order_id = $order->get_id();
            $order_key = $order->get_order_key();
            
            return home_url("/order-edit/{$order_id}/?key={$order_key}");
        }
        
        return $pay_url;
    }

    /**
     * Intercept order pay page and redirect to edit page if conditions are met
     */
    public function intercept_order_pay_page() {
        global $wp_query;
        
        // Check if this is the order-pay page
        if (is_wc_endpoint_url('order-pay')) {
            // Don't intercept if skip_edit parameter is set
            if (isset($_GET['skip_edit']) && $_GET['skip_edit'] === '1') {
                return;
            }
            
            $order_id = absint(get_query_var('order-pay'));
            $order_key = sanitize_text_field($_GET['key'] ?? '');
            
            if ($order_id && $order_key) {
                $order = wc_get_order($order_id);
                
                if ($order && $order->get_order_key() === $order_key) {
                    $enable_editing = get_option('awcom_enable_customer_order_editing', 'yes');
                    
                    if ($enable_editing === 'yes' && $order->get_status() === 'pending') {
                        $redirect_url = home_url("/order-edit/{$order_id}/?key={$order_key}");
                        wp_redirect($redirect_url);
                        exit;
                    }
                }
            }
        }
    }

    /**
     * Handle order edit requests
     */
    public function handle_order_edit_request() {
        if (!get_query_var('awcom_order_edit')) {
            return;
        }

        $order_id = get_query_var('order_id');
        $order_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

        if (!$order_id || !$order_key) {
            wp_die(__('Invalid order parameters.', 'alynt-wc-customer-order-manager'));
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key) {
            wp_die(__('Invalid order or order key.', 'alynt-wc-customer-order-manager'));
        }

        // Check if order can be edited
        $allowed_statuses = array('pending', 'processing', 'on-hold');
        if (!in_array($order->get_status(), $allowed_statuses)) {
            wp_redirect($order->get_checkout_payment_url());
            exit;
        }

        // Load the template
        $this->load_order_edit_template($order);
        exit;
    }

    /**
     * Check if we're on an order edit page and enqueue assets
     */
    public function maybe_enqueue_frontend_assets() {
        // Check if we're on an order edit page or if the query var is set
        if (get_query_var('awcom_order_edit') || 
            (isset($_GET['awcom_order_edit']) && $_GET['awcom_order_edit'] == '1') ||
            (strpos($_SERVER['REQUEST_URI'], '/order-edit/') !== false)) {
            
            error_log('AWCOM Debug - Enqueuing frontend assets');
            $this->enqueue_frontend_assets();
        }
    }

    /**
     * Load the order editing template
     */
    private function load_order_edit_template($order) {
        // Start output buffering
        ob_start();
        
        // Get header
        get_header();
        
        // Load our template content
        $this->render_order_edit_content($order);
        
        
        // Get footer
        get_footer();
        
        // Output the complete page
        echo ob_get_clean();
    }

    /**
     * Enqueue frontend assets
     */
    private function enqueue_frontend_assets() {
        error_log('AWCOM Debug - enqueue_frontend_assets called');
        
        // Enqueue WooCommerce styles
        wp_enqueue_style('woocommerce-general');
        wp_enqueue_style('woocommerce-layout');
        wp_enqueue_style('woocommerce-smallscreen');
        
        // Enqueue Select2
        wp_enqueue_style('select2', WC()->plugin_url() . '/assets/css/select2.css', array(), '4.0.3');
        wp_enqueue_script('select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array('jquery'), '4.0.3', true);

        // Enqueue accounting.js
        wp_enqueue_script('accounting', WC()->plugin_url() . '/assets/js/accounting/accounting.min.js', array('jquery'), '0.4.2', true);

        // Enqueue our custom styles and scripts
        $css_url = AWCOM_PLUGIN_URL . 'assets/css/frontend-order-editor.css';
        $js_url = AWCOM_PLUGIN_URL . 'assets/js/frontend-order-editor.js';
        
        error_log('AWCOM Debug - CSS URL: ' . $css_url);
        error_log('AWCOM Debug - JS URL: ' . $js_url);
        
        wp_enqueue_style(
            'awcom-frontend-order-editor', 
            $css_url,
            array('select2', 'woocommerce-general'),
            AWCOM_VERSION
        );

        wp_enqueue_script(
            'awcom-frontend-order-editor',
            $js_url,
            array('jquery', 'select2', 'accounting'),
            AWCOM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('awcom-frontend-order-editor', 'awcomFrontendVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('awcom-frontend-order-editor'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'currency_format' => array(
                'symbol' => get_woocommerce_currency_symbol(),
                'decimal_separator' => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
                'decimals' => wc_get_price_decimals(),
                'price_format' => get_woocommerce_price_format()
            ),
            'i18n' => array(
                'search_products' => __('Search for a product...', 'alynt-wc-customer-order-manager'),
                'no_products' => __('No products found', 'alynt-wc-customer-order-manager'),
                'remove_item' => __('Remove item', 'alynt-wc-customer-order-manager'),
                'calculating' => __('Calculating...', 'alynt-wc-customer-order-manager'),
                'no_shipping' => __('No shipping methods available', 'alynt-wc-customer-order-manager'),
                'shipping_error' => __('Error calculating shipping methods', 'alynt-wc-customer-order-manager'),
                'no_items' => __('Please add at least one item to the order.', 'alynt-wc-customer-order-manager'),
                'no_shipping_selected' => __('Please select a shipping method.', 'alynt-wc-customer-order-manager'),
                'update_success' => __('Order updated successfully!', 'alynt-wc-customer-order-manager'),
                'update_error' => __('Error updating order. Please try again.', 'alynt-wc-customer-order-manager'),
                'proceed_to_payment' => __('Proceed to Payment', 'alynt-wc-customer-order-manager'),
                'update_order' => __('Update Order', 'alynt-wc-customer-order-manager')
            )
        ));
    }

    /**
     * Render the order editing content
     */
    private function render_order_edit_content($order) {
        $customer_id = $order->get_customer_id();
        $order_items = $order->get_items();
        $is_paid_order = in_array($order->get_status(), array('processing', 'on-hold'));
        
        // Add CSS and JS inline since wp_enqueue doesn't work with custom routing
        ?>
        <style type="text/css">
        <?php echo file_get_contents(AWCOM_PLUGIN_PATH . 'assets/css/frontend-order-editor.css'); ?>
        </style>
        
        <div class="woocommerce">
            <div class="awcom-frontend-order-editor">
                <div class="container">
                    <?php if ($is_paid_order): ?>
                        <h1><?php printf(__('Add Items to Order #%s', 'alynt-wc-customer-order-manager'), $order->get_order_number()); ?></h1>
                        <div class="awcom-paid-order-notice">
                            <p><?php _e('This order has already been paid. You can add additional items which will create a separate order for payment.', 'alynt-wc-customer-order-manager'); ?></p>
                        </div>
                    <?php else: ?>
                        <h1><?php printf(__('Edit Order #%s', 'alynt-wc-customer-order-manager'), $order->get_order_number()); ?></h1>
                    <?php endif; ?>
                    
                    <div class="awcom-order-edit-notices"></div>
                    
                    <form id="awcom-frontend-order-form" data-order-id="<?php echo esc_attr($order->get_id()); ?>" data-order-key="<?php echo esc_attr($order->get_order_key()); ?>">
                        
                        <div class="awcom-order-sections">
                            <!-- Order Items Section -->
                            <div class="awcom-section awcom-items-section">
                                <h2><?php _e('Order Items', 'alynt-wc-customer-order-manager'); ?></h2>
                                
                                <div class="awcom-product-search">
                                    <label for="awcom-add-product"><?php _e('Add Product:', 'alynt-wc-customer-order-manager'); ?></label>
                                    <select id="awcom-add-product" style="width: 100%;">
                                        <option></option>
                                    </select>
                                </div>
                                
                                <div class="awcom-order-items-wrapper">
                                    <table class="awcom-order-items shop_table">
                                        <thead>
                                            <tr>
                                                <th class="product-name"><?php _e('Product', 'alynt-wc-customer-order-manager'); ?></th>
                                                <th class="product-quantity"><?php _e('Quantity', 'alynt-wc-customer-order-manager'); ?></th>
                                                <th class="product-price"><?php _e('Price', 'alynt-wc-customer-order-manager'); ?></th>
                                                <th class="product-total"><?php _e('Total', 'alynt-wc-customer-order-manager'); ?></th>
                                                <th class="product-remove"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($order_items as $item_id => $item) : 
                                                $product = $item->get_product();
                                                if (!$product) continue;
                                                
                                                $quantity = $item->get_quantity();
                                                $total = $item->get_total();
                                                $price = $total / $quantity;
                                            ?>
                                            <tr data-product-id="<?php echo esc_attr($product->get_id()); ?>" data-price="<?php echo esc_attr($price); ?>">
                                                <td class="product-name">
                                                    <?php echo esc_html($product->get_name()); ?>
                                                    <?php
                                                    // Add stock info
                                                    $stock_quantity = $product->get_stock_quantity();
                                                    $stock_status = $product->get_stock_status();
                                                    $manage_stock = $product->get_manage_stock();
                                                    
                                                    if ($manage_stock && $stock_quantity !== null) {
                                                        if ($stock_quantity > 0) {
                                                            $stock_class = $stock_quantity <= 5 ? 'awcom-stock-low' : 'awcom-stock-info';
                                                            echo '<br><small class="' . $stock_class . '">' . sprintf(__('%d in stock', 'alynt-wc-customer-order-manager'), $stock_quantity) . '</small>';
                                                        } else {
                                                            echo '<br><small class="awcom-stock-out">' . __('Out of stock', 'alynt-wc-customer-order-manager') . '</small>';
                                                        }
                                                    } elseif ($stock_status === 'instock') {
                                                        echo '<br><small class="awcom-stock-info">' . __('In stock', 'alynt-wc-customer-order-manager') . '</small>';
                                                    } elseif ($stock_status === 'outofstock') {
                                                        echo '<br><small class="awcom-stock-out">' . __('Out of stock', 'alynt-wc-customer-order-manager') . '</small>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="product-quantity">
                                                    <?php if ($is_paid_order): ?>
                                                        <span class="quantity-display"><?php echo esc_html($quantity); ?></span>
                                                        <small class="paid-order-note"><?php _e('(Paid)', 'alynt-wc-customer-order-manager'); ?></small>
                                                    <?php else: ?>
                                                        <input type="number" class="quantity" value="<?php echo esc_attr($quantity); ?>" min="1" max="<?php echo $manage_stock ? $stock_quantity : 999; ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td class="product-price"><?php echo wc_price($price); ?></td>
                                                <td class="product-total"><?php echo wc_price($total); ?></td>
                                                <td class="product-remove">
                                                    <?php if (!$is_paid_order): ?>
                                                        <a href="#" class="remove-item" title="<?php esc_attr_e('Remove item', 'alynt-wc-customer-order-manager'); ?>">×</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="order-subtotal">
                                                <td colspan="3"><?php _e('Subtotal:', 'alynt-wc-customer-order-manager'); ?></td>
                                                <td class="subtotal"><?php echo wc_price($order->get_subtotal()); ?></td>
                                                <td></td>
                                            </tr>
                                            <tr class="order-shipping">
                                                <td colspan="3"><?php _e('Shipping:', 'alynt-wc-customer-order-manager'); ?></td>
                                                <td class="shipping-total"><?php echo wc_price($order->get_shipping_total()); ?></td>
                                                <td></td>
                                            </tr>
                                            <tr class="order-total">
                                                <td colspan="3"><strong><?php _e('Total:', 'alynt-wc-customer-order-manager'); ?></strong></td>
                                                <td class="order-total-amount"><strong><?php echo wc_price($order->get_total()); ?></strong></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <?php if ($is_paid_order): ?>
                            <!-- Additional Items Section -->
                            <div class="awcom-section awcom-additional-items-section">
                                <h2><?php _e('Additional Items', 'alynt-wc-customer-order-manager'); ?></h2>
                                <p><?php _e('Items added here will create a separate order for payment.', 'alynt-wc-customer-order-manager'); ?></p>
                                
                                <div class="awcom-additional-items-wrapper">
                                    <table class="awcom-additional-items shop_table">
                                        <thead>
                                            <tr>
                                                <th class="product-name"><?php _e('Product', 'alynt-wc-customer-order-manager'); ?></th>
                                                <th class="product-quantity"><?php _e('Quantity', 'alynt-wc-customer-order-manager'); ?></th>
                                                <th class="product-price"><?php _e('Price', 'alynt-wc-customer-order-manager'); ?></th>
                                                <th class="product-total"><?php _e('Total', 'alynt-wc-customer-order-manager'); ?></th>
                                                <th class="product-remove"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="awcom-additional-items-body">
                                            <tr class="no-items">
                                                <td colspan="5"><?php _e('No additional items added yet.', 'alynt-wc-customer-order-manager'); ?></td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="additional-total">
                                                <td colspan="3"><strong><?php _e('Additional Items Total:', 'alynt-wc-customer-order-manager'); ?></strong></td>
                                                <td class="additional-total-amount"><strong><?php echo wc_price(0); ?></strong></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Shipping Section -->
                            <div class="awcom-section awcom-shipping-section">
                                <h2><?php _e('Shipping Method', 'alynt-wc-customer-order-manager'); ?></h2>
                                <div id="awcom-shipping-methods">
                                    <p class="loading"><?php _e('Loading shipping methods...', 'alynt-wc-customer-order-manager'); ?></p>
                                </div>
                            </div>

                            <!-- Order Notes Section -->
                            <div class="awcom-section awcom-notes-section">
                                <h2><?php _e('Order Notes', 'alynt-wc-customer-order-manager'); ?></h2>
                                <textarea id="order-notes" rows="4" placeholder="<?php esc_attr_e('Add any special instructions for your order (optional)', 'alynt-wc-customer-order-manager'); ?>"><?php echo esc_textarea($order->get_customer_note()); ?></textarea>
                            </div>

                            <!-- Actions Section -->
                            <div class="awcom-section awcom-actions-section">
                                <?php if ($is_paid_order): ?>
                                    <button type="button" id="awcom-create-additional-order" class="button alt" style="display: none;"><?php _e('Add Items & Pay', 'alynt-wc-customer-order-manager'); ?></button>
                                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="button"><?php _e('Back to Orders', 'alynt-wc-customer-order-manager'); ?></a>
                                <?php else: ?>
                                    <button type="button" id="awcom-update-order" class="button alt"><?php _e('Update Order', 'alynt-wc-customer-order-manager'); ?></button>
                                    <button type="button" id="awcom-proceed-payment" class="button" style="display: none;"><?php _e('Proceed to Payment', 'alynt-wc-customer-order-manager'); ?></button>
                                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="button"><?php _e('Cancel', 'alynt-wc-customer-order-manager'); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Add required scripts -->
        <script type="text/javascript" src="<?php echo WC()->plugin_url(); ?>/assets/js/select2/select2.full.min.js"></script>
        <script type="text/javascript" src="<?php echo WC()->plugin_url(); ?>/assets/js/accounting/accounting.min.js"></script>
        <script type="text/javascript">
        var awcomFrontendVars = <?php echo wp_json_encode(array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('awcom-frontend-order-editor'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'currency_format' => array(
                'symbol' => get_woocommerce_currency_symbol(),
                'decimal_separator' => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
                'decimals' => wc_get_price_decimals(),
                'price_format' => get_woocommerce_price_format()
            ),
            'i18n' => array(
                'search_products' => __('Search for a product...', 'alynt-wc-customer-order-manager'),
                'no_products' => __('No products found', 'alynt-wc-customer-order-manager'),
                'remove_item' => __('Remove item', 'alynt-wc-customer-order-manager'),
                'loading' => __('Loading...', 'alynt-wc-customer-order-manager'),
                'error' => __('An error occurred. Please try again.', 'alynt-wc-customer-order-manager')
            )
        )); ?>;
        </script>
        <script type="text/javascript" src="<?php echo AWCOM_PLUGIN_URL; ?>assets/js/frontend-order-editor.js?ver=<?php echo AWCOM_VERSION; ?>"></script>
        
        <?php
    }

    /**
     * AJAX handler for frontend product search
     */
    public function ajax_search_products() {
        check_ajax_referer('awcom-frontend-order-editor', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';
        $search_term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';

        error_log('AWCOM Product Search Debug - Raw POST data: ' . print_r($_POST, true));
        error_log('AWCOM Product Search Debug - Order ID: ' . $order_id . ' (type: ' . gettype($order_id) . ')');
        error_log('AWCOM Product Search Debug - Order Key: ' . $order_key . ' (length: ' . strlen($order_key) . ')');
        error_log('AWCOM Product Search Debug - Search term: ' . $search_term);

        if (!$order_id || empty($order_key)) {
            error_log('AWCOM Product Search Debug - Validation failed - ID empty: ' . ($order_id ? 'false' : 'true') . ', Key empty: ' . (empty($order_key) ? 'true' : 'false'));
            wp_send_json_error('Invalid order data');
        }

        // Verify order access
        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key) {
            error_log('AWCOM Product Search Debug - Invalid order access');
            wp_send_json_error('Invalid order access');
        }

        $customer_id = $order->get_customer_id();

        // Search for products using the same logic as admin interface
        $products = array();
        
        if (!empty($search_term)) {
            // Search for products by title only (direct database query to avoid content search)
            global $wpdb;
            $search_term_like = '%' . $wpdb->esc_like($search_term) . '%';
            
            $title_sql = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type IN ('product', 'product_variation')
                AND post_status = 'publish'
                AND post_title LIKE %s
                ORDER BY post_title ASC
                LIMIT 25",
                $search_term_like
            );
            
            $title_ids = $wpdb->get_col($title_sql);
            $found_posts = array();
            $found_ids = array();
            
            // Convert title IDs to post objects
            foreach ($title_ids as $id) {
                $post = get_post($id);
                if ($post) {
                    $found_ids[] = $id;
                    $found_posts[] = $post;
                }
            }
            
            // Search for products by SKU
            $sku_args = array(
                'post_type'      => array('product', 'product_variation'),
                'post_status'    => 'publish',
                'posts_per_page' => 25,
                'meta_query'     => array(
                    array(
                        'key'     => '_sku',
                        'value'   => $search_term,
                        'compare' => 'LIKE'
                    )
                ),
                'orderby'        => 'title',
                'order'          => 'ASC',
            );
            
            $sku_query = new \WP_Query($sku_args);
            
            // Add SKU matches that aren't already found
            if ($sku_query->have_posts()) {
                while ($sku_query->have_posts()) {
                    $sku_query->the_post();
                    $post_id = get_the_ID();
                    if (!in_array($post_id, $found_ids)) {
                        $found_ids[] = $post_id;
                        $found_posts[] = get_post($post_id);
                    }
                }
                wp_reset_postdata();
            }
            
            // Sort by title
            usort($found_posts, function($a, $b) {
                return strcmp($a->post_title, $b->post_title);
            });
            
            $posts = $found_posts;
            
            foreach ($posts as $post) {
                $product_id = $post->ID;
                $product = wc_get_product($product_id);
                
                if (!$product || !$product->is_purchasable()) {
                    continue;
                }

                // Get customer-specific pricing
                $price = $product->get_price();
                
                // Get stock information
                $manage_stock = $product->get_manage_stock();
                $stock_quantity = $product->get_stock_quantity();
                $stock_status = $product->get_stock_status();
                
                // Create stock display text
                $stock_display = '';
                if ($stock_status === 'outofstock') {
                    $stock_display = __('Out of stock', 'alynt-wc-customer-order-manager');
                } elseif ($manage_stock && $stock_quantity !== null) {
                    $stock_display = sprintf(__('%d in stock', 'alynt-wc-customer-order-manager'), $stock_quantity);
                } else {
                    $stock_display = __('In stock', 'alynt-wc-customer-order-manager');
                }

                $products[] = array(
                    'id' => $product->get_id(),
                    'text' => $product->get_name() . ' (' . $stock_display . ')',
                    'price' => $price,
                    'formatted_price' => wc_price($price),
                    'stock_quantity' => $stock_quantity,
                    'manage_stock' => $manage_stock,
                    'stock_status' => $stock_status,
                    'stock_display' => $stock_display
                );
            }
        }

        error_log('AWCOM Product Search Debug - Found products: ' . count($products));
        error_log('AWCOM Product Search Debug - Products data: ' . print_r($products, true));

        wp_send_json($products);
    }

    /**
     * AJAX handler for frontend shipping methods
     */
    public function ajax_get_shipping_methods() {
        check_ajax_referer('awcom-frontend-order-editor', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';

        if (!$order_id || !$order_key) {
            wp_send_json_error('Invalid order data');
        }

        // Verify order access
        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key) {
            wp_send_json_error('Invalid order access');
        }

        $customer_id = $order->get_customer_id();
        
        // Get customer's shipping address from order
        $shipping_address = array(
            'country'   => $order->get_shipping_country(),
            'state'     => $order->get_shipping_state(),
            'postcode'  => $order->get_shipping_postcode(),
            'city'      => $order->get_shipping_city(),
            'address_1' => $order->get_shipping_address_1(),
            'address_2' => $order->get_shipping_address_2(),
        );
        
        // Fallback to billing if shipping is empty
        if (empty($shipping_address['address_1'])) {
            $shipping_address = array(
                'country'   => $order->get_billing_country(),
                'state'     => $order->get_billing_state(),
                'postcode'  => $order->get_billing_postcode(),
                'city'      => $order->get_billing_city(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
            );
        }

        // Create temporary cart and calculate shipping
        WC()->cart->empty_cart();

        // Add items to cart
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                WC()->cart->add_to_cart($item['product_id'], $item['quantity']);
            }
        }

        // Set customer address for shipping calculations
        WC()->customer->set_billing_location(
            $shipping_address['country'],
            $shipping_address['state'],
            $shipping_address['postcode'],
            $shipping_address['city']
        );
        WC()->customer->set_shipping_location(
            $shipping_address['country'],
            $shipping_address['state'],
            $shipping_address['postcode'],
            $shipping_address['city']
        );
        WC()->customer->set_shipping_address_1($shipping_address['address_1']);
        WC()->customer->set_shipping_address_2($shipping_address['address_2']);

        // Get shipping packages
        $packages = array(
            array(
                'contents'        => WC()->cart->get_cart(),
                'contents_cost'   => WC()->cart->get_cart_contents_total(),
                'applied_coupons' => WC()->cart->get_applied_coupons(),
                'destination'     => $shipping_address
            )
        );

        // Debug logging
        error_log('AWCOM Shipping Debug - Address: ' . print_r($shipping_address, true));
        error_log('AWCOM Shipping Debug - Cart items: ' . count(WC()->cart->get_cart()));
        error_log('AWCOM Shipping Debug - Cart total: ' . WC()->cart->get_cart_contents_total());

        $shipping_methods = array();
        
        // Use WooCommerce's shipping calculation
        WC()->shipping->calculate_shipping($packages);
        $available_methods = WC()->shipping->get_packages();

        error_log('AWCOM Shipping Debug - Available packages: ' . count($available_methods));

        if (!empty($available_methods)) {
            foreach ($available_methods as $package) {
                if (isset($package['rates']) && !empty($package['rates'])) {
                    foreach ($package['rates'] as $rate) {
                        $shipping_methods[] = array(
                            'id' => $rate->get_id(),
                            'label' => $rate->get_label(),
                            'cost' => wc_format_decimal($rate->get_cost(), 2),
                            'method_id' => $rate->get_method_id()
                        );
                    }
                }
            }
        }

        error_log('AWCOM Shipping Debug - Found methods: ' . count($shipping_methods));

        // If no shipping methods found, try to get the original order's shipping method as fallback
        if (empty($shipping_methods)) {
            $original_shipping = $order->get_shipping_methods();
            if (!empty($original_shipping)) {
                foreach ($original_shipping as $shipping_item) {
                    $shipping_methods[] = array(
                        'id' => $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id(),
                        'label' => $shipping_item->get_name(),
                        'cost' => $shipping_item->get_total(),
                        'method_id' => $shipping_item->get_method_id()
                    );
                }
                error_log('AWCOM Shipping Debug - Using original order shipping methods: ' . count($shipping_methods));
            }
        }

        // Clean up cart
        WC()->cart->empty_cart();

        wp_send_json_success($shipping_methods);
    }

    /**
     * AJAX handler for updating the order
     */
    public function ajax_update_order() {
        check_ajax_referer('awcom-frontend-order-editor', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        $shipping_method = isset($_POST['shipping_method']) ? sanitize_text_field($_POST['shipping_method']) : '';
        $order_notes = isset($_POST['order_notes']) ? sanitize_textarea_field($_POST['order_notes']) : '';

        if (!$order_id || !$order_key) {
            wp_send_json_error(__('Invalid order parameters.', 'alynt-wc-customer-order-manager'));
        }

        // Verify order access
        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key || $order->get_status() !== 'pending') {
            wp_send_json_error(__('Invalid order or order cannot be edited.', 'alynt-wc-customer-order-manager'));
        }

        try {
            error_log('AWCOM Debug - Starting order update for order #' . $order_id);
            
            // Remove all existing items
            foreach ($order->get_items() as $item_id => $item) {
                $order->remove_item($item_id);
            }

            // Add new items
            foreach ($items as $item_data) {
                $product_id = intval($item_data['product_id']);
                $quantity = intval($item_data['quantity']);
                
                if ($product_id && $quantity > 0) {
                    $product = wc_get_product($product_id);
                    if ($product && $product->is_purchasable()) {
                        // Get customer pricing (reuse logic from OrderInterface)
                        $customer_id = $order->get_customer_id();
                        $price = $this->get_customer_price($product, $customer_id);
                        
                        error_log('AWCOM Debug - Adding product #' . $product_id . ' qty: ' . $quantity . ' price: ' . $price);
                        
                        $order->add_product($product, $quantity, array(
                            'subtotal' => $price * $quantity,
                            'total' => $price * $quantity
                        ));
                    }
                }
            }
            
            error_log('AWCOM Debug - Order items added. Total items: ' . count($order->get_items()));

            // Update shipping method
            if ($shipping_method) {
                // Remove existing shipping
                foreach ($order->get_shipping_methods() as $shipping_id => $shipping) {
                    $order->remove_item($shipping_id);
                }

                // Calculate shipping with actual order items
                // Build package from order items
                $package = array(
                    'contents' => array(),
                    'contents_cost' => 0,
                    'applied_coupons' => array(),
                    'user' => array('ID' => $order->get_customer_id()),
                    'destination' => array(
                        'country' => $order->get_shipping_country() ?: $order->get_billing_country(),
                        'state' => $order->get_shipping_state() ?: $order->get_billing_state(),
                        'postcode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                        'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
                        'address' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
                        'address_2' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
                    )
                );

                // Add items to package
                foreach ($order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product) {
                        $package['contents'][] = array(
                            'data' => $product,
                            'quantity' => $item->get_quantity(),
                            'line_total' => $item->get_total(),
                            'line_subtotal' => $item->get_subtotal()
                        );
                        $package['contents_cost'] += $item->get_total();
                    }
                }

                // Get shipping rates
                WC()->shipping->calculate_shipping(array($package));
                $packages = WC()->shipping->get_packages();
                
                foreach ($packages as $package) {
                    if (isset($package['rates'][$shipping_method])) {
                        $rate = $package['rates'][$shipping_method];
                        $shipping_item = new \WC_Order_Item_Shipping();
                        $shipping_item->set_method_title($rate->get_label());
                        $shipping_item->set_method_id($rate->get_method_id());
                        $shipping_item->set_instance_id($rate->get_instance_id());
                        $shipping_item->set_total($rate->get_cost());
                        $order->add_item($shipping_item);
                        break;
                    }
                }
            }

            // Update order notes
            $order->set_customer_note($order_notes);

            error_log('AWCOM Debug - Before calculate_totals. Items: ' . count($order->get_items()) . ', Shipping methods: ' . count($order->get_shipping_methods()));
            
            // Recalculate totals
            $order->calculate_totals();
            
            error_log('AWCOM Debug - After calculate_totals. Order total: ' . $order->get_total() . ', Subtotal: ' . $order->get_subtotal() . ', Shipping: ' . $order->get_shipping_total());
            
            // Ensure order status is set to pending for payment
            $order->set_status('pending');
            
            // Clear any payment complete flags
            $order->delete_meta_data('_paid_date');
            $order->set_date_paid(null);
            
            // Set order as cart hash to allow payment (WooCommerce validation check)
            $order->update_meta_data('_order_stock_reduced', '');
            
            // Ensure customer ID is set (required for payment validation)
            if (!$order->get_customer_id()) {
                error_log('AWCOM Debug - WARNING: Order has no customer ID');
            }
            
            // Ensure billing email exists (required for payment)
            if (!$order->get_billing_email()) {
                error_log('AWCOM Debug - WARNING: Order has no billing email');
                // Try to get email from customer
                $customer = $order->get_user();
                if ($customer) {
                    $order->set_billing_email($customer->user_email);
                    error_log('AWCOM Debug - Set billing email from customer: ' . $customer->user_email);
                }
            }
            
            $order->save();
            
            error_log('AWCOM Debug - Order saved. Status: ' . $order->get_status() . ', needs_payment(): ' . ($order->needs_payment() ? 'true' : 'false'));
            error_log('AWCOM Debug - Order details: Customer ID: ' . $order->get_customer_id() . ', Email: ' . $order->get_billing_email() . ', Payment method: ' . $order->get_payment_method());

            // Validate order can be paid
            if ($order->get_total() <= 0) {
                error_log('AWCOM Debug - Validation failed: Order total is zero or negative');
                wp_send_json_error(__('Order total must be greater than zero.', 'alynt-wc-customer-order-manager'));
            }

            if (count($order->get_items()) === 0) {
                error_log('AWCOM Debug - Validation failed: No order items');
                wp_send_json_error(__('Order must contain at least one item.', 'alynt-wc-customer-order-manager'));
            }

            // Verify order needs payment
            if (!$order->needs_payment()) {
                error_log('AWCOM Debug - Order does not need payment. Status: ' . $order->get_status() . ', Total: ' . $order->get_total() . ', Paid date: ' . $order->get_date_paid());
                wp_send_json_error(__('Order validation failed. Status: ' . $order->get_status() . ', Total: ' . $order->get_total(), 'alynt-wc-customer-order-manager'));
            }

            // Get payment URL and add skip_edit parameter to bypass edit page redirect
            $payment_url = add_query_arg('skip_edit', '1', $order->get_checkout_payment_url());
            
            error_log('AWCOM Debug - Order update successful. Payment URL: ' . $payment_url);

            wp_send_json_success(array(
                'message' => __('Order updated successfully!', 'alynt-wc-customer-order-manager'),
                'payment_url' => $payment_url,
                'order_total' => $order->get_total(),
                'order_status' => $order->get_status()
            ));

        } catch (Exception $e) {
            wp_send_json_error(__('Error updating order: ', 'alynt-wc-customer-order-manager') . $e->getMessage());
        }
    }

    /**
     * Get customer-specific pricing for a product
     */
    private function get_customer_price($product, $customer_id) {
        // Get base price: use sale price if on sale, otherwise regular price
        // This respects WooCommerce sales while avoiding double-discount issues
        $sale_price = $product->get_sale_price();
        $original_price = !empty($sale_price) && $sale_price > 0 ? $sale_price : $product->get_regular_price();
        $adjusted_price = $original_price;

        if (!$customer_id) {
            return $adjusted_price;
        }

        // Get customer's group (if using group pricing)
        global $wpdb;
        $group_id = $wpdb->get_var($wpdb->prepare(
            "SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
            $customer_id
        ));

        if ($group_id) {
            // Check for product-specific rules
            $product_rule = $wpdb->get_row($wpdb->prepare(
                "SELECT pr.* 
                FROM {$wpdb->prefix}pricing_rules pr
                JOIN {$wpdb->prefix}rule_products rp ON pr.rule_id = rp.rule_id
                WHERE pr.group_id = %d AND rp.product_id = %d
                ORDER BY pr.created_at DESC
                LIMIT 1",
                $group_id,
                $product->get_id()
            ));

            if ($product_rule) {
                if ($product_rule->discount_type === 'percentage') {
                    $adjusted_price = $original_price - (($product_rule->discount_value / 100) * $original_price);
                } else {
                    $adjusted_price = $original_price - $product_rule->discount_value;
                }
            } else {
                // Check for category rules
                $category_ids = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
                if (!empty($category_ids)) {
                    $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
                    $query_args = array_merge(array($group_id), $category_ids);
                    $category_rule = $wpdb->get_row($wpdb->prepare(
                        "SELECT pr.* 
                        FROM {$wpdb->prefix}pricing_rules pr
                        JOIN {$wpdb->prefix}rule_categories rc ON pr.rule_id = rc.rule_id
                        WHERE pr.group_id = %d AND rc.category_id IN ($placeholders)
                        ORDER BY pr.created_at DESC
                        LIMIT 1",
                        $query_args
                    ));

                    if ($category_rule) {
                        if ($category_rule->discount_type === 'percentage') {
                            $adjusted_price = $original_price - (($category_rule->discount_value / 100) * $original_price);
                        } else {
                            $adjusted_price = $original_price - $category_rule->discount_value;
                        }
                    }
                }
            }
        }

        return max(0, $adjusted_price);
    }

    /**
     * AJAX handler for creating additional items order
     */
    public function ajax_create_additional_order() {
        check_ajax_referer('awcom-frontend-order-editor', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';
        $additional_items = isset($_POST['additional_items']) ? $_POST['additional_items'] : array();

        if (!$order_id || empty($order_key)) {
            wp_send_json_error('Invalid order data');
        }

        // Verify order access
        $original_order = wc_get_order($order_id);
        if (!$original_order || $original_order->get_order_key() !== $order_key) {
            wp_send_json_error('Invalid order access');
        }

        // Validate that we have additional items
        if (empty($additional_items)) {
            wp_send_json_error('No additional items specified');
        }

        try {
            // Create new order for additional items
            $additional_order = wc_create_order();

            // Set customer information from original order
            $additional_order->set_customer_id($original_order->get_customer_id());
            $additional_order->set_billing_address($original_order->get_billing_address());
            $additional_order->set_shipping_address($original_order->get_shipping_address());

            // Add products to the additional order
            foreach ($additional_items as $item) {
                $product_id = intval($item['product_id']);
                $quantity = intval($item['quantity']);

                if ($product_id && $quantity > 0) {
                    $product = wc_get_product($product_id);
                    if ($product && $product->is_purchasable()) {
                        // Get customer pricing
                        $customer_id = $original_order->get_customer_id();
                        $price = $this->get_customer_price($product, $customer_id);
                        
                        $additional_order->add_product($product, $quantity, array(
                            'subtotal' => $price * $quantity,
                            'total' => $price * $quantity
                        ));
                    }
                }
            }

            // Set shipping method if provided
            if (isset($_POST['shipping_method']) && !empty($_POST['shipping_method'])) {
                $shipping_method = sanitize_text_field($_POST['shipping_method']);
                $additional_order->add_shipping_rate($shipping_method);
            }

            // Add order notes if provided
            if (isset($_POST['order_notes']) && !empty($_POST['order_notes'])) {
                $order_notes = sanitize_textarea_field($_POST['order_notes']);
                $additional_order->set_customer_note($order_notes);
            }

            // Link the orders
            $additional_order->add_meta_data('_parent_order_id', $order_id);
            $additional_order->add_meta_data('_is_additional_items_order', true);
            $original_order->add_meta_data('_additional_order_id', $additional_order->get_id());

            // Calculate totals and save
            $additional_order->calculate_totals();
            $additional_order->save();
            $original_order->save();

            // Add order note to original order
            $original_order->add_order_note(
                sprintf(__('Customer added additional items. Additional order #%s created.', 'alynt-wc-customer-order-manager'), 
                $additional_order->get_order_number())
            );

            // Add order note to additional order
            $additional_order->add_order_note(
                sprintf(__('Additional items for original order #%s.', 'alynt-wc-customer-order-manager'), 
                $original_order->get_order_number())
            );

            wp_send_json_success(array(
                'message' => __('Additional items order created successfully!', 'alynt-wc-customer-order-manager'),
                'additional_order_id' => $additional_order->get_id(),
                'payment_url' => $additional_order->get_checkout_payment_url()
            ));

        } catch (Exception $e) {
            wp_send_json_error('Error creating additional order: ' . $e->getMessage());
        }
    }
}
