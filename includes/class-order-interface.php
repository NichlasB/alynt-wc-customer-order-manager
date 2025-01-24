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

        error_log('WCCG Debug - Search Products:');
        error_log('Customer ID: ' . $customer_id);
        error_log('Search Term: ' . $term);

        if (empty($term)) {
            wp_die();
        }

        $args = array(
            'post_type'      => array('product', 'product_variation'),
            'post_status'    => 'publish',
            'posts_per_page' => 25,
            's'              => $term,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        $query = new \WP_Query($args);
        $products = array();

        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());

            if (!$product || !$product->is_purchasable()) {
                continue;
            }

            $original_price = $product->get_regular_price();
            $adjusted_price = $original_price;

            error_log('Product ID: ' . $product->get_id());
            error_log('Original Price: ' . $original_price);

        // Get customer's group
            global $wpdb;
            $group_id = $wpdb->get_var($wpdb->prepare(
                "SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
                $customer_id
            ));

            error_log('Customer Group ID: ' . ($group_id ? $group_id : 'None'));

            if ($group_id) {
            // First check for product-specific rules
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
                    error_log('Found Product Rule: ' . print_r($product_rule, true));
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
                        $query = $wpdb->prepare(
                            "SELECT pr.* 
                            FROM {$wpdb->prefix}pricing_rules pr
                            JOIN {$wpdb->prefix}rule_categories rc ON pr.rule_id = rc.rule_id
                            WHERE pr.group_id = %d AND rc.category_id IN ($placeholders)
                            ORDER BY pr.created_at DESC
                            LIMIT 1",
                            array_merge(array($group_id), $category_ids)
                        );
                        $category_rule = $wpdb->get_row($query);

                        if ($category_rule) {
                            error_log('Found Category Rule: ' . print_r($category_rule, true));
                            if ($category_rule->discount_type === 'percentage') {
                                $adjusted_price = $original_price - (($category_rule->discount_value / 100) * $original_price);
                            } else {
                                $adjusted_price = $original_price - $category_rule->discount_value;
                            }
                        }
                    }
                }
            }

            error_log('Final Adjusted Price: ' . $adjusted_price);

        // Ensure price doesn't go below zero
            $adjusted_price = max(0, $adjusted_price);

            $products[] = array(
                'id'        => $product->get_id(),
                'text'      => $product->get_formatted_name(),
                'price'     => $adjusted_price,
                'formatted_price' => wc_price($adjusted_price),
                'original_price' => $original_price,
                'formatted_original_price' => wc_price($original_price),
                'has_discount' => $adjusted_price < $original_price
            );
        }

        wp_reset_postdata();

        error_log('Final Products Array: ' . print_r($products, true));

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

    // Get customer's billing address
        $address = array(
            'country'   => get_user_meta($customer_id, 'billing_country', true),
            'state'     => get_user_meta($customer_id, 'billing_state', true),
            'postcode'  => get_user_meta($customer_id, 'billing_postcode', true),
            'city'      => get_user_meta($customer_id, 'billing_city', true),
            'address_1' => get_user_meta($customer_id, 'billing_address_1', true),
            'address_2' => get_user_meta($customer_id, 'billing_address_2', true),
        );

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

    // Debug shipping information
        error_log('Shipping Debug:');
        error_log('Customer Address: ' . print_r($address, true));
        error_log('Cart Contents: ' . print_r(WC()->cart->get_cart(), true));

        $shipping_methods = array();
        WC()->shipping->load_shipping_methods($packages[0]);

    // Get all enabled shipping methods
        $enabled_methods = WC()->shipping->get_shipping_methods();

        foreach ($enabled_methods as $method) {
            if ($method->is_enabled()) {
                error_log('Processing shipping method: ' . $method->id);

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

                        error_log('Added shipping rate: ' . print_r($shipping_methods[count($shipping_methods)-1], true));
                    }
                }
            }
        }

        if (empty($shipping_methods)) {
            error_log('No shipping methods available for this order');
        }

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

}