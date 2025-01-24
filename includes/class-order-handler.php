<?php
namespace AlyntWCOrderManager;

use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Shipping_Zones;
use Exception;

class OrderHandler {
    const DEBUG = true;
    public function __construct() {
        add_action('admin_init', array($this, 'handle_order_creation'));
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
        // Get original and adjusted prices
        $original_price = $product->get_regular_price();

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
            'subtotal' => $original_price * $quantity,  // Set original price as subtotal
            'total'    => $adjusted_price * $quantity,  // Set adjusted price as total
        ));

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
        if (!$this->add_shipping_to_order($order, $_POST['shipping_method'])) {
            $this->redirect_with_error($customer_id, __('Error adding shipping method to order.', 'alynt-wc-customer-order-manager'));
            return;
        }

        // Add order notes if provided
        if (!empty($_POST['order_notes'])) {
            $order->add_order_note(sanitize_textarea_field($_POST['order_notes']), 0, true);
        }

        // Calculate totals
        $order->calculate_totals(true); // Force recalculation

        // In handle_order_creation():
        // Before saving the order
        if ($order->get_shipping_total() <= 0) {
            $this->log('Warning: Zero or negative shipping total detected');
            
            // Optionally retry shipping calculation
            $order->calculate_shipping();
            $order->calculate_totals(true);
        }

        if (!$order->get_items('shipping')) {
            $this->log('Warning: No shipping items found in order');
        }

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

        // Parse the shipping method ID
        $parts = explode(':', $shipping_method);
        $method_id = $parts[0] ?? '';
        $rate_id = end($parts); // Get the last part of the ID

        foreach ($available_methods as $method) {
            // Check both the method_id and the full shipping method string
            if ($method['method_id'] === $method_id || $shipping_method === $method['id']) {
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

        $this->log('Shipping method not found: ' . $shipping_method);
        return false;
    } catch (Exception $e) {
        $this->log('Shipping error: ' . $e->getMessage());
        return false;
    }
}

private function get_available_shipping_methods($order) {
    $methods = array();

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

        // Calculate shipping for package
        $shipping_zone = WC_Shipping_Zones::get_zone_matching_package($package);
        $shipping_methods = $shipping_zone->get_shipping_methods(true);

        foreach ($shipping_methods as $method) {
            if ($method->is_enabled()) {
                $rates = $method->get_rates_for_package($package);
                if ($rates) {
                    foreach ($rates as $rate_id => $rate) {
                        $methods[] = array(
                            'id' => $rate_id,
                            'method_id' => $method->id,
                            'instance_id' => $method->get_instance_id(),
                            'label' => $rate->get_label(),
                            'cost' => $rate->get_cost(),
                            'taxes' => $rate->get_taxes()
                        );
                    }
                }
            }
        }

        $this->log('Available shipping methods calculated: ' . print_r($methods, true));
    } catch (Exception $e) {
        $this->log('Error getting shipping methods: ' . $e->getMessage());
    }

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
}

