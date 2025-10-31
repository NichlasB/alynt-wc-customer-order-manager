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
        // Check if we're on an order edit page
        $is_order_page = false;
        
        // Check for legacy order pages
        if ($hook === 'post.php') {
            global $post;
            if ($post && $post->post_type === 'shop_order') {
                $is_order_page = true;
            }
        }
        
        // Check for HPOS order pages
        if (strpos($hook, 'woocommerce') !== false || strpos($hook, 'wc-orders') !== false) {
            $is_order_page = true;
        }
        
        // Also check for order ID in URL
        if (isset($_GET['post']) && get_post_type($_GET['post']) === 'shop_order') {
            $is_order_page = true;
        }
        
        if (!$is_order_page) {
            return;
        }

        // Enqueue dashicons
        wp_enqueue_style('dashicons');
        wp_enqueue_style('awcom-payment-link', plugins_url('../assets/css/payment-link.css', __FILE__), array(), '1.0.1');

        // Enqueue jQuery
        wp_enqueue_script('jquery');

        // Add our custom script
        wp_enqueue_script(
            'awcom-payment-link-js', 
            plugins_url('../assets/js/payment-link.js', __FILE__),
            array('jquery'),
            '1.0.4',
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

        // Show only for pending orders
        if ($order->get_status() !== 'pending') {
            return;
        }

        // Always use standard WooCommerce payment link
        $link = $order->get_checkout_payment_url();
        $button_text = __('Copy Payment Link', 'alynt-wc-customer-order-manager');
        $section_title = __('Payment Link', 'alynt-wc-customer-order-manager');
        ?>
        <div class="payment-link-actions">
            <h3 class="wc-order-data-row-toggle">
                <?php echo esc_html($section_title); ?>
            </h3>
            <div class="wc-order-data-row">
                <button type="button" class="button button-primary awcom-copy-payment-link" data-payment-link="<?php echo esc_attr($link); ?>" onclick="awcomCopyLink(this)">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php echo esc_html($button_text); ?>
                </button>
                <script>
                function awcomCopyLink(button) {
                    var link = button.getAttribute('data-payment-link');
                    
                    if (!link) {
                        alert('No payment link found to copy.');
                        return;
                    }
                    
                    // Try modern clipboard API first
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(link).then(function() {
                            alert('Payment link copied to clipboard!');
                        }).catch(function(err) {
                            fallbackCopy(link);
                        });
                    } else {
                        fallbackCopy(link);
                    }
                    
                    function fallbackCopy(text) {
                        var textarea = document.createElement('textarea');
                        textarea.value = text;
                        document.body.appendChild(textarea);
                        textarea.select();
                        
                        try {
                            var successful = document.execCommand('copy');
                            if (successful) {
                                alert('Payment link copied to clipboard!');
                            } else {
                                alert('Failed to copy. Please copy manually: ' + text);
                            }
                        } catch (err) {
                            alert('Failed to copy. Please copy manually: ' + text);
                        } finally {
                            document.body.removeChild(textarea);
                        }
                    }
                }
                </script>
            </div>
        </div>
        <?php
    }
}