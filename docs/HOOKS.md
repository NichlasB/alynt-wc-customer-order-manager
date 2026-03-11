# Hook Reference

This file documents the WordPress and WooCommerce hooks that the Alynt WooCommerce Customer and Order Manager plugin integrates with.

> **Note:** This plugin does not currently expose any custom `do_action()` or `apply_filters()` hooks of its own. All hooks listed below are WordPress/WooCommerce hooks that the plugin hooks **into**. Custom developer hooks will be added in a future version.

---

## Actions — Plugin Hooks Into

| Hook | Priority | Callback | Description |
|------|----------|----------|-------------|
| `plugins_loaded` | 10 | `awcom_init` | Initialises the plugin after all other plugins are loaded. |
| `init` | 10 | `OrderPaymentAccess::add_payment_capabilities` | Grants `pay_for_order` capability to administrator and shop_manager roles. |
| `admin_init` | 10 | `OrderHandler::handle_order_creation` | Processes the Create Order form submission. |
| `admin_init` | 10 | `AdminPages::handle_bulk_actions` | Processes bulk customer deletions. |
| `admin_init` | 10 | `AdminPages::handle_customer_form_submission` | Processes the Add New Customer form. |
| `admin_init` | 10 | `AdminPages::handle_customer_edit_submission` | Processes the Edit Customer form and Send Login Details action. |
| `admin_menu` | 10 | `AdminPages::add_menu_pages` | Registers Customer Manager menu and submenu pages. |
| `admin_menu` | 10 | `OrderInterface::add_menu_pages` | Registers the hidden Create Order submenu page. |
| `admin_enqueue_scripts` | 10 | `AdminPages::enqueue_customer_notes_scripts` | Enqueues customer notes JS on the Edit Customer page. |
| `admin_enqueue_scripts` | 10 | `AdminPages::enqueue_editor_scripts` | Enqueues the email template editor on the Edit Customer page. |
| `admin_enqueue_scripts` | 10 | `OrderInterface::enqueue_scripts` | Enqueues order interface assets on the Create Order page. |
| `admin_enqueue_scripts` | 10 | `PaymentLink::enqueue_scripts` | Enqueues payment link CSS on WooCommerce order edit screens. |
| `wp_ajax_awcom_add_customer_note` | 10 | `AdminPages::handle_add_customer_note` | AJAX: adds a note to a customer record. |
| `wp_ajax_awcom_edit_customer_note` | 10 | `AdminPages::handle_edit_customer_note` | AJAX: edits an existing customer note. |
| `wp_ajax_awcom_delete_customer_note` | 10 | `AdminPages::handle_delete_customer_note` | AJAX: deletes a customer note. |
| `wp_ajax_save_login_email_template` | 10 | `AdminPages::save_login_email_template` | AJAX: saves the login-details email template. |
| `wp_ajax_awcom_search_products` | 10 | `OrderInterface::ajax_search_products` | AJAX: searches products with customer group pricing applied. |
| `wp_ajax_awcom_get_shipping_methods` | 10 | `OrderInterface::ajax_get_shipping_methods` | AJAX: retrieves available shipping methods for a customer's address. |
| `woocommerce_order_before_calculate_totals` | 10 | `OrderHandler::prevent_total_recalculation` | Cancels total recalculation for custom-priced orders on the frontend. |
| `woocommerce_order_after_calculate_totals` | 10 | `OrderHandler::restore_custom_prices_after_recalculation` | Re-applies custom item prices after a recalculation completes. |
| `woocommerce_before_pay_action` | 10 | `OrderHandler::fix_order_before_payment` | Re-applies customer group pricing before a customer submits payment. |
| `woocommerce_order_actions_end` | 10 | `PaymentLink::add_payment_link_copy_button` | Renders the Copy Payment Link button in the order actions sidebar. |

---

## Filters — Plugin Hooks Into

| Hook | Priority | Callback | Description |
|------|----------|----------|-------------|
| `plugin_action_links_{basename}` | 10 | `awcom_add_plugin_action_links` | Prepends a "Manage Customers" link to the plugin's action links. |
| `plugin_row_meta` | 10 | `awcom_plugin_row_meta` | Adds a "Documentation" link to the plugin's row meta. |
| `woocommerce_order_item_get_subtotal` | 10 | `OrderHandler::preserve_item_subtotal` | Passes item subtotal through unchanged for custom-priced orders. |
| `woocommerce_order_item_get_total` | 10 | `OrderHandler::preserve_item_total` | Passes item total through unchanged for custom-priced orders. |
| `woocommerce_order_get_total` | 9999 | `OrderHandler::lock_order_total` | Returns the locked order total on the frontend pay page. |
| `woocommerce_order_get_total` | 10 | `OrderHandler::filter_order_total_for_payment` | Stub filter reserved for future payment-gateway corrections. |
| `woocommerce_cart_item_name` | 10 | `OrderHandler::add_discount_info_to_cart_item` | Appends a group pricing label to the cart item name. |
| `woocommerce_cart_get_total` | 9999 | `OrderHandler::override_cart_total` | Returns a corrected cart total when custom pricing is active. |
| `woocommerce_order_email_verification_required` | 9999 | `__return_false` | Disables email verification on the WooCommerce pay page. |
| `wc_ppcp_cart_data` | 10 | `OrderHandler::fix_paypal_cart_data` | Corrects the total passed to the PayPal PPCP gateway. |
| `wp_mail_content_type` | — | `AdminPages::set_html_content_type` | Temporarily sets the mail content type to `text/html` for login-details emails. Added and removed inline — not permanently registered. |

---

## Planned Custom Hooks (Future)

The following hooks are planned for a future release to allow developers to extend the plugin:

| Hook (planned) | Type | Description |
|----------------|------|-------------|
| `awcom_before_order_create` | Action | Fires before an admin-created order is saved. |
| `awcom_after_order_create` | Action | Fires after an admin-created order is saved, passing the order object. |
| `awcom_customer_note_added` | Action | Fires after a customer note is added. |
| `awcom_order_item_price` | Filter | Filters the adjusted price applied to an order item during admin creation. |
