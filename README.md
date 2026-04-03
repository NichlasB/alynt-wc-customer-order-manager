# Alynt WooCommerce Customer and Order Manager

A powerful WordPress plugin that enhances WooCommerce's customer and order management capabilities. This plugin provides a comprehensive interface for efficiently managing customer relationships and order processing.

## 🚀 Features

- **Advanced Customer Management**
  - Centralized customer information dashboard
  - Detailed customer profiles
  - Customer activity tracking

- **Enhanced Order Interface**
  - Streamlined order processing
  - Bulk order management
  - Custom order status handling
  - Quick payment link copying and sharing

- **Customer Order Editing** ⭐ NEW
  - Customers can edit pending orders before payment
  - Add/remove products with real-time search
  - Change quantities with stock validation
  - Update shipping methods with automatic recalculation
  - Mobile-responsive interface with theme integration
  - Secure access with order key validation

- **Security**
  - Secure data handling
  - Role-based access control
  - Data encryption support
  - Order key validation for customer access

## 📋 Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher

## 🔧 Installation

1. Clone this repository or download the ZIP file
2. Upload the plugin files to the `/wp-content/plugins/alynt-wc-customer-order-manager` directory
3. Activate the plugin through the WordPress admin panel
4. Configure the plugin settings under WooCommerce menu

## 💻 Usage

After installation and activation:

1. Navigate to WooCommerce > Alynt Customer Order Manager in your WordPress admin panel
2. Use the intuitive interface to manage customers and orders
3. Customize the settings according to your needs

### Payment Link Sharing
- Open any order in the WooCommerce order edit screen
- Look for the "Copy Payment Link" button in the order actions sidebar
- Click the button to copy the payment URL to your clipboard
- Share the link with your customer via email, message, or any preferred method
- For Square-powered stores, complete the payment link in a logged-out window or while switched into the customer account. Admin wp-admin sessions can fail before payment.

### Customer Order Editing
When customers receive payment links for pending orders, they can:
- **Edit Order Contents**: Add or remove products using the search interface
- **Adjust Quantities**: Change item quantities with real-time stock validation
- **Update Shipping**: Select different shipping methods with automatic cost calculation
- **Add Notes**: Include special instructions or requests
- **Review Changes**: See updated totals before proceeding to payment

**Access Requirements:**
- Order must be in "Pending" status
- Valid order key required (automatically included in payment links)
- Feature can be enabled/disabled by administrators

**Security Features:**
- Only pending orders can be edited
- Order key validation prevents unauthorized access
- Stock validation prevents overselling
- Customer-specific pricing maintained

## 🔒 Security

The plugin implements various security measures:
- Data sanitization and validation
- WordPress nonce verification
- Capability checks for user actions

## ❓ FAQ

**Does this plugin work without WooCommerce?**
No. WooCommerce must be installed and activated. The plugin will display an admin notice and will not load if WooCommerce is missing.

**What user roles can access the plugin?**
Administrators and Shop Managers. All plugin screens and AJAX endpoints enforce this check via the `Security::user_can_access()` helper.

**Will this plugin conflict with other plugins that use Composer?**
The plugin checks for an existing Composer autoloader before loading its own to prevent conflicts in environments with multiple Composer-dependent plugins.

**Where is customer order editing data stored?**
Order-level pricing is stored in WooCommerce order meta (`_has_custom_pricing`, `_locked_total`, etc.). Customer notes are stored in user meta (`_customer_notes`). See [docs/SETTINGS.md](docs/SETTINGS.md) for the full reference.

---

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history.

### 1.1.0 (2026-04-03)
- Added customer edit-screen improvements, including shipping address management, notes, and billing email tools
- Added pending-order editing enhancements with live product and shipping updates
- Added a hardened customer payment-switch workflow for staff-assisted payment completion
- Improved payment link UX, shipping/pricing flow reliability, and deploy/build tooling

### 1.0.6 (2026-03-11)
- Added PHPDoc blocks for all classes, traits, and public methods
- Added CHANGELOG.md, docs/SETTINGS.md, and docs/HOOKS.md
- Fixed plugin header (added License, License URI, Text Domain, Domain Path fields)
- Fixed documentation link in plugin row meta (was pointing to `#`)

### 1.0.5 (2025-02-10)
- Added customer order editing functionality for pending orders
- Customers can now modify orders before payment (add/remove products, change quantities)
- Real-time shipping calculations and order total updates
- Mobile-responsive order editing interface with theme integration
- Stock quantity validation and visual indicators
- Secure order access with order key validation
- Customer-specific pricing maintained during order modifications

### 1.0.4 (2025-01-30)
- Added secure access to WooCommerce order payment links for authorized roles
- Added streamlined payment-link sharing workflow for customer service
- Note: Some gateways, such as Square, may require the payment link to be completed in the customer or logged-out context rather than an active admin session

### 1.0.3 (2025-01-27)
- Fixed potential Composer autoloader conflicts with other plugins
- Improved plugin compatibility in multi-plugin environments

### 1.0.2 (2025-01-24)
- Added "Copy Payment Link" button to order details for quick payment link sharing
- Improved order management interface
- Updated documentation with new features

### 1.0.1 (2025-01-24)
- Added documentation in README.md and README.txt

### 1.0.0 (2025-01-20)
- Initial release
- Customer management interface
- Order handling capabilities
- Security features implementation

## 📝 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 📫 Support

For support queries:
- Create an issue in this repository
