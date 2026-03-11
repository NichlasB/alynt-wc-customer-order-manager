# Settings Reference

This file documents all database options, order meta keys, and user meta keys used by the Alynt WooCommerce Customer and Order Manager plugin.

---

## Database Options (`wp_options`)

| Option Key                   | Type   | Default | Sanitization   | Description                                                                 |
|------------------------------|--------|---------|----------------|-----------------------------------------------------------------------------|
| `awcom_login_email_template` | string | `''`    | `wp_kses_post` | HTML template used when sending login credentials to customers. Supports the merge tags `{customer_first_name}` and `{password_reset_link}`. Managed via the email template editor on the Edit Customer screen. |

---

## Order Meta Keys

These keys are stored on `WC_Order` objects via `update_meta_data()` / `add_meta_data()`.

| Meta Key                 | Type   | Values         | Description                                                                                  |
|--------------------------|--------|----------------|----------------------------------------------------------------------------------------------|
| `_has_custom_pricing`    | string | `'yes'`        | Flags an order as having custom (group) pricing applied. Prevents WooCommerce from overwriting item prices during recalculation. |
| `_pricing_locked`        | string | `'yes'`        | Indicates that the order total should not be recalculated by WooCommerce filters.             |
| `_locked_total`          | float  | numeric string | The definitive total for the order. Returned by the `woocommerce_order_get_total` filter on the frontend pay page. |

---

## Order Item Meta Keys

These keys are stored on `WC_Order_Item_Product` objects.

| Meta Key                  | Type   | Description                                                                              |
|---------------------------|--------|------------------------------------------------------------------------------------------|
| `_custom_price`           | float  | The discounted unit price applied to this item after customer group pricing rules.        |
| `_custom_subtotal_price`  | float  | The original (pre-discount) unit price, used as the line subtotal for display purposes.  |
| `_discount_description`   | string | Human-readable label for the applied discount, e.g. "VIP Group Discount: 10%".           |
| `_customer_group`         | string | The name of the customer group whose pricing rule was applied to this item.               |

---

## User Meta Keys

These keys are stored on WordPress user objects and are read/written by the plugin's customer management screens.

| Meta Key              | Description                                                                              |
|-----------------------|------------------------------------------------------------------------------------------|
| `_customer_notes`     | Serialized array of admin notes for a customer. Each entry is an array with keys: `content` (string), `author` (string), `date` (int timestamp), and optionally `edited` (bool) and `edit_date` (int timestamp). |
| `billing_email`       | Customer billing email address.                                                          |
| `billing_company`     | Customer billing company name.                                                           |
| `billing_phone`       | Customer billing phone number.                                                           |
| `billing_address_1`   | Customer billing address line 1.                                                         |
| `billing_address_2`   | Customer billing address line 2.                                                         |
| `billing_city`        | Customer billing city.                                                                   |
| `billing_state`       | Customer billing state/province code.                                                    |
| `billing_postcode`    | Customer billing postal code.                                                            |
| `billing_country`     | Customer billing country code (ISO 3166-1 alpha-2).                                     |
| `shipping_phone`      | Customer shipping phone number.                                                          |
| `shipping_address_1`  | Customer shipping address line 1.                                                        |
| `shipping_address_2`  | Customer shipping address line 2.                                                        |
| `shipping_city`       | Customer shipping city.                                                                  |
| `shipping_state`      | Customer shipping state/province code.                                                   |
| `shipping_postcode`   | Customer shipping postal code.                                                           |
| `shipping_country`    | Customer shipping country code (ISO 3166-1 alpha-2).                                    |

---

## Third-Party Options (Read Only)

The plugin reads but does not write the following options from the companion Customer Groups plugin:

| Option Key                       | Description                                           |
|----------------------------------|-------------------------------------------------------|
| `wccg_default_group_id`          | ID of the default pricing group for unassigned users. |
| `wccg_default_group_custom_title`| Custom display title for the default group.           |
