=== Alynt WooCommerce Customer and Order Manager ===
Contributors: Alynt
Tags: woocommerce, customers, orders, management, admin
Requires at least: 5.0
Tested up to: 6.7.1
Requires PHP: 7.2
WC requires at least: 4.0
Stable tag: 1.0.6
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A comprehensive customer and order management interface for WooCommerce stores.

== Description ==

Customer and Order Manager for WooCommerce enhances your WooCommerce store's administrative capabilities by providing a dedicated interface for managing customers and orders efficiently.

= Key Features =
* Advanced customer management interface
* Enhanced order handling capabilities
* Customer order editing for pending orders (NEW)
* Real-time shipping calculations and order updates
* Mobile-responsive customer interface with theme integration
* Stock quantity validation and visual indicators
* Secure customer data management
* Customizable order interface
* Streamlined administrative workflow
* Easy payment link sharing

= Requirements =
* WordPress 5.0 or higher
* WooCommerce 4.0 or higher
* PHP 7.2 or higher

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/alynt-wc-customer-order-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure WooCommerce is installed and activated
4. Access the customer management features through the WooCommerce menu

== Frequently Asked Questions ==

= Is WooCommerce required for this plugin? =

Yes, WooCommerce must be installed and activated for this plugin to work.

= Is this plugin compatible with my theme? =

The plugin works with any WordPress theme as it operates primarily in the admin area.

= How do I use the payment link copy feature? =

When viewing an order in the admin area, you'll find a "Copy Payment Link" button in the order actions sidebar. Click this button to copy the payment URL to your clipboard, which you can then share with your customer.

= Can customers edit their orders? =

Yes! Customers can edit pending orders before payment. When they click the payment link, they'll be taken to an order editing interface where they can add/remove products, change quantities, update shipping methods, and add order notes. Only pending orders can be edited for security reasons.

= What can customers modify in their orders? =

Customers can modify pending orders by adding or removing products, changing item quantities (with stock validation), selecting different shipping methods, and adding special instructions. All changes are calculated in real-time with updated totals and shipping costs.

== Changelog ==

= 1.0.6 =
* Fix: Resolved critical bug where shipping addresses were reverting to billing addresses during order creation
* Fix: Modified address copy logic to only copy billing to shipping when ALL key shipping fields (address_1, city, country) are empty
* Fix: Updated shipping method calculation to use shipping address when available, falling back to billing address only when necessary
* Added: Stock quantity display in admin order creation screen with visual color-coded indicators
* Added: Product search dropdown now shows stock quantities (e.g., "Product Name (15 in stock)")
* Added: Visual stock status indicators - red (out of stock), orange (low stock ≤5), gray (normal stock)
* Added: Comprehensive shipping address management section in customer edit screen
* Added: "Same as billing address" checkbox toggle for quick shipping address entry
* Added: Complete shipping address fields (company, phone, address lines, city, state, postcode, country)
* Added: JavaScript functionality to copy billing values when checkbox is checked
* Enhancement: Improved product search to include SKUs and custom fields for better product discovery
* Enhancement: Enhanced data sanitization and security across all form submissions
* Enhancement: Improved nonce verification and capability checks
* Enhancement: Zero performance impact for stock display - uses existing WooCommerce product objects
* Enhancement: Responsive UI improvements with proper field toggling
* Technical: Maintained full backward compatibility with existing orders and configurations

= 1.0.5 =
* Added: Customer order editing functionality for pending orders
* Added: Real-time shipping calculations and order total updates during editing
* Added: Mobile-responsive order editing interface with theme integration
* Added: Stock quantity validation and visual indicators for customers
* Added: Secure order access with order key validation
* Enhancement: Customer-specific pricing maintained during order modifications
* Security: Only pending orders can be edited to prevent payment conflicts

= 1.0.4 =
* Added: Ability for administrators and shop managers to process payments on behalf of customers
* Added: Secure access to order payment forms for authorized roles
* Added: Streamlined payment processing workflow for customer service

= 1.0.3 - 2025-01-27 =
* Fix: Resolved potential Composer autoloader conflicts with other plugins
* Enhancement: Improved plugin compatibility with environments using multiple Composer-based plugins

= 1.0.2 - 2025-01-24 =
* Feature: Added "Copy Payment Link" button to order details
* Enhancement: Improved order management interface
* Documentation: Updated documentation with new features

= 1.0.1 - 2025-01-24 =
* Enhancement: Added GitHub Update Checker integration
* Enhancement: Added automatic plugin updates through WordPress admin panel
* Enhancement: Added comprehensive documentation
* Enhancement: Improved plugin distribution with required dependencies
* Documentation: Added detailed installation and configuration guides
* Documentation: Added troubleshooting section

= 1.0.0 =
* Initial release
* Customer management interface
* Order handling capabilities
* Security features implementation

== Upgrade Notice ==

= 1.0.5 =
Major new feature: Customers can now edit their pending orders before payment! This includes adding/removing products, changing quantities, and updating shipping methods with real-time calculations. Highly recommended for all users to improve customer experience.

= 1.0.4 =
Important update for stores that process customer payments manually. Administrators and shop managers can now access payment forms to process payments on behalf of customers.

= 1.0.3 =
Important stability update: Fixes potential conflicts with other plugins using Composer. Recommended for all users, especially those running multiple plugins.

= 1.0.2 =
New feature: Adds payment link copying functionality for easier order management. Recommended for all users.

= 1.0.1 =
Important update: Adds automatic update functionality through WordPress admin panel and includes improved documentation. Recommended for all users.

= 1.0.0 =
Initial release of the Customer and Order Manager for WooCommerce.