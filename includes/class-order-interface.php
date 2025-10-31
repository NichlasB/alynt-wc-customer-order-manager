<?php
namespace AlyntWCOrderManager;

class OrderInterface {
    private $customer_id;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('wp_ajax_awcom_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_awcom_get_shipping_methods', array($this, 'ajax_get_shipping_methods'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_menu_pages() {
    // Hidden page for creating orders
        add_submenu_page(
        null,  // parent slug
        __('Create Order', 'alynt-wc-customer-order-manager'),  // page title
        __('Create Order', 'alynt-wc-customer-order-manager'),  // menu title
        'manage_woocommerce',  // capability
        'alynt-wc-customer-order-manager-create-order',  // menu slug
        array($this, 'render_create_order_page')  // callback function
    );
    }

    public function enqueue_scripts($hook) {
        if ($hook != 'admin_page_alynt-wc-customer-order-manager-create-order') {
            return;
        }

        // Enqueue Select2
        wp_enqueue_style('select2', WC()->plugin_url() . '/assets/css/select2.css', array(), '4.0.3');
        wp_enqueue_script('select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array('jquery'), '4.0.3', true);

        // Enqueue accounting.js
        wp_enqueue_script('accounting', WC()->plugin_url() . '/assets/js/accounting/accounting.min.js', array('jquery'), '0.4.2', true);

        // Enqueue our custom styles and scripts
        wp_enqueue_style(
            'awcom-order-interface', 
            AWCOM_PLUGIN_URL . 'assets/css/order-interface.css',
            array('select2'),
            AWCOM_VERSION
        );

        wp_enqueue_script(
            'awcom-order-interface',
            AWCOM_PLUGIN_URL . 'assets/js/order-interface.js',
            array('jquery', 'select2', 'accounting'),
            AWCOM_VERSION,
            true
        );

        wp_localize_script('awcom-order-interface', 'awcomOrderVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('awcom-order-interface'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'i18n' => array(
                'search_products' => __('Search for a product...', 'alynt-wc-customer-order-manager'),
                'no_products' => __('No products found', 'alynt-wc-customer-order-manager'),
                'remove_item' => __('Remove item', 'alynt-wc-customer-order-manager'),
                'calculating' => __('Calculating...', 'alynt-wc-customer-order-manager'),
                'no_shipping' => __('No shipping methods available', 'alynt-wc-customer-order-manager'),
                'shipping_error' => __('Error calculating shipping methods', 'alynt-wc-customer-order-manager'),
                'no_items' => __('Please add at least one item to the order.', 'alynt-wc-customer-order-manager'),
                'no_shipping_selected' => __('Please select a shipping method.', 'alynt-wc-customer-order-manager'),
            )
        ));
    }

    public function render_create_order_page() {
        if (!Security::user_can_access()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $this->customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        if (!$this->customer_id) {
            wp_die(__('Invalid customer ID.', 'alynt-wc-customer-order-manager'));
        }

        $customer = get_user_by('id', $this->customer_id);
        if (!$customer || !in_array('customer', $customer->roles)) {
            wp_die(__('Invalid customer.', 'alynt-wc-customer-order-manager'));
        }

        ?>
        <div class="wrap">
            <h1><?php 
            printf(
                __('Create Order for %s', 'alynt-wc-customer-order-manager'),
                esc_html($customer->first_name . ' ' . $customer->last_name)
            ); 
        ?></h1>

        <?php
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . 
            esc_html(urldecode($_GET['error'])) . '</p></div>';
        }
        ?>

        <form method="post" id="awcom-create-order-form">
            <?php wp_nonce_field('create_order', 'awcom_order_nonce'); ?>
            <input type="hidden" name="action" value="create_order">
            <input type="hidden" name="customer_id" value="<?php echo esc_attr($this->customer_id); ?>">

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Products Section -->
                        <div class="postbox">
                            <h2 class="hndle"><span><?php _e('Order Items', 'alynt-wc-customer-order-manager'); ?></span></h2>
                            <div class="inside">
                                <div class="awcom-product-search">
                                    <select id="awcom-add-product" style="width: 100%;">
                                        <option></option>
                                    </select>
                                </div>
                                <table class="widefat awcom-order-items">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Item', 'alynt-wc-customer-order-manager'); ?></th>
                                            <th class="quantity"><?php _e('Qty', 'alynt-wc-customer-order-manager'); ?></th>
                                            <th class="price"><?php _e('Price', 'alynt-wc-customer-order-manager'); ?></th>
                                            <th class="total"><?php _e('Total', 'alynt-wc-customer-order-manager'); ?></th>
                                            <th class="actions"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Products will be added here dynamically -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="2"></td>
                                            <td><?php _e('Subtotal:', 'alynt-wc-customer-order-manager'); ?></td>
                                            <td class="subtotal">0.00</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2"></td>
                                            <td><?php _e('Shipping:', 'alynt-wc-customer-order-manager'); ?></td>
                                            <td class="shipping-total">0.00</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2"></td>
                                            <td><strong><?php _e('Total:', 'alynt-wc-customer-order-manager'); ?></strong></td>
                                            <td class="order-total"><strong>0.00</strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Customer Notes -->
                        <div class="postbox">
                            <h2 class="hndle"><span><?php _e('Order Notes', 'alynt-wc-customer-order-manager'); ?></span></h2>
                            <div class="inside">
                                <textarea name="order_notes" rows="5" style="width: 100%;" 
                                placeholder="<?php esc_attr_e('Add any notes about this order (optional)', 'alynt-wc-customer-order-manager'); ?>"></textarea>
                            </div>
                        </div>
                    </div>

                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Order Actions -->
                        <div class="postbox">
                            <h2 class="hndle"><span><?php _e('Order Actions', 'alynt-wc-customer-order-manager'); ?></span></h2>
                            <div class="inside">
                                <?php submit_button(__('Create Order', 'alynt-wc-customer-order-manager'), 'primary', 'submit', false); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=alynt-wc-customer-order-manager-edit&id=' . $this->customer_id)); ?>" 
                                    class="button"><?php _e('Cancel', 'alynt-wc-customer-order-manager'); ?></a>
                                </div>
                            </div>

                            <!-- Customer Details -->
                            <div class="postbox">
                                <h2 class="hndle"><span><?php _e('Customer Details', 'alynt-wc-customer-order-manager'); ?></span></h2>
                                <div class="inside">
                                    <div class="billing-details">
                                        <h4><?php _e('Billing Address', 'alynt-wc-customer-order-manager'); ?></h4>
                                        <?php
                                        $billing_address = array(
                                            'first_name' => $customer->first_name,
                                            'last_name'  => $customer->last_name,
                                            'company'    => get_user_meta($this->customer_id, 'billing_company', true),
                                            'address_1'  => get_user_meta($this->customer_id, 'billing_address_1', true),
                                            'address_2'  => get_user_meta($this->customer_id, 'billing_address_2', true),
                                            'city'       => get_user_meta($this->customer_id, 'billing_city', true),
                                            'state'      => get_user_meta($this->customer_id, 'billing_state', true),
                                            'postcode'   => get_user_meta($this->customer_id, 'billing_postcode', true),
                                            'country'    => get_user_meta($this->customer_id, 'billing_country', true),
                                        );

                                        echo '<div class="address">';
                                        echo WC()->countries->get_formatted_address($billing_address);
                                        echo '</div>';
                                        ?>
                                    </div>
                                    
                                    <?php
                                    // Check if customer has a different shipping address
                                    $shipping_address_1 = get_user_meta($this->customer_id, 'shipping_address_1', true);
                                    if (!empty($shipping_address_1)) {
                                        $shipping_address = array(
                                            'first_name' => $customer->first_name,
                                            'last_name'  => $customer->last_name,
                                            'company'    => get_user_meta($this->customer_id, 'shipping_company', true),
                                            'address_1'  => get_user_meta($this->customer_id, 'shipping_address_1', true),
                                            'address_2'  => get_user_meta($this->customer_id, 'shipping_address_2', true),
                                            'city'       => get_user_meta($this->customer_id, 'shipping_city', true),
                                            'state'      => get_user_meta($this->customer_id, 'shipping_state', true),
                                            'postcode'   => get_user_meta($this->customer_id, 'shipping_postcode', true),
                                            'country'    => get_user_meta($this->customer_id, 'shipping_country', true),
                                        );
                                        
                                        // Only show shipping address if it's different from billing
                                        $is_different = (
                                            $shipping_address['address_1'] !== $billing_address['address_1'] ||
                                            $shipping_address['city'] !== $billing_address['city'] ||
                                            $shipping_address['country'] !== $billing_address['country']
                                        );
                                        
                                        if ($is_different) {
                                            echo '<div class="shipping-details" style="margin-top: 20px;">';
                                            echo '<h4>' . __('Shipping Address', 'alynt-wc-customer-order-manager') . '</h4>';
                                            echo '<div class="address">';
                                            echo WC()->countries->get_formatted_address($shipping_address);
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>

                            <!-- Shipping Method -->
                            <div class="postbox">
                                <h2 class="hndle"><span><?php _e('Shipping', 'alynt-wc-customer-order-manager'); ?></span></h2>
                                <div class="inside">
                                    <div id="shipping-methods">
                                        <!-- Shipping methods will be loaded here -->
                                        <p class="loading"><?php _e('Calculating available shipping methods...', 'alynt-wc-customer-order-manager'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function ajax_search_products() {
        check_ajax_referer('awcom-order-interface', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(-1);
        }

        $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        $term = isset($_GET['term']) ? wc_clean($_GET['term']) : '';

        if (empty($term)) {
            wp_die();
        }

        // Search for products by title only (direct database query to avoid content search)
        global $wpdb;
        $search_term = '%' . $wpdb->esc_like($term) . '%';
        
        $title_sql = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type IN ('product', 'product_variation')
            AND post_status = 'publish'
            AND post_title LIKE %s
            ORDER BY post_title ASC
            LIMIT 25",
            $search_term
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
                    'value'   => $term,
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
        
        $products = array();

        foreach ($posts as $post) {
            $product_id = $post->ID;
            $product = wc_get_product($product_id);
            
            if (!$product || !$product->is_purchasable()) {
                continue;
            }

            // Get base price: use sale price if on sale, otherwise regular price
            // This respects WooCommerce sales while avoiding double-discount issues
            $sale_price = $product->get_sale_price();
            $original_price = !empty($sale_price) && $sale_price > 0 ? $sale_price : $product->get_regular_price();
            $adjusted_price = $original_price;

            if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                error_log('Product ID: ' . $product->get_id());
                error_log('Original Price: ' . $original_price);
            }

            // Get customer's group
            global $wpdb;
            $group_id = $wpdb->get_var($wpdb->prepare(
                "SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
                $customer_id
            ));

            if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                error_log('Customer Group ID: ' . ($group_id ? $group_id : 'None'));
            }

            if ($group_id) {
                // First check for product-specific rules
                try {
                    $product_query = $wpdb->prepare(
                        "SELECT pr.* 
                        FROM {$wpdb->prefix}pricing_rules pr
                        JOIN {$wpdb->prefix}rule_products rp ON pr.rule_id = rp.rule_id
                        WHERE pr.group_id = %d AND rp.product_id = %d
                        ORDER BY pr.created_at DESC
                        LIMIT 1",
                        $group_id,
                        $product->get_id()
                    );
                    
                    if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                        error_log('Product Rule Query: ' . $product_query);
                    }
                    
                    $product_rule = $wpdb->get_row($product_query);
                    
                    if ($wpdb->last_error) {
                        error_log('SQL Error (Product Rule): ' . $wpdb->last_error);
                    }
                } catch (Exception $e) {
                    error_log('Exception in product rule query: ' . $e->getMessage());
                    $product_rule = null;
                }

                if ($product_rule) {
                    if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                        error_log('Found Product Rule: ' . print_r($product_rule, true));
                    } else {
                        error_log('Found Product Rule for product ID: ' . $product->get_id());
                    }
                    
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
                        try {
                            $query_args = array_merge(array($group_id), $category_ids);
                            $category_query = $wpdb->prepare(
                                "SELECT pr.* 
                                FROM {$wpdb->prefix}pricing_rules pr
                                JOIN {$wpdb->prefix}rule_categories rc ON pr.rule_id = rc.rule_id
                                WHERE pr.group_id = %d AND rc.category_id IN ($placeholders)
                                ORDER BY pr.created_at DESC
                                LIMIT 1",
                                $query_args
                            );
                            
                            if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                                error_log('Category Rule Query: ' . $category_query);
                            }
                            
                            $category_rule = $wpdb->get_row($category_query);
                            
                            if ($wpdb->last_error) {
                                error_log('SQL Error (Category Rule): ' . $wpdb->last_error);
                            }
                        } catch (Exception $e) {
                            error_log('Exception in category rule query: ' . $e->getMessage());
                            $category_rule = null;
                        }

                        if ($category_rule) {
                            if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                                error_log('Found Category Rule: ' . print_r($category_rule, true));
                            } else {
                                error_log('Found Category Rule for product ID: ' . $product->get_id());
                            }
                            
                            if ($category_rule->discount_type === 'percentage') {
                                $adjusted_price = $original_price - (($category_rule->discount_value / 100) * $original_price);
                            } else {
                                $adjusted_price = $original_price - $category_rule->discount_value;
                            }
                        }
                    }
                }
            }

            if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                error_log('Final Adjusted Price: ' . $adjusted_price);
            }

            // Ensure price doesn't go below zero
            $adjusted_price = max(0, $adjusted_price);

            // Get stock information
            $stock_quantity = $product->get_stock_quantity();
            $stock_status = $product->get_stock_status();
            $manage_stock = $product->get_manage_stock();
            
            // Format stock display
            $stock_display = '';
            if ($manage_stock && $stock_quantity !== null) {
                if ($stock_quantity > 0) {
                    $stock_display = sprintf(__('%d in stock', 'alynt-wc-customer-order-manager'), $stock_quantity);
                } else {
                    $stock_display = __('Out of stock', 'alynt-wc-customer-order-manager');
                }
            } elseif ($stock_status === 'instock') {
                $stock_display = __('In stock', 'alynt-wc-customer-order-manager');
            } elseif ($stock_status === 'outofstock') {
                $stock_display = __('Out of stock', 'alynt-wc-customer-order-manager');
            } elseif ($stock_status === 'onbackorder') {
                $stock_display = __('On backorder', 'alynt-wc-customer-order-manager');
            }



            $products[] = array(
                'id'        => $product->get_id(),
                'text'      => $product->get_formatted_name(),
                'price'     => $adjusted_price,
                'formatted_price' => wc_price($adjusted_price),
                'original_price' => $original_price,
                'formatted_original_price' => wc_price($original_price),
                'has_discount' => $adjusted_price < $original_price,
                'stock_quantity' => $stock_quantity,
                'stock_status' => $stock_status,
                'stock_display' => $stock_display,
                'manage_stock' => $manage_stock
            );
        }

        wp_reset_postdata();

        if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
            error_log('Final Products Array: ' . print_r($products, true));
        }

        wp_send_json($products);
    }

    public function ajax_get_shipping_methods() {
        check_ajax_referer('awcom-order-interface', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(-1);
        }

        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        if (!$customer_id) {
            wp_die(-1);
        }

        // Get customer's shipping address first, fallback to billing if shipping is empty
        $shipping_address = array(
            'country'   => get_user_meta($customer_id, 'shipping_country', true),
            'state'     => get_user_meta($customer_id, 'shipping_state', true),
            'postcode'  => get_user_meta($customer_id, 'shipping_postcode', true),
            'city'      => get_user_meta($customer_id, 'shipping_city', true),
            'address_1' => get_user_meta($customer_id, 'shipping_address_1', true),
            'address_2' => get_user_meta($customer_id, 'shipping_address_2', true),
        );
        
        $billing_address = array(
            'country'   => get_user_meta($customer_id, 'billing_country', true),
            'state'     => get_user_meta($customer_id, 'billing_state', true),
            'postcode'  => get_user_meta($customer_id, 'billing_postcode', true),
            'city'      => get_user_meta($customer_id, 'billing_city', true),
            'address_1' => get_user_meta($customer_id, 'billing_address_1', true),
            'address_2' => get_user_meta($customer_id, 'billing_address_2', true),
        );
        
        // Check if shipping address has key fields populated
        $has_shipping_address = !empty($shipping_address['address_1']) && 
                               !empty($shipping_address['city']) && 
                               !empty($shipping_address['country']);
        
        // Use shipping address if available, otherwise use billing address
        $address = $has_shipping_address ? $shipping_address : $billing_address;

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
            $address['country'],
            $address['state'],
            $address['postcode'],
            $address['city']
        );
        WC()->customer->set_shipping_location(
            $address['country'],
            $address['state'],
            $address['postcode'],
            $address['city']
        );
        WC()->customer->set_shipping_address_1($address['address_1']);
        WC()->customer->set_shipping_address_2($address['address_2']);

        // Get shipping packages
        $packages = array(
            array(
                'contents'        => WC()->cart->get_cart(),
                'contents_cost'   => WC()->cart->get_cart_contents_total(),
                'applied_coupons' => WC()->cart->get_applied_coupons(),
                'destination'     => array(
                    'country'   => $address['country'],
                    'state'     => $address['state'],
                    'postcode'  => $address['postcode'],
                    'city'      => $address['city'],
                    'address'   => $address['address_1'],
                    'address_2' => $address['address_2']
                )
            )
        );

        if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
            error_log('Shipping Debug:');
            error_log('Customer Address: ' . print_r($address, true));
            error_log('Cart Contents: ' . print_r(WC()->cart->get_cart(), true));
        }

        $shipping_methods = array();
        WC()->shipping->load_shipping_methods($packages[0]);

        // Get all enabled shipping methods
        $enabled_methods = WC()->shipping->get_shipping_methods();

        foreach ($enabled_methods as $method) {
            if ($method->is_enabled()) {
                if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                    error_log('Processing shipping method: ' . $method->id);
                }

                // Calculate shipping for this method
                $rate = $method->get_rates_for_package($packages[0]);

                if ($rate) {
                    foreach ($rate as $rate_id => $rate_data) {
                        $shipping_methods[] = array(
                            'id'             => $rate_id,
                            'method_id'      => $method->id,
                            'label'          => $rate_data->label,
                            'cost'           => $rate_data->cost,
                            'formatted_cost' => wc_price($rate_data->cost),
                            'taxes'          => $rate_data->taxes,
                        );

                        if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                            error_log('Added shipping rate: ' . print_r($shipping_methods[count($shipping_methods)-1], true));
                        }
                    }
                }
            }
        }

        if (empty($shipping_methods)) {
            if (defined('AWCOM_DEBUG') && AWCOM_DEBUG) {
                error_log('No shipping methods available for this order');
            }
        }

        // Clean up: Empty the cart to prevent items from appearing in admin's frontend cart
        WC()->cart->empty_cart();

        // Send response with success status
        wp_send_json(array(
            'success' => true,
            'methods' => $shipping_methods,
            'debug'   => array(
                'address'  => $address,
                'packages' => $packages
            )
        ));
    }

    /**
     * Extend product search to include SKU
     */
    public function extend_product_search($search, $wp_query) {
        global $wpdb;
        
        if (!is_admin() || empty($this->search_term)) {
            return $search;
        }
        
        // Only modify search for product queries
        if (!isset($wp_query->query_vars['post_type']) || 
            !in_array('product', (array)$wp_query->query_vars['post_type'])) {
            return $search;
        }
        
        $search_term = esc_sql(like_escape($this->search_term));
        
        // Add SKU search to the existing search
        if (!empty($search)) {
            $search = preg_replace(
                '/\(\(\(.*?\)\)\)/',
                "((($0) OR (mt1.meta_key = '_sku' AND mt1.meta_value LIKE '%{$search_term}%')))",
                $search
            );
        }
        
        return $search;
    }
    
    /**
     * Join meta table for SKU search
     */
    public function extend_product_search_join($join, $wp_query) {
        global $wpdb;
        
        if (!is_admin() || empty($this->search_term)) {
            return $join;
        }
        
        // Only modify join for product queries
        if (!isset($wp_query->query_vars['post_type']) || 
            !in_array('product', (array)$wp_query->query_vars['post_type'])) {
            return $join;
        }
        
        $join .= " LEFT JOIN {$wpdb->postmeta} mt1 ON ({$wpdb->posts}.ID = mt1.post_id)";
        
        return $join;
    }
    
    /**
     * Group by post ID to avoid duplicates
     */
    public function extend_product_search_groupby($groupby, $wp_query) {
        global $wpdb;
        
        if (!is_admin() || empty($this->search_term)) {
            return $groupby;
        }
        
        // Only modify groupby for product queries
        if (!isset($wp_query->query_vars['post_type']) || 
            !in_array('product', (array)$wp_query->query_vars['post_type'])) {
            return $groupby;
        }
        
        $groupby = "{$wpdb->posts}.ID";
        
        return $groupby;
    }

}