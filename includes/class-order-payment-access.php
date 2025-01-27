<?php
namespace AlyntWCOrderManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class OrderPaymentAccess
 * Handles payment form access control for shop managers and administrators
 */
class OrderPaymentAccess {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'add_payment_capabilities'));
        add_filter('woocommerce_order_email_verification_required', '__return_false', 9999);
    }

    /**
     * Add payment capabilities to admin and shop manager roles
     */
    public function add_payment_capabilities() {
        // Add capability to administrator role
        $administrator = get_role('administrator');
        if ($administrator) {
            $administrator->add_cap('pay_for_order');
        }

        // Add capability to shop manager role
        $shop_manager = get_role('shop_manager');
        if ($shop_manager) {
            $shop_manager->add_cap('pay_for_order');
        }
    }
}