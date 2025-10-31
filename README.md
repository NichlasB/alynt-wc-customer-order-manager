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

## 📝 Changelog

### 1.0.5
- Added customer order editing functionality for pending orders
- Customers can now modify orders before payment (add/remove products, change quantities)
- Real-time shipping calculations and order total updates
- Mobile-responsive order editing interface with theme integration
- Stock quantity validation and visual indicators
- Secure order access with order key validation
- Customer-specific pricing maintained during order modifications

### 1.0.4
- Added ability for administrators and shop managers to process payments on behalf of customers
- Added secure access to order payment forms for authorized roles
- Added streamlined payment processing workflow for customer service

### 1.0.3 (2025-01-27)
- Fixed potential Composer autoloader conflicts with other plugins
- Improved plugin compatibility in multi-plugin environments


### 1.0.2 (2025-01-24)
- Added "Copy Payment Link" button to order details for quick payment link sharing
- Improved order management interface
- Updated documentation with new features

### 1.0.1 (2025-01-24)
- Added GitHub Update Checker integration for automatic plugin updates
- Added documentation in README.md and README.txt
- Added plugin update functionality through WordPress admin panel
- Improved plugin distribution with vendor files included

### 1.0.0
- Initial release
- Customer management interface
- Order handling capabilities
- Security features implementation

## 📝 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 📫 Support

For support queries:
- Create an issue in this repository