<?php
namespace CustomerManager;

class Security {
    /**
     * Check if current user has access to plugin features
     *
     * @return bool
     */
    public static function user_can_access() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        $allowed_roles = array('administrator', 'shop_manager');
        
        return array_intersect($allowed_roles, $user->roles) ? true : false;
    }

    /**
     * Verify nonce
     *
     * @param string $nonce_name Name of the nonce
     * @param string $action Action name
     * @return void
     */
    public static function verify_nonce($nonce_name, $action) {
        if (!isset($_REQUEST[$nonce_name]) || 
            !wp_verify_nonce($_REQUEST[$nonce_name], $action)) {
            wp_die(__('Security check failed', 'customer-manager'));
        }
    }
}