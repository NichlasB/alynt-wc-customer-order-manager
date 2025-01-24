<?php
namespace AlyntWCOrderManager;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentLink {
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_order_actions_end', array($this, 'add_payment_link_copy_button'));
    }

    public function enqueue_scripts($hook) {
        if ($hook != 'post.php') {
            return;
        }

        // Enqueue dashicons
        wp_enqueue_style('dashicons');
        wp_enqueue_style('awcom-payment-link', plugins_url('../assets/css/payment-link.css', __FILE__));

        // Enqueue jQuery
        wp_enqueue_script('jquery');

        // Add our custom script
        wp_enqueue_script(
            'awcom-payment-link-js', 
            plugins_url('../assets/js/payment-link.js', __FILE__),
            array('jquery', 'woocommerce_admin'),
            '1.0',
            true
        );
    }

    public function add_payment_link_copy_button($order_id) {
        // Get order object from ID
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Only show for unpaid orders
        if ($order->is_paid()) {
            return;
        }

        $payment_link = $order->get_checkout_payment_url();
        ?>
        <div class="payment-link-actions">
            <h3 class="wc-order-data-row-toggle">
                <?php esc_html_e('Payment Link', 'alynt-wc-customer-order-manager'); ?>
            </h3>
            <div class="wc-order-data-row">
                <button type="button" class="button button-primary awcom-copy-payment-link" data-payment-link="<?php echo esc_attr($payment_link); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e('Copy Payment Link', 'alynt-wc-customer-order-manager'); ?>
                </button>
            </div>
        </div>
        <?php
    }
}