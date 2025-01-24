<?php
namespace AlyntWCOrderManager;

class AdminPages {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'handle_bulk_actions'));
        add_action('admin_init', array($this, 'handle_customer_form_submission'));
        add_action('admin_init', array($this, 'handle_customer_edit_submission'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_customer_notes_scripts'));
        add_action('wp_ajax_awcom_add_customer_note', array($this, 'handle_add_customer_note'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
        add_action('wp_ajax_save_login_email_template', array($this, 'save_login_email_template'));
        add_action('wp_ajax_awcom_edit_customer_note', array($this, 'handle_edit_customer_note'));
        add_action('wp_ajax_awcom_delete_customer_note', array($this, 'handle_delete_customer_note'));
    }

    public function set_html_content_type() {
        return 'text/html';
    }

    public function add_menu_pages() {
        if (!Security::user_can_access()) {
            return;
        }

        add_menu_page(
            __('Customer Manager', 'alynt-wc-customer-order-manager'),
            __('Customer Manager', 'alynt-wc-customer-order-manager'),
            'manage_woocommerce',
            'alynt-wc-customer-order-manager',
            array($this, 'render_main_page'),
            'dashicons-groups',
            56
        );

        add_submenu_page(
            'alynt-wc-customer-order-manager',
            __('Add New Customer', 'alynt-wc-customer-order-manager'),
            __('Add New Customer', 'alynt-wc-customer-order-manager'),
            'manage_woocommerce',
            'alynt-wc-customer-order-manager-add',
            array($this, 'render_add_page')
        );

        // Hidden page for editing customers
        add_submenu_page(
            null,
            __('Edit Customer', 'alynt-wc-customer-order-manager'),
            __('Edit Customer', 'alynt-wc-customer-order-manager'),
            'manage_woocommerce',
            'alynt-wc-customer-order-manager-edit',
            array($this, 'render_edit_page')
        );
    }

    public function render_main_page() {
        if (!Security::user_can_access()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once AWCOM_PLUGIN_PATH . 'includes/class-customer-list-table.php';
        $customer_table = new CustomerListTable();
        $customer_table->prepare_items();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Customer Manager', 'alynt-wc-customer-order-manager'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=alynt-wc-customer-order-manager-add'); ?>" class="page-title-action">
                <?php _e('Add New Customer', 'alynt-wc-customer-order-manager'); ?>
            </a>

            <?php
            if (isset($_GET['deleted'])) {
                $count = intval($_GET['deleted']);
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                sprintf(_n('%s customer deleted.', '%s customers deleted.', $count, 'alynt-wc-customer-order-manager'), $count) . 
                '</p></div>';
            }
            ?>

            <form method="post">
                <?php
                $customer_table->search_box(__('Search Customers', 'alynt-wc-customer-order-manager'), 'customer-search');
                wp_nonce_field('bulk-customers', 'alynt-wc-customer-order-manager-nonce');
                $customer_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_edit_page() {
        if (!Security::user_can_access()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$customer_id) {
            wp_die(__('Invalid customer ID.', 'alynt-wc-customer-order-manager'));
        }

        $customer = get_user_by('id', $customer_id);
        if (!$customer || !in_array('customer', $customer->roles)) {
            wp_die(__('Invalid customer.', 'alynt-wc-customer-order-manager'));
        }

        ?>
        <div class="wrap">

            <h1><?php printf(__('Edit Customer - %s %s', 'alynt-wc-customer-order-manager'), 
                esc_html($customer->first_name), 
                esc_html($customer->last_name)); ?></h1>

            <?php
            // Show success/error messages
            if (isset($_GET['updated']) && $_GET['updated'] == '1') {
                // Get the current group name for display
                global $wpdb;
                $groups_table = $wpdb->prefix . 'customer_groups';
                $user_groups_table = $wpdb->prefix . 'user_groups';

                $current_group = $wpdb->get_var($wpdb->prepare(
                    "SELECT g.group_name 
                    FROM $groups_table g 
                    JOIN $user_groups_table ug ON g.group_id = ug.group_id 
                    WHERE ug.user_id = %d",
                    $customer_id
                ));

                echo '<div class="notice notice-success is-dismissible"><p>' . 
                __('Customer updated successfully.', 'alynt-wc-customer-order-manager') . 
                ($current_group ? ' Current group: ' . esc_html($current_group) : '') . 
                '</p></div>';
            }

            if (isset($_GET['error'])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                esc_html(urldecode($_GET['error'])) . '</p></div>';
            }
            if (isset($_GET['email_sent']) && $_GET['email_sent'] == '1') {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                __('Login details email sent successfully.', 'alynt-wc-customer-order-manager') . '</p></div>';
            }
            ?>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <!-- Left Column -->
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php _e('Customer Information', 'alynt-wc-customer-order-manager'); ?></h2>
                                <div class="inside">
                                    <form method="post" class="awcom-customer-form">
                                        <?php wp_nonce_field('edit_customer', 'awcom_customer_nonce'); ?>
                                        <input type="hidden" name="action" value="edit_customer">
                                        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
                                        <table class="form-table">
                                            <tr>
                                                <th scope="row">
                                                    <label for="customer_group"><?php _e('Customer Group (if any)', 'alynt-wc-customer-order-manager'); ?></label>
                                                </th>
                                                <td>
                                                    <?php
                                                    global $wpdb;
                                                    $groups_table = $wpdb->prefix . 'customer_groups';
                                                    $user_groups_table = $wpdb->prefix . 'user_groups';

                                                // Get all available groups
                                                    $groups = $wpdb->get_results("SELECT * FROM $groups_table");

                                                // Get customer's current group
                                                    $current_group = $wpdb->get_var($wpdb->prepare(
                                                        "SELECT group_id FROM $user_groups_table WHERE user_id = %d",
                                                        $customer_id
                                                    ));
                                                    ?>
                                                    <select name="customer_group" id="customer_group" class="regular-text">
                                                        <option value=""><?php _e('Select a group...', 'alynt-wc-customer-order-manager'); ?></option>
                                                        <?php foreach ($groups as $group) : ?>
                                                            <option value="<?php echo esc_attr($group->group_id); ?>" 
                                                                <?php selected($current_group, $group->group_id); ?>>
                                                                <?php echo esc_html($group->group_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    <label for="first_name"><?php _e('First Name', 'alynt-wc-customer-order-manager'); ?> *</label>
                                                </th>
                                                <td>
                                                    <input type="text" name="first_name" id="first_name" class="regular-text" 
                                                    value="<?php echo esc_attr($customer->first_name); ?>" required>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th scope="row">
                                                    <label for="last_name"><?php _e('Last Name', 'alynt-wc-customer-order-manager'); ?> *</label>
                                                </th>
                                                <td>
                                                    <input type="text" name="last_name" id="last_name" class="regular-text" 
                                                    value="<?php echo esc_attr($customer->last_name); ?>" required>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th scope="row">
                                                    <label for="email"><?php _e('Email Address', 'alynt-wc-customer-order-manager'); ?> *</label>
                                                </th>
                                                <td>
                                                    <input type="email" name="email" id="email" class="regular-text" 
                                                    value="<?php echo esc_attr($customer->user_email); ?>" required>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th scope="row">
                                                    <label for="company"><?php _e('Company Name', 'alynt-wc-customer-order-manager'); ?></label>
                                                </th>
                                                <td>
                                                    <input type="text" name="billing_company" id="company" class="regular-text" 
                                                    value="<?php echo esc_attr(get_user_meta($customer_id, 'billing_company', true)); ?>">
                                                </td>
                                            </tr>

                                            <tr>
                                                <th scope="row">
                                                    <label for="phone"><?php _e('Phone', 'alynt-wc-customer-order-manager'); ?></label>
                                                </th>
                                                <td>
                                                    <input type="tel" name="billing_phone" id="phone" class="regular-text" 
                                                    value="<?php echo esc_attr(get_user_meta($customer_id, 'billing_phone', true)); ?>">
                                                </td>
                                            </tr>

                                            <tr>
                                                <th scope="row">
                                                    <label for="billing_address_1"><?php _e('Billing Address 1', 'alynt-wc-customer-order-manager'); ?></label>
                                                </th>
                                                <td>
                                                    <input type="text" name="billing_address_1" id="billing_address_1" class="regular-text" 
                                                    value="<?php echo esc_attr(get_user_meta($customer_id, 'billing_address_1', true)); ?>">
                                                </td>
                                            </tr>

                                            <tr>
                                                <th scope="row">
                                                    <label for="billing_address_2"><?php _e('Billing Address 2', 'alynt-wc-customer-order-manager'); ?></label>
                                                </th>
                                                <td>
                                                    <input type="text" name="billing_address_2" id="billing_address_2" class="regular-text" 
                                                    value="<?php echo esc_attr(get_user_meta($customer_id, 'billing_address_2', true)); ?>">
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    <label for="billing_city"><?php _e('City', 'alynt-wc-customer-order-manager'); ?></label>
                                                </th>
                                                <td>
                                                    <input type="text" name="billing_city" id="billing_city" class="regular-text" 
                                                    value="<?php echo esc_attr(get_user_meta($customer_id, 'billing_city', true)); ?>">
                                                </td>
                                            </tr>

                                            <tr>
                                                <th scope="row">
                                                    <label for="billing_state"><?php _e('State', 'alynt-wc-customer-order-manager'); ?></label>
                                                </th>
                                                <td>
                                                    <input type="text" name="billing_state" id="billing_state" class="regular-text" 
                                                    value="<?php echo esc_attr(get_user_meta($customer_id, 'billing_state', true)); ?>">
                                                </td>
                                            </tr>

                                            <tr>
                                                <th scope="row">
                                                    <label for="billing_postcode"><?php _e('Postal Code', 'alynt-wc-customer-order-manager'); ?></label>
                                                </th>
                                                <td>
                                                    <input type="text" name="billing_postcode" id="billing_postcode" class="regular-text" 
                                                    value="<?php echo esc_attr(get_user_meta($customer_id, 'billing_postcode', true)); ?>">
                                                </td>
                                            </tr>

                                            <tr>
                                                <th scope="row">
                                                    <label for="billing_country"><?php _e('Country', 'alynt-wc-customer-order-manager'); ?></label>
                                                </th>
                                                <td>
                                                    <select name="billing_country" id="billing_country" class="regular-text">
                                                        <?php
                                                        $countries_obj = new \WC_Countries();
                                                        $countries = $countries_obj->get_countries();
                                                        $current_country = get_user_meta($customer_id, 'billing_country', true);
                                                        foreach ($countries as $code => $name) {
                                                            echo '<option value="' . esc_attr($code) . '"' . 
                                                            selected($code, $current_country, false) . '>' . 
                                                            esc_html($name) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        </table>

                                        <div class="submit-button-container">
                                            <?php submit_button(__('Update Customer', 'alynt-wc-customer-order-manager')); ?>
                                            <a href="<?php echo admin_url('admin.php?page=alynt-wc-customer-order-manager'); ?>" class="button">
                                                <?php _e('Back to List', 'alynt-wc-customer-order-manager'); ?>
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Orders Section -->
                            <div class="orders-section postbox">
                                <h2 class="hndle"><?php _e('Orders', 'alynt-wc-customer-order-manager'); ?></h2>
                                <div class="inside">
                                    <p>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=alynt-wc-customer-order-manager-create-order&customer_id=' . $customer_id)); ?>" 
                                            class="button button-primary"><?php _e('Create New Order', 'alynt-wc-customer-order-manager'); ?></a>
                                        </p>
                                        <?php
                                    // Display recent orders
                                        $orders = wc_get_orders(array(
                                            'customer_id' => $customer_id,
                                            'limit' => 10,
                                            'orderby' => 'date',
                                            'order' => 'DESC',
                                        ));

                                        if ($orders) {
                                            echo '<h3>' . __('Recent Orders', 'alynt-wc-customer-order-manager') . '</h3>';
                                            echo '<table class="widefat">';
                                            echo '<thead><tr>';
                                            echo '<th>' . __('Order', 'alynt-wc-customer-order-manager') . '</th>';
                                            echo '<th>' . __('Date', 'alynt-wc-customer-order-manager') . '</th>';
                                            echo '<th>' . __('Status', 'alynt-wc-customer-order-manager') . '</th>';
                                            echo '<th>' . __('Total', 'alynt-wc-customer-order-manager') . '</th>';
                                            echo '</tr></thead>';
                                            echo '<tbody>';
                                            foreach ($orders as $order) {
                                                echo '<tr>';
                                                echo '<td><a href="' . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . '">#' . 
                                                $order->get_order_number() . '</a></td>';
                                                echo '<td>' . wc_format_datetime($order->get_date_created()) . '</td>';
                                                echo '<td>' . wc_get_order_status_name($order->get_status()) . '</td>';
                                                echo '<td>' . $order->get_formatted_order_total() . '</td>';
                                                echo '</tr>';
                                            }
                                            echo '</tbody></table>';
                                        } else {
                                            echo '<p>' . __('No orders found for this customer.', 'alynt-wc-customer-order-manager') . '</p>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div id="postbox-container-1" class="postbox-container">
                                <!-- Login Details Box -->
                                <div class="postbox">
                                    <h2 class="hndle"><span><?php _e('Login Details', 'alynt-wc-customer-order-manager'); ?></span></h2>
                                    <div class="inside">
                                        <form method="post">
                                            <?php wp_nonce_field('send_login_details', 'send_login_nonce'); ?>
                                            <input type="hidden" name="action" value="send_login_details">
                                            <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
                                            <?php submit_button(__('Send Login Details Email', 'alynt-wc-customer-order-manager'), 'secondary'); ?>
                                        </form>
                                        <button type="button" class="button" id="edit-email-template">
                                            <?php _e('Edit Email Template', 'alynt-wc-customer-order-manager'); ?>
                                        </button>
                                    </div>
                                </div>

                                <!-- Email Template Modal -->
                                <div id="email-template-modal" style="display:none;">
                                    <div class="email-template-editor">
                                        <h2><?php _e('Edit Login Email Template', 'alynt-wc-customer-order-manager'); ?></h2>
                                        <p class="description">
                                            <?php _e('Available merge tags:', 'alynt-wc-customer-order-manager'); ?>
                                            <br>
                                            <code>{customer_first_name}</code> - <?php _e('Customer\'s first name', 'alynt-wc-customer-order-manager'); ?>
                                            <br>
                                            <code>{password_reset_link}</code> - <?php _e('Password reset link', 'alynt-wc-customer-order-manager'); ?>
                                        </p>
                                        <?php 
                                        $template = get_option('awcom_login_email_template', ''); 
                                        if (empty($template)) {
                                            $template = sprintf(
                                                __("Hello {customer_first_name},\n\nYou can set your password and login to your account by visiting the following address:\n\n{password_reset_link}\n\nThis link will expire in 24 hours.\n\nRegards,\n%s", 'alynt-wc-customer-order-manager'),
                                                get_bloginfo('name')
                                            );
                                        }
                                        wp_editor($template, 'login_email_template', array(
                                            'textarea_rows' => 15
                                        )); 
                                        ?>
                                        <div class="submit-buttons">
                                            <button type="button" class="button button-primary save-template">
                                                <?php _e('Save Template', 'alynt-wc-customer-order-manager'); ?>
                                            </button>
                                            <button type="button" class="button cancel-edit">
                                                <?php _e('Cancel', 'alynt-wc-customer-order-manager'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Customer Notes Box -->
                                <div class="postbox">
                                    <h2 class="hndle"><span><?php _e('Customer Notes', 'alynt-wc-customer-order-manager'); ?></span></h2>
                                    <div class="inside">
                                        <div class="customer-notes-list">
                                            <?php
                                            $notes = get_user_meta($customer_id, '_customer_notes', true);
                                            if ($notes) {
                                            $notes = array_reverse($notes); // Show newest first
                                            foreach ($notes as $index => $note) {
                                                echo '<div class="customer-note" data-note-index="' . esc_attr($index) . '">';
                                                echo '<div class="note-content">' . wp_kses_post($note['content']) . '</div>';
                                                echo '<div class="note-actions">';
                                                echo '<button type="button" class="button button-small edit-note"><span class="dashicons dashicons-edit"></span> ' . esc_html__('Edit', 'alynt-wc-customer-order-manager') . '</button> ';
                                                echo '<button type="button" class="button button-small delete-note"><span class="dashicons dashicons-trash"></span> ' . esc_html__('Delete', 'alynt-wc-customer-order-manager') . '</button>';
                                                echo '</div>';
                                                echo '<div class="note-meta">';
                                                echo 'By ' . esc_html($note['author']) . ' on ' . 
                                                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $note['date']);
                                                echo '</div>';
                                                echo '</div>';
                                            }
                                        } else {
                                            echo '<p>' . __('No notes found.', 'alynt-wc-customer-order-manager') . '</p>';
                                        }
                                        ?>
                                    </div>
                                    <div class="add-note">
                                        <textarea name="customer_note" placeholder="<?php esc_attr_e('Add a note about this customer...', 'alynt-wc-customer-order-manager'); ?>"></textarea>
                                        <button type="button" class="button add-note-button" data-customer-id="<?php echo esc_attr($customer_id); ?>">
                                            <?php _e('Add Note', 'alynt-wc-customer-order-manager'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

    /**
     * Enqueues scripts needed for customer notes functionality
     */
    public function enqueue_customer_notes_scripts($hook) {
        if ($hook !== 'admin_page_alynt-wc-customer-order-manager-edit') {
            return;
        }

        // Enqueue the stylesheet
        wp_enqueue_style(
            'awcom-edit-customer',
            AWCOM_PLUGIN_URL . 'assets/css/edit-customer.css',
        array('dashicons'),
        AWCOM_VERSION
    );

        // Enqueue the script
        wp_enqueue_script(
            'awcom-customer-notes',
            AWCOM_PLUGIN_URL . 'assets/js/customer-notes.js',
            array('jquery'),
            AWCOM_VERSION,
            true
        );

        wp_localize_script('awcom-customer-notes', 'awcomCustomerNotes', array(
            'nonce' => wp_create_nonce('awcom_customer_notes'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'customer_id' => isset($_GET['id']) ? intval($_GET['id']) : 0,
            'confirm_delete' => __('Are you sure you want to delete this note?', 'alynt-wc-customer-order-manager')
        ));

    }

    public function enqueue_editor_scripts($hook) {
        if ($hook != 'admin_page_alynt-wc-customer-order-manager-edit') {
            return;
        }

        // Enqueue jQuery UI
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-dialog');

        // Enqueue WordPress editor
        wp_enqueue_editor();

        // Enqueue our custom script
        wp_enqueue_script(
            'awcom-email-template',
            AWCOM_PLUGIN_URL . 'assets/js/email-template.js',
            array('jquery', 'jquery-ui-dialog'),
            AWCOM_VERSION,
            true
        );

        wp_localize_script('awcom-email-template', 'awcomEmailVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('awcom_email_template'),
            'mergeTags' => array(
                '{customer_first_name}' => __('Customer First Name', 'alynt-wc-customer-order-manager'),
                '{password_reset_link}' => __('Password Reset Link', 'alynt-wc-customer-order-manager')
            )
        ));
    }

    public function save_login_email_template() {
        check_ajax_referer('awcom_email_template', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
        }

        if (!isset($_POST['template'])) {
            wp_send_json_error('No template content received');
        }

        $template = wp_kses_post($_POST['template']);
        $updated = update_option('awcom_login_email_template', $template);

        if ($updated) {
            wp_send_json_success('Template saved successfully');
        } else {
            wp_send_json_error('Failed to save template');
        }
    }

    /**
     * Handles AJAX requests for adding customer notes
     */
    public function handle_add_customer_note() {
        check_ajax_referer('awcom_customer_notes', 'nonce');

        if (!Security::user_can_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $note_content = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        if (!$customer_id || !$note_content) {
            wp_send_json_error(array('message' => 'Invalid data'));
        }
        $current_user = wp_get_current_user();
        $note = array(
            'content' => $note_content,
            'author' => $current_user->display_name,
            'date' => time(),
        );

        // Get existing notes or initialize empty array
        $notes = get_user_meta($customer_id, '_customer_notes', true);
        if (!is_array($notes)) {
            $notes = array();
        }

        // Add new note
        array_unshift($notes, $note); // Add to beginning of array
        update_user_meta($customer_id, '_customer_notes', $notes);

        // Format date for display
        $formatted_date = date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            $note['date']
        );

        wp_send_json_success(array(
            'content' => $note['content'],
            'author' => $note['author'],
            'date' => $formatted_date
        ));
    }

    public function handle_edit_customer_note() {
        check_ajax_referer('awcom_customer_notes', 'nonce');

        if (!Security::user_can_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $note_index = isset($_POST['note_index']) ? intval($_POST['note_index']) : -1;
        $note_content = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        if (!$customer_id || $note_index < 0 || !$note_content) {
            wp_send_json_error(array('message' => 'Invalid data'));
        }

        $notes = get_user_meta($customer_id, '_customer_notes', true);
        if (!is_array($notes)) {
            wp_send_json_error(array('message' => 'Notes not found'));
        }

        if (!isset($notes[$note_index])) {
            wp_send_json_error(array('message' => 'Note not found'));
        }

        $notes[$note_index]['content'] = $note_content;
        $notes[$note_index]['edited'] = true;
        $notes[$note_index]['edit_date'] = time();

        update_user_meta($customer_id, '_customer_notes', $notes);

        wp_send_json_success(array(
            'content' => $note_content
        ));
    }

    public function handle_delete_customer_note() {
        check_ajax_referer('awcom_customer_notes', 'nonce');

        if (!Security::user_can_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $note_index = isset($_POST['note_index']) ? intval($_POST['note_index']) : -1;

        if (!$customer_id || $note_index < 0) {
            wp_send_json_error(array('message' => 'Invalid data'));
        }

        $notes = get_user_meta($customer_id, '_customer_notes', true);
        if (!is_array($notes)) {
            wp_send_json_error(array('message' => 'Notes not found'));
        }

        if (!isset($notes[$note_index])) {
            wp_send_json_error(array('message' => 'Note not found'));
        }

        unset($notes[$note_index]);
    $notes = array_values($notes);

    update_user_meta($customer_id, '_customer_notes', $notes);

    wp_send_json_success();
}

public function handle_bulk_actions() {
    if (!isset($_POST['customers']) || !isset($_POST['alynt-wc-customer-order-manager-nonce'])) {
        return;
    }

        // Verify nonce
    Security::verify_nonce('alynt-wc-customer-order-manager-nonce', 'bulk-customers');

        // Check permissions
    if (!Security::user_can_access()) {
        return;
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action !== 'delete') {
        return;
    }

    $customer_ids = array_map('intval', $_POST['customers']);

    foreach ($customer_ids as $customer_id) {
        wp_delete_user($customer_id);
    }

        // Redirect back to the customer list with a success message
    wp_redirect(add_query_arg(
        array(
            'page' => 'alynt-wc-customer-order-manager',
            'deleted' => count($customer_ids)
        ),
        admin_url('admin.php')
    ));
    exit;
}

public function handle_customer_form_submission() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'create_customer') {
        return;
    }

    if (!isset($_POST['awcom_customer_nonce']) || 
        !wp_verify_nonce($_POST['awcom_customer_nonce'], 'create_customer')) {
        wp_die(__('Security check failed', 'alynt-wc-customer-order-manager'));
}

if (!Security::user_can_access()) {
    wp_die(__('You do not have sufficient permissions', 'alynt-wc-customer-order-manager'));
}

    // Validate required fields
if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
    wp_redirect(add_query_arg(
        array(
            'page' => 'alynt-wc-customer-order-manager-add',
            'error' => urlencode(__('Please fill in all required fields.', 'alynt-wc-customer-order-manager'))
        ),
        admin_url('admin.php')
    ));
    exit;
}

    // Check if email already exists
if (email_exists($_POST['email'])) {
    wp_redirect(add_query_arg(
        array(
            'page' => 'alynt-wc-customer-order-manager-add',
            'error' => urlencode(__('This email address is already registered.', 'alynt-wc-customer-order-manager'))
        ),
        admin_url('admin.php')
    ));
    exit;
}

    // Create the user
$user_data = array(
    'user_login'    => $_POST['email'],
    'user_email'    => $_POST['email'],
    'first_name'    => $_POST['first_name'],
    'last_name'     => $_POST['last_name'],
    'role'          => 'customer',
    'user_pass'     => wp_generate_password()
);

$user_id = wp_insert_user($user_data);

if (is_wp_error($user_id)) {
    wp_redirect(add_query_arg(
        array(
            'page' => 'alynt-wc-customer-order-manager-add',
            'error' => urlencode($user_id->get_error_message())
        ),
        admin_url('admin.php')
    ));
    exit;
}

    // Save billing information
$billing_fields = array(
    'billing_company',
    'billing_phone',
    'billing_address_1',
    'billing_address_2',
    'billing_city',
    'billing_state',
    'billing_postcode',
    'billing_country'
);

foreach ($billing_fields as $field) {
    if (isset($_POST[$field])) {
        update_user_meta($user_id, $field, sanitize_text_field($_POST[$field]));
    }
}

    // Handle customer group assignment
if (isset($_POST['customer_group'])) {
    global $wpdb;
    $user_groups_table = $wpdb->prefix . 'user_groups';
    $group_id = intval($_POST['customer_group']);

        // Only insert if a group was selected
    if ($group_id > 0) {
        $wpdb->insert(
            $user_groups_table,
            array(
                'user_id' => $user_id,
                'group_id' => $group_id
            ),
            array('%d', '%d')
        );
    }
}

    // Redirect to edit page for the new customer
wp_redirect(add_query_arg(
    array(
        'page' => 'alynt-wc-customer-order-manager-edit',
        'id' => $user_id,
        'created' => '1'
    ),
    admin_url('admin.php')
));
exit;
}

public function handle_customer_edit_submission() {
    if (!isset($_POST['action'])) {
        return;
    }

    if ($_POST['action'] === 'edit_customer') {
        if (!isset($_POST['awcom_customer_nonce']) || 
            !wp_verify_nonce($_POST['awcom_customer_nonce'], 'edit_customer')) {
            wp_die(__('Security check failed', 'alynt-wc-customer-order-manager'));
    }

    if (!Security::user_can_access()) {
        wp_die(__('You do not have sufficient permissions', 'alynt-wc-customer-order-manager'));
    }

    $customer_id = intval($_POST['customer_id']);

            // Validate required fields
    if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
        wp_redirect(add_query_arg(
            array(
                'page' => 'alynt-wc-customer-order-manager-edit',
                'id' => $customer_id,
                'error' => urlencode(__('Please fill in all required fields.', 'alynt-wc-customer-order-manager'))
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    // Check if email exists and belongs to another user
    $existing_user = get_user_by('email', $_POST['email']);
    if ($existing_user && $existing_user->ID != $customer_id) {
        wp_redirect(add_query_arg(
            array(
                'page' => 'alynt-wc-customer-order-manager-edit',
                'id' => $customer_id,
                'error' => urlencode(__('This email address is already registered to another user.', 'alynt-wc-customer-order-manager'))
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    // Update user data
    $user_data = array(
        'ID'         => $customer_id,
        'user_email' => $_POST['email'],
        'first_name' => $_POST['first_name'],
        'last_name'  => $_POST['last_name']
    );

    $user_id = wp_update_user($user_data);

    if (is_wp_error($user_id)) {
        wp_redirect(add_query_arg(
            array(
                'page' => 'alynt-wc-customer-order-manager-edit',
                'id' => $customer_id,
                'error' => urlencode($user_id->get_error_message())
            ),
            admin_url('admin.php')
        ));
        exit;
    }

            // Update billing information
    $billing_fields = array(
        'billing_company',
        'billing_phone',
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_country'
    );

    foreach ($billing_fields as $field) {
        if (isset($_POST[$field])) {
            update_user_meta($customer_id, $field, sanitize_text_field($_POST[$field]));
        }
    }

    // Handle customer group assignment
    if (isset($_POST['customer_group'])) {
        global $wpdb;
        $user_groups_table = $wpdb->prefix . 'user_groups';
        $group_id = intval($_POST['customer_group']);
        $customer_id = intval($_POST['customer_id']);

        // Debug output
        error_log('Attempting to update customer group:');
        error_log('Customer ID: ' . $customer_id);
        error_log('New Group ID: ' . $group_id);

        // Remove existing group assignment
        $delete_result = $wpdb->delete(
            $user_groups_table,
            array('user_id' => $customer_id),
            array('%d')
        );

        error_log('Delete result: ' . print_r($delete_result, true));
        if ($wpdb->last_error) {
            error_log('Delete error: ' . $wpdb->last_error);
        }

        // Add new group assignment if a group was selected
        if ($group_id > 0) {
            $insert_result = $wpdb->insert(
                $user_groups_table,
                array(
                    'user_id' => $customer_id,
                    'group_id' => $group_id
                ),
                array('%d', '%d')
            );

            error_log('Insert result: ' . print_r($insert_result, true));
            if ($wpdb->last_error) {
                error_log('Insert error: ' . $wpdb->last_error);
            }
        }
    }

    wp_redirect(add_query_arg(
        array(
            'page' => 'alynt-wc-customer-order-manager-edit',
            'id' => $customer_id,
            'updated' => '1'
        ),
        admin_url('admin.php')
    ));
    exit;

} elseif ($_POST['action'] === 'send_login_details') {
    if (!isset($_POST['send_login_nonce']) || 
        !wp_verify_nonce($_POST['send_login_nonce'], 'send_login_details')) {
        wp_die(__('Security check failed', 'alynt-wc-customer-order-manager'));
}

$customer_id = intval($_POST['customer_id']);
$user = get_user_by('id', $customer_id);

if (!$user) {
    wp_die(__('Invalid customer.', 'alynt-wc-customer-order-manager'));
}

$key = get_password_reset_key($user);
$reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');

$to = $user->user_email;
$subject = sprintf(__('[%s] Your login details', 'alynt-wc-customer-order-manager'), get_bloginfo('name'));

// Get custom template if it exists
$template = get_option('awcom_login_email_template', '');
if (!empty($template)) {
    $message = str_replace(
        array('{customer_first_name}', '{password_reset_link}'),
        array($user->first_name, '<a href="' . esc_url($reset_link) . '">' . esc_url($reset_link) . '</a>'),
        $template
    );
} else {
    // Use default template as fallback
    $message = sprintf(
        __('Hello %s,<br><br>
            You can set your password and login to your account by visiting the following address:<br><br>
            <a href="%s">%s</a><br><br>
            This link will expire in 24 hours.<br><br>
            Regards,<br>
            %s', 'alynt-wc-customer-order-manager'),
        $user->display_name,
        esc_url($reset_link),
        esc_url($reset_link),
        get_bloginfo('name')
    );
}

// Make sure the message is wrapped in proper HTML
$message = '<!DOCTYPE html><html><body>' . wpautop($message) . '</body></html>';

// Set HTML content type
add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));

// Send the email
$mail_sent = wp_mail($to, $subject, $message);

// Reset content type
remove_filter('wp_mail_content_type', array($this, 'set_html_content_type'));

wp_redirect(add_query_arg(
    array(
        'page' => 'alynt-wc-customer-order-manager-edit',
        'id' => $customer_id,
        'email_sent' => '1'
    ),
    admin_url('admin.php')
));
exit;
}
}

public function render_add_page() {
    if (!Security::user_can_access()) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Add New Customer', 'alynt-wc-customer-order-manager'); ?></h1>

        <?php
            // Show success/error messages
        if (isset($_GET['created']) && $_GET['created'] == '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
            __('Customer created successfully.', 'alynt-wc-customer-order-manager') . '</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . 
            esc_html(urldecode($_GET['error'])) . '</p></div>';
        }
        ?>

        <form method="post" class="awcom-customer-form">
            <?php wp_nonce_field('create_customer', 'awcom_customer_nonce'); ?>
            <input type="hidden" name="action" value="create_customer">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="customer_group"><?php _e('Customer Group', 'alynt-wc-customer-order-manager'); ?></label>
                    </th>
                    <td>
                        <?php
                        global $wpdb;
                        $groups_table = $wpdb->prefix . 'customer_groups';

                        // Get all available groups
                        $groups = $wpdb->get_results("SELECT * FROM $groups_table");
                        ?>
                        <select name="customer_group" id="customer_group" class="regular-text">
                            <option value=""><?php _e('- None (unassigned) -', 'alynt-wc-customer-order-manager'); ?></option>
                            <?php foreach ($groups as $group) : ?>
                                <option value="<?php echo esc_attr($group->group_id); ?>">
                                    <?php echo esc_html($group->group_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="first_name"><?php _e('First Name', 'alynt-wc-customer-order-manager'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="first_name" id="first_name" class="regular-text" required>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="last_name"><?php _e('Last Name', 'alynt-wc-customer-order-manager'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="last_name" id="last_name" class="regular-text" required>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="email"><?php _e('Email Address', 'alynt-wc-customer-order-manager'); ?> *</label>
                    </th>
                    <td>
                        <input type="email" name="email" id="email" class="regular-text" required>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="company"><?php _e('Company Name', 'alynt-wc-customer-order-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="billing_company" id="company" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="phone"><?php _e('Phone', 'alynt-wc-customer-order-manager'); ?></label>
                    </th>
                    <td>
                        <input type="tel" name="billing_phone" id="phone" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="billing_address_1"><?php _e('Billing Address 1', 'alynt-wc-customer-order-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="billing_address_1" id="billing_address_1" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="billing_address_2"><?php _e('Billing Address 2', 'alynt-wc-customer-order-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="billing_address_2" id="billing_address_2" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="billing_city"><?php _e('City', 'alynt-wc-customer-order-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="billing_city" id="billing_city" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="billing_state"><?php _e('State', 'alynt-wc-customer-order-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="billing_state" id="billing_state" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="billing_postcode"><?php _e('Postal Code', 'alynt-wc-customer-order-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="billing_postcode" id="billing_postcode" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="billing_country"><?php _e('Country', 'alynt-wc-customer-order-manager'); ?></label>
                    </th>
                    <td>
                        <select name="billing_country" id="billing_country" class="regular-text">
                            <?php
                            $countries_obj = new \WC_Countries();
                            $countries = $countries_obj->get_countries();
                            foreach ($countries as $code => $name) {
                                echo '<option value="' . esc_attr($code) . '"' . 
                                selected($code, 'US', false) . '>' . 
                                esc_html($name) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Create Customer', 'alynt-wc-customer-order-manager')); ?>
        </form>
    </div>
    <?php
}
} // End of class