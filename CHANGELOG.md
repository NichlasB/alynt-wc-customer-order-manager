# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.1.0] - 2026-04-03

### Added
- Customer edit-screen improvements, including shipping-address management, customer notes, and billing email tools
- Pending-order editing enhancements with live product search, shipping updates, and stock-aware workflows
- Customer payment switching for staff-assisted payment flows that need the customer account context
- Build tooling, release automation, and settings/hooks documentation

### Changed
- Refactored admin pages, order handling, order interface, and pricing lookup into smaller components
- Improved payment-link copy UX, product search behavior, and deploy packaging defaults
- Clarified gateway guidance for payment links that require customer-context completion

### Fixed
- Shipping-address fallback logic during order creation
- Pricing protection and admin order-origin edge cases
- Release workflow asset-upload handling

### Security
- Hardened capability checks, nonce validation, uninstall cleanup, and scoped switched-payment access

## [1.0.6] - 2026-03-11

### Added
- PHPDoc blocks for all classes, traits, and public methods
- File-level `@package`, `@subpackage`, and `@since` headers across all PHP files
- `CHANGELOG.md`, `docs/SETTINGS.md`, and `docs/HOOKS.md` documentation files

### Changed
- Plugin header now includes `License`, `License URI`, `Text Domain`, and `Domain Path` fields
- Documentation link in plugin row meta now points to the GitHub repository instead of `#`
- Documentation now clarifies that some gateways, such as Square, may require order payment links to be completed in the customer or logged-out context instead of an active admin session

## [1.0.5] - 2025-02-10

### Added
- Customer order editing functionality for pending orders
- Customers can modify orders before payment (add/remove products, change quantities)
- Real-time shipping calculations and order total updates
- Mobile-responsive order editing interface with theme integration
- Stock quantity validation and visual indicators
- Secure order access with order key validation
- Customer-specific pricing maintained during order modifications

## [1.0.4] - 2025-01-30

### Added
- Secure access to WooCommerce order payment links for authorized roles
- Streamlined payment-link sharing workflow for customer service

## [1.0.3] - 2025-01-27

### Fixed
- Potential Composer autoloader conflicts with other plugins
- Improved plugin compatibility in multi-plugin environments

## [1.0.2] - 2025-01-24

### Added
- "Copy Payment Link" button to order details for quick payment link sharing

### Changed
- Improved order management interface
- Updated documentation with new features

## [1.0.1] - 2025-01-24

### Added
- Documentation in README.md and README.txt

## [1.0.0] - 2025-01-20

### Added
- Initial release
- Customer management interface (list, add, edit customers)
- Admin-side order creation with customer group pricing
- Order handling capabilities
- Security features implementation (nonce verification, capability checks)
