<?php
namespace AlyntWCOrderManager;

use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Shipping_Zones;
use Exception;

class OrderHandler {
    const DEBUG = true;
    
    private $shipping_errors = array();
    public function __construct() {
        add_action('admin_init', array($this, 'handle_order_creation'));
        
        // Prevent WooCommerce from recalculating totals for our orders during payment
        add_action('woocommerce_order_before_calculate_totals', array($this, 'prevent_total_recalculation'), 10, 2);
        
        // Restore custom prices after WooCommerce recalculates (for admin recalculate button)
        add_action('woocommerce_order_after_calculate_totals', array($this, 'restore_custom_prices_after_recalculation'), 10, 2);
        
        // Additional protection: prevent calculate_totals() from running on payment page
        add_filter('woocommerce_order_item_get_subtotal', array($this, 'preserve_item_subtotal'), 10, 2);
        add_filter('woocommerce_order_item_get_total', array($this, 'preserve_item_total'), 10, 2);
        
        // Lock the order total to prevent WooCommerce from recalculating
        add_filter('woocommerce_order_get_total', array($this, 'lock_order_total'), 9999, 2);
        
        // NOTE: Customer group pricing is now handled by the Customer Groups for WooCommerce plugin
        // These filters have been removed to prevent double discount application
        // add_filter('woocommerce_product_get_price', array($this, 'apply_customer_group_pricing'), 10, 2);
        // add_filter('woocommerce_product_get_regular_price', array($this, 'apply_customer_group_pricing'), 10, 2);
        // add_filter('woocommerce_product_get_sale_price', array($this, 'apply_customer_group_pricing'), 10, 2);
        // add_filter('woocommerce_variation_prices_price', array($this, 'apply_customer_group_pricing'), 10, 2);
        // add_filter('woocommerce_variation_prices_regular_price', array($this, 'apply_customer_group_pricing'), 10, 2);
        // add_filter('woocommerce_variation_prices_sale_price', array($this, 'apply_customer_group_pricing'), 10, 2);
        
        // NOTE: Cart pricing is also handled by the Customer Groups plugin
        // add_action('woocommerce_before_calculate_totals', array($this, 'apply_cart_customer_pricing'), 9999, 1);
        
        // NOTE: Session pricing also handled by Customer Groups plugin
        // add_filter('woocommerce_get_cart_item_from_session', array($this, 'apply_pricing_from_session'), 10, 3);
        
        // NOTE: Cart display pricing handled by Customer Groups plugin
        // add_filter('woocommerce_cart_item_price', array($this, 'display_custom_cart_price'), 10, 3);
        // add_filter('woocommerce_cart_item_subtotal', array($this, 'display_custom_cart_subtotal'), 10, 3);
        
        // NOTE: Cart subtotal handled by Customer Groups plugin
        // add_filter('woocommerce_cart_subtotal', array($this, 'fix_cart_subtotal'), 10, 3);
        // NOTE: Cart total handled by Customer Groups plugin
        // add_filter('woocommerce_cart_total', array($this, 'fix_cart_total'), 10, 1);
        
        // Display discount information on cart/checkout (display only, doesn't affect pricing)
        add_filter('woocommerce_cart_item_name', array($this, 'add_discount_info_to_cart_item'), 10, 3);
        
        // NOTE: Order line item pricing handled by Customer Groups plugin via WooCommerce's standard pricing flow
        // add_action('woocommerce_checkout_create_order_line_item', array($this, 'set_order_item_custom_price'), 10, 4);
        // add_action('woocommerce_checkout_create_order', array($this, 'recalculate_order_totals'), 20, 2);
        
        // NOTE: Order creation pricing handled by Customer Groups plugin
        // add_action('woocommerce_new_order', array($this, 'fix_order_pricing_on_creation'), 10, 1);
        
        // Hook into order before it's sent to payment gateway
        add_filter('woocommerce_order_get_total', array($this, 'filter_order_total_for_payment'), 10, 2);
        add_action('woocommerce_before_pay_action', array($this, 'fix_order_before_payment'), 10, 1);
        
        // NOTE: Cart item pricing handled by Customer Groups plugin
        // add_filter('woocommerce_cart_item_subtotal', array($this, 'ensure_cart_item_uses_custom_price'), 9999, 3);
        // add_filter('woocommerce_add_cart_item', array($this, 'ensure_custom_price_on_cart_item'), 10, 1);
        
        // Hook specifically for Payment Plugins for PayPal WooCommerce
        add_filter('wc_ppcp_cart_data', array($this, 'fix_paypal_cart_data'), 10, 1);
        
        // Filter the actual cart total that PayPal reads
        add_filter('woocommerce_cart_get_total', array($this, 'override_cart_total'), 9999, 1);
    }

    private function log($message) {
        if (self::DEBUG) {
            error_log('[Alynt WC Customer Order Manager] ' . $message);
        }
    }

    private function get_adjusted_price($product_id, $customer_id, $original_price) {
        // Add debugging
        $this->log('Getting adjusted price:');
        $this->log("Product ID: $product_id");
        $this->log("Customer ID: $customer_id");
        $this->log("Original Price: $original_price");

        // Check if wccg_get_adjusted_price function exists (Customer Groups plugin is active)
        if (function_exists('wccg_get_adjusted_price')) {
            $adjusted_price = wccg_get_adjusted_price($product_id, $customer_id, $original_price);
            $this->log("Adjusted Price: $adjusted_price");
            if (function_exists('wccg_get_user_group')) {
                $group_id = wccg_get_user_group($customer_id);
                $this->log("Customer Group ID: $group_id");
            }
            return $adjusted_price;
        }
        $this->log("wccg_get_adjusted_price function not found - returning original price");
        return $original_price;
    }

    public function handle_order_creation() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'create_order') {
            return;
        }

        if (!isset($_POST['awcom_order_nonce']) || 
            !wp_verify_nonce($_POST['awcom_order_nonce'], 'create_order')) {
            wp_die(__('Security check failed', 'alynt-wc-customer-order-manager'));
    }

    if (!Security::user_can_access()) {
        wp_die(__('You do not have sufficient permissions', 'alynt-wc-customer-order-manager'));
    }

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    if (!$customer_id) {
        wp_die(__('Invalid customer ID.', 'alynt-wc-customer-order-manager'));
    }

        // Validate items
    if (empty($_POST['items']) || !is_array($_POST['items'])) {
        $this->redirect_with_error($customer_id, __('No items selected.', 'alynt-wc-customer-order-manager'));
    }

        // Validate shipping method
    if (empty($_POST['shipping_method'])) {
        $this->redirect_with_error($customer_id, __('Please select a shipping method.', 'alynt-wc-customer-order-manager'));
    }

    try {
        // Create the order
        $order = wc_create_order(array(
        'customer_id' => $customer_id,
        'created_via' => 'alynt_wc_customer_order_manager'
    ));

        // Add items to order
foreach ($_POST['items'] as $product_id => $item_data) {
    $product = wc_get_product($product_id);
    if (!$product) {
        continue;
    }

    $quantity = isset($item_data['quantity']) ? absint($item_data['quantity']) : 1;

    try {
        // Get base price: use sale price if on sale, otherwise regular price
        // This respects WooCommerce sales while avoiding double-discount issues
        $sale_price = $product->get_sale_price();
        $original_price = !empty($sale_price) && $sale_price > 0 ? $sale_price : $product->get_regular_price();

        // Get customer's group
        global $wpdb;
        $group_id = $wpdb->get_var($wpdb->prepare(
            "SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
            $customer_id
        ));

        $adjusted_price = $original_price;
        $discount_description = '';

        if ($group_id) {
            // Check for product-specific rules first
            $product_rule = $wpdb->get_row($wpdb->prepare(
                "SELECT pr.*, g.group_name 
                FROM {$wpdb->prefix}pricing_rules pr
                JOIN {$wpdb->prefix}rule_products rp ON pr.rule_id = rp.rule_id
                JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
                WHERE pr.group_id = %d AND rp.product_id = %d
                ORDER BY pr.created_at DESC
                LIMIT 1",
                $group_id,
                $product_id
            ));

            if ($product_rule) {
                if ($product_rule->discount_type === 'percentage') {
                    $adjusted_price = $original_price - (($product_rule->discount_value / 100) * $original_price);
                    $discount_description = sprintf('%s Group Discount: %s%%', $product_rule->group_name, $product_rule->discount_value);
                } else {
                    $adjusted_price = $original_price - $product_rule->discount_value;
                    $discount_description = sprintf('%s Group Discount: %s', $product_rule->group_name, wc_price($product_rule->discount_value));
                }
            } else {
                // Check for category rules if no product rule exists
                $category_ids = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
                if (!empty($category_ids)) {
                    $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
                    $query = $wpdb->prepare(
                        "SELECT pr.*, g.group_name 
                        FROM {$wpdb->prefix}pricing_rules pr
                        JOIN {$wpdb->prefix}rule_categories rc ON pr.rule_id = rc.rule_id
                        JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
                        WHERE pr.group_id = %d AND rc.category_id IN ($placeholders)
                        ORDER BY pr.created_at DESC
                        LIMIT 1",
                        array_merge(array($group_id), $category_ids)
                    );
                    $category_rule = $wpdb->get_row($query);

                    if ($category_rule) {
                        if ($category_rule->discount_type === 'percentage') {
                            $adjusted_price = $original_price - (($category_rule->discount_value / 100) * $original_price);
                            $discount_description = sprintf('%s Group Discount: %s%%', $category_rule->group_name, $category_rule->discount_value);
                        } else {
                            $adjusted_price = $original_price - $category_rule->discount_value;
                            $discount_description = sprintf('%s Group Discount: %s', $category_rule->group_name, wc_price($category_rule->discount_value));
                        }
                    }
                }
            }
        }

        // Ensure price doesn't go below zero
        $adjusted_price = max(0, $adjusted_price);

        // Add product to order
        $item = new WC_Order_Item_Product();
        $item->set_props(array(
            'product'  => $product,
            'quantity' => $quantity,
            'subtotal' => $original_price * $quantity,  // Current price (includes WC sale prices)
            'total'    => $adjusted_price * $quantity,  // Current price minus customer group discount
        ));
        
        // Store custom pricing in meta to protect against recalculation
        $item->add_meta_data('_custom_price', $adjusted_price, true);
        $item->add_meta_data('_custom_subtotal_price', $original_price, true);

        // Add discount information if there is a discount
        if ($adjusted_price < $original_price) {
            $item->add_meta_data('_discount_description', $discount_description);

            // Calculate the discount amount
            $discount_amount = ($original_price - $adjusted_price) * $quantity;
            $item->set_subtotal($original_price * $quantity);
            $item->set_total($adjusted_price * $quantity);

            // Add the discount to the order
            $order->add_order_note(sprintf(
                'Applied %s - Original Price: %s, Discounted Price: %s, Total Discount: %s',
                $discount_description,
                wc_price($original_price),
                wc_price($adjusted_price),
                wc_price($discount_amount)
            ));
        }

        // Add the item to the order
        $order->add_item($item);

    } catch (Exception $e) {
        $this->redirect_with_error($customer_id, sprintf(
            __('Error adding product "%s" to order: %s', 'alynt-wc-customer-order-manager'),
            $product->get_name(),
            $e->getMessage()
        ));
        return;
    }
}

        // Add customer data
        $this->add_customer_data_to_order($order, $customer_id);

        // Add shipping
        try {
            $this->add_shipping_to_order($order, $_POST['shipping_method']);
        } catch (Exception $e) {
            $this->redirect_with_error($customer_id, __('Error adding shipping method: ', 'alynt-wc-customer-order-manager') . $e->getMessage());
            return;
        }

        // Add order notes if provided
        if (!empty($_POST['order_notes'])) {
            $order->add_order_note(sanitize_textarea_field($_POST['order_notes']), 0, true);
        }

        // Calculate totals - don't force recalculation to preserve discounted line item totals
        $order->calculate_totals(false);

        // In handle_order_creation():
        // Before saving the order
        if ($order->get_shipping_total() <= 0) {
            $this->log('Warning: Zero or negative shipping total detected');
            
            // Optionally retry shipping calculation
            $order->calculate_shipping();
            $order->calculate_totals(false);
        }

        if (!$order->get_items('shipping')) {
            $this->log('Warning: No shipping items found in order');
        }

        // Mark order as having custom pricing to prevent recalculation
        $order->update_meta_data('_has_custom_pricing', 'yes');
        $order->update_meta_data('_pricing_locked', 'yes');
        
        // Lock the order total to prevent WooCommerce from recalculating it
        $order->update_meta_data('_locked_total', $order->get_total());
        
        $this->log('Order Creation: Locked total for order #' . $order->get_id() . ' at ' . $order->get_total());

        // Save order
        $order->save();

        // Add admin note
        $admin_note = sprintf(
            __('Order created via Alynt WC Customer Order Manager by %s', 'alynt-wc-customer-order-manager'),
            wp_get_current_user()->display_name
        );
        $order->add_order_note($admin_note, 0, false);

            // Log the creation
        $this->log_order_creation($order->get_id(), $customer_id);

            // Redirect to the edit order page
        wp_redirect(admin_url('post.php?post=' . $order->get_id() . '&action=edit'));
        exit;

    } catch (\Exception $e) {
        $this->redirect_with_error($customer_id, $e->getMessage());
    }
}

private function add_customer_data_to_order($order, $customer_id) {
    $customer = get_user_by('id', $customer_id);
    if (!$customer) {
        return;
    }

        // Billing address
    $billing_fields = array(
        'first_name',
        'last_name',
        'company',
        'address_1',
        'address_2',
        'city',
        'state',
        'postcode',
        'country',
        'email',
        'phone'
    );

    foreach ($billing_fields as $field) {
        $value = '';
        if ($field === 'email') {
            $value = $customer->user_email;
        } elseif ($field === 'first_name') {
            $value = $customer->first_name;
        } elseif ($field === 'last_name') {
            $value = $customer->last_name;
        } else {
            $value = get_user_meta($customer_id, 'billing_' . $field, true);
        }
        $order->{"set_billing_$field"}($value);
    }

        // Copy billing to shipping if no shipping address is set
    $shipping_fields = array(
        'first_name',
        'last_name',
        'company',
        'address_1',
        'address_2',
        'city',
        'state',
        'postcode',
        'country'
    );

    foreach ($shipping_fields as $field) {
        $shipping_value = get_user_meta($customer_id, 'shipping_' . $field, true);
        if (empty($shipping_value)) {
            $shipping_value = get_user_meta($customer_id, 'billing_' . $field, true);
        }
        $order->{"set_shipping_$field"}($shipping_value);
    }
}

private function add_shipping_to_order($order, $shipping_method) {
    try {
        // Get shipping methods
        $available_methods = $this->get_available_shipping_methods($order);
        $this->log('Available shipping methods: ' . print_r($available_methods, true));
        $this->log('Selected shipping method: ' . $shipping_method);

        // If no methods available, provide detailed error
        if (empty($available_methods)) {
            $error_details = '';
            if (!empty($this->shipping_errors)) {
                $error_details = ' Details: ' . implode(' | ', $this->shipping_errors);
            }
            throw new Exception('No shipping methods available for this destination.' . $error_details);
        }

        // Parse the shipping method ID
        $parts = explode(':', $shipping_method);
        $method_id = $parts[0] ?? '';
        $rate_id = end($parts); // Get the last part of the ID

        foreach ($available_methods as $method) {
            // Match on the exact full shipping method ID to ensure the correct method is selected
            if ($shipping_method === $method['id']) {
                $item = new WC_Order_Item_Shipping();

                // Validate shipping cost
                $shipping_cost = !empty($method['cost']) ? floatval($method['cost']) : 0;
                $this->log('Setting shipping cost: ' . $shipping_cost);

                $item->set_props(array(
                    'method_title' => $method['label'],
                    'method_id'    => $method_id,
                    'instance_id'  => $parts[1] ?? '',
                    'total'        => $shipping_cost,
                    'taxes'        => $method['taxes'] ?? array('total' => array()),
                    'rate_id'      => $shipping_method // Store the full rate ID
                ));

                // Add the shipping line item to the order
                $order->add_item($item);

                // Ensure the shipping total is set on the order
                $order->set_shipping_total($shipping_cost);
                $order->set_shipping_tax(0);

                $this->log('Shipping added to order: ' . print_r($item->get_data(), true));
                return true;
            }
        }

        // Method not found in available methods
        $available_ids = array_column($available_methods, 'id');
        throw new Exception('Selected shipping method "' . $shipping_method . '" not found. Available: ' . implode(', ', $available_ids));
    } catch (Exception $e) {
        $this->log('Shipping error: ' . $e->getMessage());
        // Re-throw to pass detailed error message up
        throw $e;
    }
}

private function get_available_shipping_methods($order) {
    $methods = array();
    $errors = array();

    try {
        // Build the package
        $package = array(
            'contents' => array(),
            'contents_cost' => 0,
            'applied_coupons' => array(),
            'destination' => array(
                'country'   => $order->get_shipping_country() ?: '',
                'state'     => $order->get_shipping_state() ?: '',
                'postcode'  => $order->get_shipping_postcode() ?: '',
                'city'      => $order->get_shipping_city() ?: '',
                'address'   => $order->get_shipping_address_1() ?: '',
                'address_2' => $order->get_shipping_address_2() ?: '',
            ),
            'user' => array('ID' => $order->get_customer_id()),
        );

        // Add items to package
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $package['contents'][] = array(
                    'data' => $product,
                    'quantity' => $item->get_quantity(),
                    'product_id' => $product->get_id(),
                    'variation_id' => $product->get_id(),
                    'variation' => array(),
                    'line_total' => $item->get_total(),
                    'line_tax' => $item->get_total_tax(),
                    'line_subtotal' => $item->get_subtotal(),
                    'line_subtotal_tax' => $item->get_subtotal_tax(),
                );
                $package['contents_cost'] += $item->get_total();
            }
        }

        $this->log('Shipping package destination: ' . print_r($package['destination'], true));
        $this->log('Shipping package items count: ' . count($package['contents']));

        // Calculate shipping for package
        $shipping_zone = WC_Shipping_Zones::get_zone_matching_package($package);
        $this->log('Matched shipping zone: ' . $shipping_zone->get_zone_name() . ' (ID: ' . $shipping_zone->get_id() . ')');
        
        $shipping_methods = $shipping_zone->get_shipping_methods(true);
        $this->log('Enabled shipping methods in zone: ' . count($shipping_methods));

        foreach ($shipping_methods as $method) {
            if ($method->is_enabled()) {
                $this->log('Processing shipping method: ' . $method->id . ' - ' . $method->get_method_title());
                $this->log('Method class: ' . get_class($method));
                $this->log('Method instance ID: ' . $method->get_instance_id());
                
                try {
                    // Check if method has any stored errors before calling
                    if (method_exists($method, 'get_errors') && is_callable(array($method, 'get_errors'))) {
                        $pre_errors = $method->get_errors();
                        if (!empty($pre_errors)) {
                            $this->log('USPS pre-existing errors: ' . print_r($pre_errors, true));
                        }
                    }
                    
                    // Call get_rates_for_package and capture any output
                    ob_start();
                    $rates = $method->get_rates_for_package($package);
                    $output = ob_get_clean();
                    
                    if (!empty($output)) {
                        $this->log('USPS output during rate calculation: ' . $output);
                    }
                    
                    // Check for errors after the call
                    if (method_exists($method, 'get_errors') && is_callable(array($method, 'get_errors'))) {
                        $post_errors = $method->get_errors();
                        if (!empty($post_errors)) {
                            $this->log('USPS errors after rate calculation: ' . print_r($post_errors, true));
                            $errors[] = sprintf('USPS plugin errors: %s', implode(', ', $post_errors));
                        }
                    }
                    
                    // Check if method has debug property with information
                    if (property_exists($method, 'debug') && !empty($method->debug)) {
                        $this->log('USPS debug info: ' . print_r($method->debug, true));
                    }
                    
                    if ($rates) {
                        $this->log('Found ' . count($rates) . ' rates for method: ' . $method->id);
                        
                        foreach ($rates as $rate_id => $rate) {
                            $methods[] = array(
                                'id' => $rate_id,
                                'method_id' => $method->id,
                                'instance_id' => $method->get_instance_id(),
                                'label' => $rate->get_label(),
                                'cost' => $rate->get_cost(),
                                'taxes' => $rate->get_taxes()
                            );
                            $this->log('Added rate: ' . $rate_id . ' - ' . $rate->get_label() . ' ($' . $rate->get_cost() . ')');
                        }
                    } else {
                        $this->log('No rates returned for method: ' . $method->id);
                        $this->log('USPS returned: ' . var_export($rates, true));
                        
                        // Try to get more info about why no rates
                        if (method_exists($method, 'get_instance_form_fields')) {
                            $fields = $method->get_instance_form_fields();
                            $this->log('USPS instance settings fields: ' . print_r(array_keys($fields), true));
                        }
                        
                        $errors[] = sprintf('Shipping method "%s" returned no rates for this destination', $method->get_method_title());
                    }
                } catch (Exception $e) {
                    $error_message = sprintf('Shipping method "%s" error: %s', $method->get_method_title(), $e->getMessage());
                    $this->log($error_message);
                    $this->log('Exception trace: ' . $e->getTraceAsString());
                    $errors[] = $error_message;
                } catch (\Throwable $t) {
                    // Catch any fatal errors or PHP 7+ throwables
                    $error_message = sprintf('Shipping method "%s" fatal error: %s', $method->get_method_title(), $t->getMessage());
                    $this->log($error_message);
                    $this->log('Throwable trace: ' . $t->getTraceAsString());
                    $errors[] = $error_message;
                }
            }
        }

        $this->log('Total available shipping methods calculated: ' . count($methods));
        
        if (!empty($errors)) {
            $this->log('Shipping calculation errors: ' . print_r($errors, true));
        }
    } catch (Exception $e) {
        $error_message = 'Critical error getting shipping methods: ' . $e->getMessage();
        $this->log($error_message);
        $errors[] = $error_message;
    }

    // Store errors for later retrieval
    $this->shipping_errors = $errors;

    return $methods;
}

private function log_order_creation($order_id, $customer_id) {
    $current_user = wp_get_current_user();
    $log_entry = sprintf(
        __('Order #%1$s created for customer #%2$s by %3$s', 'alynt-wc-customer-order-manager'),
        $order_id,
        $customer_id,
        $current_user->display_name
    );

    $this->log($log_entry);
}

private function redirect_with_error($customer_id, $error_message) {
    wp_redirect(add_query_arg(
        array(
            'page' => 'alynt-wc-customer-order-manager-create-order',
            'customer_id' => $customer_id,
            'error' => urlencode($error_message)
        ),
        admin_url('admin.php')
    ));
    exit;
}

/**
 * Prevent WooCommerce from recalculating totals for orders created by our plugin
 * This preserves the customer group pricing that was applied during order creation
 * Only applies on frontend/payment pages - admin can still modify orders
 */
public function prevent_total_recalculation($and_taxes, $order) {
    // Skip refunds - they don't have get_created_via() method
    if (is_a($order, 'WC_Order_Refund')) {
        return;
    }
    
    // In admin, allow recalculation to proceed (fees, shipping, etc.)
    // The preserve_item_* filters will protect the line item prices
    if (is_admin()) {
        return;
    }
    
    // On frontend/payment pages, prevent ALL recalculation to preserve custom pricing
    if ($order->get_created_via() === 'alynt_wc_customer_order_manager') {
        $this->log('Preventing total recalculation for order #' . $order->get_id() . ' to preserve customer group pricing');
        
        // Stop the calculation
        remove_action('woocommerce_order_before_calculate_totals', array($this, 'prevent_total_recalculation'), 10);
        return false;
    }
    
    // Also prevent for orders with custom pricing metadata
    if ($order->get_meta('_has_custom_pricing') === 'yes') {
        $this->log('Preventing total recalculation for order #' . $order->get_id() . ' - has custom pricing flag');
        remove_action('woocommerce_order_before_calculate_totals', array($this, 'prevent_total_recalculation'), 10);
        return false;
    }
}

/**
 * Restore custom prices after WooCommerce recalculates totals
 * This runs after calculate_totals() completes and restores our custom pricing
 */
public function restore_custom_prices_after_recalculation($and_taxes, $order) {
    // Skip refunds
    if (is_a($order, 'WC_Order_Refund')) {
        return;
    }
    
    // Only restore for orders with custom pricing
    if ($order->get_created_via() !== 'alynt_wc_customer_order_manager' && $order->get_meta('_has_custom_pricing') !== 'yes') {
        return;
    }
    
    $this->log('Restoring custom prices after recalculation for order #' . $order->get_id());
    
    $needs_save = false;
    
    // Loop through line items and restore custom prices from meta
    foreach ($order->get_items() as $item_id => $item) {
        $custom_price = $item->get_meta('_custom_price', true);
        $custom_subtotal_price = $item->get_meta('_custom_subtotal_price', true);
        
        if ($custom_price && $custom_subtotal_price) {
            $quantity = $item->get_quantity();
            
            // Restore the custom prices
            $item->set_subtotal($custom_subtotal_price * $quantity);
            $item->set_total($custom_price * $quantity);
            $item->save();
            
            $needs_save = true;
            
            $this->log(sprintf(
                'Restored custom price for item #%d: subtotal=%s, total=%s',
                $item_id,
                $custom_subtotal_price * $quantity,
                $custom_price * $quantity
            ));
        }
    }
    
    // Recalculate the order total from the restored line items
    // IMPORTANT: Remove this hook before recalculating to prevent infinite loop
    if ($needs_save) {
        remove_action('woocommerce_order_after_calculate_totals', array($this, 'restore_custom_prices_after_recalculation'), 10);
        $order->calculate_totals(false); // Don't force recalculation of line items
        add_action('woocommerce_order_after_calculate_totals', array($this, 'restore_custom_prices_after_recalculation'), 10, 2);
        $this->log('Order #' . $order->get_id() . ' total after restoring custom prices: ' . $order->get_total());
    }
}

/**
 * Preserve order item subtotal (prevents recalculation on payment page)
 */
public function preserve_item_subtotal($subtotal, $item) {
    $order = $item->get_order();
    
    // Skip refunds - they don't have get_created_via() method
    if (!$order || is_a($order, 'WC_Order_Refund')) {
        return $subtotal;
    }
    
    // Always preserve line item subtotals to maintain custom pricing
    // This applies everywhere (admin and frontend)
    
    if ($order->get_created_via() === 'alynt_wc_customer_order_manager' || $order->get_meta('_has_custom_pricing') === 'yes') {
        // Return the already-stored subtotal (don't let WC recalculate it from product price)
        // The $subtotal parameter already contains the stored value before any recalculation
        return $subtotal;
    }
    return $subtotal;
}

/**
 * Preserve order item total (prevents recalculation on payment page)
 */
public function preserve_item_total($total, $item) {
    $order = $item->get_order();
    
    // Skip refunds - they don't have get_created_via() method
    if (!$order || is_a($order, 'WC_Order_Refund')) {
        return $total;
    }
    
    // Always preserve line item totals to maintain custom pricing
    // This applies everywhere (admin and frontend)
    
    if ($order->get_created_via() === 'alynt_wc_customer_order_manager' || $order->get_meta('_has_custom_pricing') === 'yes') {
        // Return the already-stored total (don't let WC recalculate it from product price)
        // The $total parameter already contains the stored value before any recalculation
        return $total;
    }
    return $total;
}

/**
 * Lock the order total to prevent WooCommerce from recalculating it
 * This is the final protection layer for the payment page
 */
public function lock_order_total($total, $order) {
    // Skip refunds - they don't have get_created_via() method
    if (is_a($order, 'WC_Order_Refund')) {
        return $total;
    }
    
    // Don't lock in admin - allow manual adjustments (fees, coupons, etc.)
    // This includes both regular admin pages and admin AJAX calls (like recalculate button)
    if (is_admin()) {
        return $total;
    }
    
    // Only lock totals for orders with custom pricing
    if ($order->get_created_via() === 'alynt_wc_customer_order_manager' || $order->get_meta('_has_custom_pricing') === 'yes') {
        // Get the stored total from order meta
        $locked_total = $order->get_meta('_locked_total');
        
        if ($locked_total) {
            $this->log('Order Total Lock: Returning locked total ' . $locked_total . ' for order #' . $order->get_id() . ' (passed in: ' . $total . ')');
            return $locked_total;
        }
        
        // For older orders without locked total, calculate from order items directly
        $this->log('Order Total Lock: No locked total metadata, calculating from order items for #' . $order->get_id());
        
        $calculated_total = 0;
        
        // Add item totals (these have the discounted prices)
        foreach ($order->get_items() as $item) {
            $calculated_total += $item->get_total();
        }
        
        // Add shipping
        $calculated_total += $order->get_shipping_total();
        
        // Add fees
        foreach ($order->get_fees() as $fee) {
            $calculated_total += $fee->get_total();
        }
        
        // Add taxes
        $calculated_total += $order->get_total_tax();
        
        $this->log('Order Total Lock: Calculated total from items: ' . $calculated_total . ' (passed in total was: ' . $total . ')');
        
        // Store this as the locked total for next time
        $order->update_meta_data('_locked_total', $calculated_total);
        $order->save_meta_data();
        
        return $calculated_total;
    }
    
    return $total;
}

/**
 * Apply customer group pricing to cart items
 * This ensures discounts are applied in the cart and checkout
 */
public function apply_cart_customer_pricing($cart) {
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    // Prevent infinite loops
    static $running = false;
    if ($running) {
        return;
    }
    $running = true;

    $customer_id = get_current_user_id();
    if (!$customer_id) {
        $running = false;
        return;
    }

    $this->log('Cart pricing: Starting for customer #' . $customer_id);

    // Get customer's group
    global $wpdb;
    $group_id = $wpdb->get_var($wpdb->prepare(
        "SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
        $customer_id
    ));

    if (!$group_id) {
        $this->log('Cart pricing: No group found for customer #' . $customer_id);
        $running = false;
        return;
    }

    $this->log('Cart pricing: Customer is in group #' . $group_id);

    // Loop through cart items
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();
        $original_price = $product->get_regular_price();

        $this->log("Cart pricing: Processing product #{$product_id}, original price: {$original_price}");

        if (empty($original_price) || !is_numeric($original_price)) {
            continue;
        }

        $adjusted_price = $original_price;

        // Check for product-specific rules
        $product_rule = $wpdb->get_row($wpdb->prepare(
            "SELECT pr.*, g.group_name 
            FROM {$wpdb->prefix}pricing_rules pr
            JOIN {$wpdb->prefix}rule_products rp ON pr.rule_id = rp.rule_id
            JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
            WHERE pr.group_id = %d AND rp.product_id = %d
            ORDER BY pr.created_at DESC
            LIMIT 1",
            $group_id,
            $product_id
        ));

        $group_name = '';
        
        if ($product_rule) {
            if ($product_rule->discount_type === 'percentage') {
                $adjusted_price = $original_price - (($product_rule->discount_value / 100) * $original_price);
            } else {
                $adjusted_price = $original_price - $product_rule->discount_value;
            }
            $group_name = $product_rule->group_name;
            $this->log("Cart pricing: Applied product rule, adjusted price: {$adjusted_price}");
        } else {
            // Check for category rules
            $category_ids = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            if (!empty($category_ids) && !is_wp_error($category_ids)) {
                $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
                $query = $wpdb->prepare(
                    "SELECT pr.*, g.group_name 
                    FROM {$wpdb->prefix}pricing_rules pr
                    JOIN {$wpdb->prefix}rule_categories rc ON pr.rule_id = rc.rule_id
                    JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
                    WHERE pr.group_id = %d AND rc.category_id IN ($placeholders)
                    ORDER BY pr.created_at DESC
                    LIMIT 1",
                    array_merge(array($group_id), $category_ids)
                );
                $category_rule = $wpdb->get_row($query);

                if ($category_rule) {
                    if ($category_rule->discount_type === 'percentage') {
                        $adjusted_price = $original_price - (($category_rule->discount_value / 100) * $original_price);
                    } else {
                        $adjusted_price = $original_price - $category_rule->discount_value;
                    }
                    $group_name = $category_rule->group_name;
                    $this->log("Cart pricing: Applied category rule, adjusted price: {$adjusted_price}");
                }
            }
        }

        // Apply the adjusted price to the cart item
        if ($adjusted_price < $original_price) {
            $adjusted_price = max(0, $adjusted_price);
            
            // Set price on product object
            $product->set_price($adjusted_price);
            
            // CRITICAL: Also update the line_subtotal and line_total directly in cart
            $cart->cart_contents[$cart_item_key]['line_subtotal'] = $adjusted_price * $cart_item['quantity'];
            $cart->cart_contents[$cart_item_key]['line_total'] = $adjusted_price * $cart_item['quantity'];
            $cart->cart_contents[$cart_item_key]['line_tax'] = 0;
            $cart->cart_contents[$cart_item_key]['line_subtotal_tax'] = 0;
            
            // Store in cart item data for display
            $cart->cart_contents[$cart_item_key]['awcom_custom_price'] = $adjusted_price;
            $cart->cart_contents[$cart_item_key]['awcom_group_name'] = $group_name;
            
            $this->log("Cart pricing: Set price to {$adjusted_price} for product #{$product_id} with {$group_name} pricing");
            $this->log("Cart pricing: Set line_total to " . ($adjusted_price * $cart_item['quantity']));
        }
    }

    $running = false;
    $this->log('Cart pricing: Completed');
}

/**
 * Apply pricing when cart items are loaded from session
 * This ensures discounts persist across page loads
 */
public function apply_pricing_from_session($session_data, $values, $key) {
    if (!is_user_logged_in()) {
        return $session_data;
    }

    $customer_id = get_current_user_id();
    if (!$customer_id) {
        return $session_data;
    }

    // Get customer's group
    global $wpdb;
    $group_id = $wpdb->get_var($wpdb->prepare(
        "SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
        $customer_id
    ));

    if (!$group_id) {
        return $session_data;
    }

    // Get product from session data
    $product = $session_data['data'];
    $product_id = $product->get_id();
    $original_price = $product->get_regular_price();

    if (empty($original_price) || !is_numeric($original_price)) {
        return $session_data;
    }

    $adjusted_price = $original_price;
    $group_name = '';

    // Check for product-specific rules
    $product_rule = $wpdb->get_row($wpdb->prepare(
        "SELECT pr.*, g.group_name 
        FROM {$wpdb->prefix}pricing_rules pr
        JOIN {$wpdb->prefix}rule_products rp ON pr.rule_id = rp.rule_id
        JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
        WHERE pr.group_id = %d AND rp.product_id = %d
        ORDER BY pr.created_at DESC
        LIMIT 1",
        $group_id,
        $product_id
    ));

    if ($product_rule) {
        if ($product_rule->discount_type === 'percentage') {
            $adjusted_price = $original_price - (($product_rule->discount_value / 100) * $original_price);
        } else {
            $adjusted_price = $original_price - $product_rule->discount_value;
        }
        $group_name = $product_rule->group_name;
    } else {
        // Check for category rules
        $category_ids = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        if (!empty($category_ids) && !is_wp_error($category_ids)) {
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT pr.*, g.group_name 
                FROM {$wpdb->prefix}pricing_rules pr
                JOIN {$wpdb->prefix}rule_categories rc ON pr.rule_id = rc.rule_id
                JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
                WHERE pr.group_id = %d AND rc.category_id IN ($placeholders)
                ORDER BY pr.created_at DESC
                LIMIT 1",
                array_merge(array($group_id), $category_ids)
            );
            $category_rule = $wpdb->get_row($query);

            if ($category_rule) {
                if ($category_rule->discount_type === 'percentage') {
                    $adjusted_price = $original_price - (($category_rule->discount_value / 100) * $original_price);
                } else {
                    $adjusted_price = $original_price - $category_rule->discount_value;
                }
                $group_name = $category_rule->group_name;
            }
        }
    }

    // Apply the adjusted price
    if ($adjusted_price < $original_price) {
        $adjusted_price = max(0, $adjusted_price);
        $session_data['data']->set_price($adjusted_price);
        
        // Store custom price and group name in session data
        $session_data['awcom_custom_price'] = $adjusted_price;
        $session_data['awcom_group_name'] = $group_name;
        
        $this->log("Session pricing: Set price to {$adjusted_price} for product #{$product_id} with {$group_name} pricing");
    }

    return $session_data;
}

/**
 * Display custom cart price with customer group discount
 */
public function display_custom_cart_price($price_html, $cart_item, $cart_item_key) {
    if (!is_user_logged_in()) {
        return $price_html;
    }

    // Check if we have a custom price stored in cart item
    if (isset($cart_item['awcom_custom_price'])) {
        $this->log("Cart display: Using stored custom price {$cart_item['awcom_custom_price']}");
        return wc_price($cart_item['awcom_custom_price']);
    }

    $product = $cart_item['data'];
    $discounted_price = $product->get_price();
    
    $this->log("Cart display: Showing price {$discounted_price} for product #{$product->get_id()}");
    
    return wc_price($discounted_price);
}

/**
 * Display custom cart subtotal with customer group discount
 */
public function display_custom_cart_subtotal($subtotal_html, $cart_item, $cart_item_key) {
    if (!is_user_logged_in()) {
        return $subtotal_html;
    }

    // Check if we have a custom price stored in cart item
    if (isset($cart_item['awcom_custom_price'])) {
        $quantity = $cart_item['quantity'];
        $subtotal = $cart_item['awcom_custom_price'] * $quantity;
        $this->log("Cart display: Using stored custom price for subtotal {$subtotal}");
        return wc_price($subtotal);
    }

    $product = $cart_item['data'];
    $discounted_price = $product->get_price();
    $quantity = $cart_item['quantity'];
    
    $subtotal = $discounted_price * $quantity;
    
    $this->log("Cart display: Showing subtotal {$subtotal} for product #{$product->get_id()}");
    
    return wc_price($subtotal);
}

/**
 * Fix cart subtotal to use custom pricing
 */
public function fix_cart_subtotal($cart_subtotal, $compound, $cart) {
    if (!is_user_logged_in()) {
        return $cart_subtotal;
    }

    $subtotal = 0;
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['awcom_custom_price'])) {
            $subtotal += $cart_item['awcom_custom_price'] * $cart_item['quantity'];
        } else {
            $subtotal += $cart_item['data']->get_price() * $cart_item['quantity'];
        }
    }

    $this->log("Cart totals: Calculated subtotal = {$subtotal}");
    
    return wc_price($subtotal);
}

/**
 * Fix cart total to use custom pricing
 */
public function fix_cart_total($total) {
    if (!is_user_logged_in()) {
        return $total;
    }

    $cart = WC()->cart;
    if (!$cart) {
        return $total;
    }

    $cart_total = 0;
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['awcom_custom_price'])) {
            $cart_total += $cart_item['awcom_custom_price'] * $cart_item['quantity'];
        } else {
            $cart_total += $cart_item['data']->get_price() * $cart_item['quantity'];
        }
    }

    // Add shipping if applicable
    if ($cart->needs_shipping() && $cart->show_shipping()) {
        $cart_total += $cart->get_shipping_total();
    }

    // Add fees
    foreach ($cart->get_fees() as $fee) {
        $cart_total += $fee->total;
    }

    // Add taxes
    $cart_total += $cart->get_total_tax();

    $this->log("Cart totals: Calculated total = {$cart_total}");
    
    return wc_price($cart_total);
}

/**
 * Add discount information to cart item name
 * Gets group name directly from database (pricing is handled by Customer Groups plugin)
 */
public function add_discount_info_to_cart_item($product_name, $cart_item, $cart_item_key) {
    if (!is_user_logged_in()) {
        return $product_name;
    }

    // Get customer's group from database
    global $wpdb;
    $customer_id = get_current_user_id();
    
    $group_name = $wpdb->get_var($wpdb->prepare(
        "SELECT g.group_name 
        FROM {$wpdb->prefix}customer_groups g
        JOIN {$wpdb->prefix}user_groups ug ON g.group_id = ug.group_id
        WHERE ug.user_id = %d",
        $customer_id
    ));
    
    // If customer has a group, add the pricing label
    if ($group_name) {
        $discount_text = sprintf(
            '<br><small style="color: #3b5249; font-weight: 500;">%s Pricing Applied</small>',
            esc_html($group_name)
        );
        return $product_name . $discount_text;
    }

    return $product_name;
}

/**
 * Set custom pricing on order line items during checkout
 * This ensures the discounted price is saved to the actual order
 */
public function set_order_item_custom_price($item, $cart_item_key, $values, $order) {
    $this->log('Checkout: set_order_item_custom_price called for cart item key: ' . $cart_item_key);
    
    if (!is_user_logged_in()) {
        $this->log('Checkout: User not logged in, skipping');
        return;
    }

    // Check if this cart item has custom pricing
    if (isset($values['awcom_custom_price'])) {
        $custom_price = $values['awcom_custom_price'];
        $quantity = $item->get_quantity();
        
        $this->log(sprintf(
            'Checkout: Found custom price %s for product #%d (quantity: %d)',
            $custom_price,
            $item->get_product_id(),
            $quantity
        ));
        
        // Set the custom price on the order item
        $item->set_subtotal($custom_price * $quantity);
        $item->set_total($custom_price * $quantity);
        
        // Store the group name as metadata
        if (isset($values['awcom_group_name'])) {
            $item->add_meta_data('_customer_group', $values['awcom_group_name'], true);
        }
        
        $this->log(sprintf(
            'Checkout: Set order item subtotal=%s, total=%s for product #%d',
            $custom_price * $quantity,
            $custom_price * $quantity,
            $item->get_product_id()
        ));
    } else {
        $this->log('Checkout: No custom price found in cart item values');
    }
}

/**
 * Recalculate order totals after line items are added
 * This ensures the order total matches the custom pricing
 */
public function recalculate_order_totals($order, $data) {
    $this->log('Checkout: Recalculating order totals for order #' . $order->get_id());
    
    // Force recalculation of order totals
    $order->calculate_totals();
    
    $this->log('Checkout: Order total after recalculation: ' . $order->get_total());
}

/**
 * Fix order pricing when order is created from cart
 * This runs as a fallback when checkout hooks don't fire
 */
public function fix_order_pricing_on_creation($order_id) {
    $this->log('Order Creation: Fixing pricing for order #' . $order_id);
    
    if (!is_user_logged_in()) {
        $this->log('Order Creation: User not logged in, skipping');
        return;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        $this->log('Order Creation: Could not get order object');
        return;
    }
    
    $customer_id = get_current_user_id();
    
    // Get customer's group
    global $wpdb;
    $group_id = $wpdb->get_var($wpdb->prepare(
        "SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
        $customer_id
    ));
    
    if (!$group_id) {
        $this->log('Order Creation: No customer group found');
        return;
    }
    
    $this->log('Order Creation: Customer is in group #' . $group_id);
    
    $modified = false;
    
    // Loop through order items and apply custom pricing
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        $product = $item->get_product();
        
        if (!$product) {
            continue;
        }
        
        $original_price = $product->get_regular_price();
        $adjusted_price = $original_price;
        $group_name = '';
        
        // Check for product-specific rules
        $product_rule = $wpdb->get_row($wpdb->prepare(
            "SELECT pr.*, g.group_name 
            FROM {$wpdb->prefix}pricing_rules pr
            JOIN {$wpdb->prefix}rule_products rp ON pr.rule_id = rp.rule_id
            JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
            WHERE pr.group_id = %d AND rp.product_id = %d
            ORDER BY pr.created_at DESC
            LIMIT 1",
            $group_id,
            $product_id
        ));
        
        if ($product_rule) {
            if ($product_rule->discount_type === 'percentage') {
                $adjusted_price = $original_price - (($product_rule->discount_value / 100) * $original_price);
            } else {
                $adjusted_price = $original_price - $product_rule->discount_value;
            }
            $group_name = $product_rule->group_name;
        } else {
            // Check for category rules
            $category_ids = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            if (!empty($category_ids) && !is_wp_error($category_ids)) {
                $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
                $query = $wpdb->prepare(
                    "SELECT pr.*, g.group_name 
                    FROM {$wpdb->prefix}pricing_rules pr
                    JOIN {$wpdb->prefix}rule_categories rc ON pr.rule_id = rc.rule_id
                    JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
                    WHERE pr.group_id = %d AND rc.category_id IN ($placeholders)
                    ORDER BY pr.created_at DESC
                    LIMIT 1",
                    array_merge(array($group_id), $category_ids)
                );
                $category_rule = $wpdb->get_row($query);
                
                if ($category_rule) {
                    if ($category_rule->discount_type === 'percentage') {
                        $adjusted_price = $original_price - (($category_rule->discount_value / 100) * $original_price);
                    } else {
                        $adjusted_price = $original_price - $category_rule->discount_value;
                    }
                    $group_name = $category_rule->group_name;
                }
            }
        }
        
        // Apply adjusted price if different
        if ($adjusted_price < $original_price) {
            $adjusted_price = max(0, $adjusted_price);
            
            $item->set_subtotal($adjusted_price * $quantity);
            $item->set_total($adjusted_price * $quantity);
            $item->save();
            
            if ($group_name) {
                $item->add_meta_data('_customer_group', $group_name, true);
                $item->save();
            }
            
            $modified = true;
            
            $this->log(sprintf(
                'Order Creation: Updated item #%d - Product #%d from %s to %s (total: %s)',
                $item_id,
                $product_id,
                wc_price($original_price),
                wc_price($adjusted_price),
                wc_price($adjusted_price * $quantity)
            ));
        }
    }
    
    if ($modified) {
        // Recalculate order totals
        $order->calculate_totals();
        $order->save();
        
        $this->log('Order Creation: Order totals recalculated. New total: ' . $order->get_total());
    } else {
        $this->log('Order Creation: No pricing changes needed');
    }
}

/**
 * Filter order total when it's retrieved for payment gateway
 * DISABLED - This was causing incorrect totals by calculating from cart instead of order
 */
public function filter_order_total_for_payment($total, $order) {
    // Don't filter - let the order's stored total be used
    // The lock_order_total function will handle protecting custom pricing
    return $total;
}

/**
 * Fix order pricing before payment is processed
 */
public function fix_order_before_payment($order_id) {
    $this->log('Before Payment: Fixing order #' . $order_id);
    
    // Use the same logic as fix_order_pricing_on_creation
    $this->fix_order_pricing_on_creation($order_id);
}

/**
 * Ensure cart item uses custom price (for third-party payment plugins)
 */
public function ensure_cart_item_uses_custom_price($subtotal, $cart_item, $cart_item_key) {
    // This is a duplicate of display_custom_cart_subtotal but with higher priority
    // to ensure it runs after other plugins
    if (!is_user_logged_in()) {
        return $subtotal;
    }

    if (isset($cart_item['awcom_custom_price'])) {
        $quantity = $cart_item['quantity'];
        $custom_subtotal = $cart_item['awcom_custom_price'] * $quantity;
        
        // Also ensure the product object has the right price
        if (isset($cart_item['data'])) {
            $cart_item['data']->set_price($cart_item['awcom_custom_price']);
        }
        
        return wc_price($custom_subtotal);
    }

    return $subtotal;
}

/**
 * Ensure custom price is set on cart item when added
 */
public function ensure_custom_price_on_cart_item($cart_item) {
    if (!is_user_logged_in()) {
        return $cart_item;
    }

    // If custom price is set, make sure the product object reflects it
    if (isset($cart_item['awcom_custom_price']) && isset($cart_item['data'])) {
        $cart_item['data']->set_price($cart_item['awcom_custom_price']);
        $this->log('Cart Item: Set price to ' . $cart_item['awcom_custom_price'] . ' for product #' . $cart_item['data']->get_id());
    }

    return $cart_item;
}

/**
 * Override cart total when retrieved
 * This ensures PayPal and other plugins get the correct discounted total
 */
public function override_cart_total($total) {
    if (!is_user_logged_in()) {
        return $total;
    }

    $cart = WC()->cart;
    if (!$cart || $cart->is_empty()) {
        return $total;
    }

    // Calculate correct total
    $correct_total = 0;
    $has_custom_pricing = false;
    
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['awcom_custom_price'])) {
            $correct_total += $cart_item['awcom_custom_price'] * $cart_item['quantity'];
            $has_custom_pricing = true;
        } else {
            $correct_total += $cart_item['data']->get_price() * $cart_item['quantity'];
        }
    }
    
    // Only override if we have custom pricing
    if ($has_custom_pricing) {
        // Add shipping
        if ($cart->needs_shipping()) {
            $correct_total += $cart->get_shipping_total();
        }
        
        // Add taxes
        $correct_total += $cart->get_total_tax();
        
        // Add fees
        foreach ($cart->get_fees() as $fee) {
            $correct_total += $fee->total;
        }
        
        $this->log('Cart Total Override: Changing from ' . $total . ' to ' . $correct_total);
        
        return $correct_total;
    }
    
    return $total;
}

/**
 * Fix cart data sent to PayPal plugin
 * The Payment Plugins for PayPal plugin uses this filter to get cart data
 */
public function fix_paypal_cart_data($cart_data) {
    if (!is_user_logged_in()) {
        return $cart_data;
    }

    $this->log('PayPal: Original cart data total = ' . $cart_data['total']);

    // Calculate correct total from cart with custom pricing
    $cart = WC()->cart;
    if ($cart && !$cart->is_empty()) {
        $correct_total = 0;
        
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['awcom_custom_price'])) {
                $correct_total += $cart_item['awcom_custom_price'] * $cart_item['quantity'];
            } else {
                $correct_total += $cart_item['data']->get_price() * $cart_item['quantity'];
            }
        }
        
        // Add shipping
        if ($cart->needs_shipping()) {
            $correct_total += $cart->get_shipping_total();
        }
        
        // Add taxes
        $correct_total += $cart->get_total_tax();
        
        // Add fees
        foreach ($cart->get_fees() as $fee) {
            $correct_total += $fee->total;
        }
        
        $cart_data['total'] = round($correct_total, 2);
        
        $this->log('PayPal: Corrected cart data total = ' . $cart_data['total']);
    }

    return $cart_data;
}

/**
 * Apply customer group pricing to products in cart/shop for logged-in customers
 * This allows customers to see their discounted prices throughout the shopping experience
 */
public function apply_customer_group_pricing($price, $product) {
    // Wrap everything in try-catch to prevent breaking frontend
    try {
        // Only apply for logged-in customers
        if (!is_user_logged_in()) {
            return $price;
        }
        
        // Don't apply in admin area (except for AJAX requests)
        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }
        
        // Prevent infinite loops
        static $processing = array();
        
        // Get current user ID
        $customer_id = get_current_user_id();
        if (!$customer_id) {
            return $price;
        }
        
        // Get product ID
        $product_id = $product->get_id();
        if (!$product_id) {
            return $price;
        }
        
        // Check if we're already processing this product for this user
        $cache_key = $customer_id . '_' . $product_id;
        if (isset($processing[$cache_key])) {
            return $price;
        }
        $processing[$cache_key] = true;
        
        // Use the product's regular price as the base
        $original_price = $product->get_regular_price();
        if (empty($original_price) || !is_numeric($original_price)) {
            unset($processing[$cache_key]);
            return $price;
        }
        
        // Get customer's group with caching
        static $user_groups = array();
        if (!isset($user_groups[$customer_id])) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'user_groups';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                unset($processing[$cache_key]);
                return $price;
            }
            
            $user_groups[$customer_id] = $wpdb->get_var($wpdb->prepare(
                "SELECT group_id FROM {$wpdb->prefix}user_groups WHERE user_id = %d",
                $customer_id
            ));
        }
        
        $group_id = $user_groups[$customer_id];
        
        if (!$group_id) {
            unset($processing[$cache_key]);
            return $price;
        }
        
        $adjusted_price = $original_price;
        
        // Check for product-specific rules first
        global $wpdb;
        $product_rule = $wpdb->get_row($wpdb->prepare(
            "SELECT pr.*, g.group_name 
            FROM {$wpdb->prefix}pricing_rules pr
            JOIN {$wpdb->prefix}rule_products rp ON pr.rule_id = rp.rule_id
            JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
            WHERE pr.group_id = %d AND rp.product_id = %d
            ORDER BY pr.created_at DESC
            LIMIT 1",
            $group_id,
            $product_id
        ));
        
        if ($product_rule) {
            if ($product_rule->discount_type === 'percentage') {
                $adjusted_price = $original_price - (($product_rule->discount_value / 100) * $original_price);
            } else {
                $adjusted_price = $original_price - $product_rule->discount_value;
            }
        } else {
            // Check for category rules if no product rule exists
            $category_ids = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            if (!empty($category_ids) && !is_wp_error($category_ids)) {
                $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
                $query = $wpdb->prepare(
                    "SELECT pr.*, g.group_name 
                    FROM {$wpdb->prefix}pricing_rules pr
                    JOIN {$wpdb->prefix}rule_categories rc ON pr.rule_id = rc.rule_id
                    JOIN {$wpdb->prefix}customer_groups g ON pr.group_id = g.group_id
                    WHERE pr.group_id = %d AND rc.category_id IN ($placeholders)
                    ORDER BY pr.created_at DESC
                    LIMIT 1",
                    array_merge(array($group_id), $category_ids)
                );
                $category_rule = $wpdb->get_row($query);
                
                if ($category_rule) {
                    if ($category_rule->discount_type === 'percentage') {
                        $adjusted_price = $original_price - (($category_rule->discount_value / 100) * $original_price);
                    } else {
                        $adjusted_price = $original_price - $category_rule->discount_value;
                    }
                }
            }
        }
        
        // Ensure price doesn't go below zero
        $adjusted_price = max(0, $adjusted_price);
        
        // Clean up processing flag
        unset($processing[$cache_key]);
        
        // Return adjusted price if different from original
        if ($adjusted_price < $original_price) {
            return $adjusted_price;
        }
        
        return $price;
        
    } catch (Exception $e) {
        // Log error but don't break the site
        error_log('AWCOM: Error in apply_customer_group_pricing: ' . $e->getMessage());
        return $price;
    }
}
}

