<?php
namespace CustomerManager;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class CustomerListTable extends \WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'customer',
            'plural'   => 'customers',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'customer_name' => __('Name', 'customer-manager'),
            'company'       => __('Company', 'customer-manager'),
            'email'        => __('Email', 'customer-manager'),
            'phone'        => __('Phone', 'customer-manager'),
            'address'      => __('Address', 'customer-manager'),
            'created'      => __('Created', 'customer-manager'),
            'orders'       => __('Orders', 'customer-manager'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'customer_name' => ['customer_name', true],
            'email'        => ['email', false],
            'created'      => ['created', false],
        ];
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="customers[]" value="%s" />', $item->ID
        );
    }

    protected function column_customer_name($item) {
        $name = $item->first_name . ' ' . $item->last_name;
        $edit_link = admin_url('admin.php?page=customer-manager-edit&id=' . $item->ID);
        
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit_link),
                __('Edit', 'customer-manager')
            ),
            'delete' => sprintf(
                '<a href="#" class="delete-customer" data-id="%s">%s</a>',
                $item->ID,
                __('Delete', 'customer-manager')
            ),
        ];

        return sprintf(
            '<a href="%1$s"><strong>%2$s</strong></a> %3$s',
            esc_url($edit_link),
            esc_html($name),
            $this->row_actions($actions)
        );
    }

    protected function column_orders($item) {
        $count = wc_get_customer_order_count($item->ID);
        $url = admin_url('edit.php?post_type=shop_order&_customer_user=' . $item->ID);
        
        return sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            sprintf(_n('%s order', '%s orders', $count, 'customer-manager'), $count)
        );
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'company':
                return get_user_meta($item->ID, 'billing_company', true);
            
            case 'email':
                return '<a href="mailto:' . esc_attr($item->user_email) . '">' . 
                    esc_html($item->user_email) . '</a>';
            
            case 'phone':
                $phone = get_user_meta($item->ID, 'billing_phone', true);
                return $phone ? '<a href="tel:' . esc_attr($phone) . '">' . 
                    esc_html($phone) . '</a>' : '';
            
            case 'address':
                $address_parts = array_filter([
                    get_user_meta($item->ID, 'billing_address_1', true),
                    get_user_meta($item->ID, 'billing_city', true),
                    get_user_meta($item->ID, 'billing_state', true),
                    get_user_meta($item->ID, 'billing_postcode', true)
                ]);
                return implode(', ', $address_parts);
            
            case 'created':
                return date_i18n(
                    get_option('date_format'), 
                    strtotime($item->user_registered)
                );
            
            default:
                return print_r($item, true);
        }
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // Column headers
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Build query
        $args = [
            'role'    => 'customer',
            'number'  => $per_page,
            'offset'  => ($current_page - 1) * $per_page,
            'search'  => isset($_REQUEST['s']) ? '*' . $_REQUEST['s'] . '*' : '',
        ];

        // Handle sorting
        if (isset($_REQUEST['orderby'])) {
            switch ($_REQUEST['orderby']) {
                case 'customer_name':
                    $args['meta_key'] = 'first_name';
                    $args['orderby'] = 'meta_value';
                    break;
                default:
                    $args['orderby'] = $_REQUEST['orderby'];
            }
            
            $args['order'] = isset($_REQUEST['order']) ? 
                strtoupper($_REQUEST['order']) : 'ASC';
        } else {
            $args['orderby'] = 'registered';
            $args['order'] = 'DESC';
        }

        // Get customers
        $user_query = new \WP_User_Query($args);
        $this->items = $user_query->get_results();

        // Set pagination args
        $total_items = $user_query->get_total();
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'customer-manager')
        ];
    }

    protected function get_views() {
        $views = [];
        $current = isset($_REQUEST['customer_status']) ? $_REQUEST['customer_status'] : 'all';

        // Count customers
        $count_args = ['role' => 'customer'];
        $total_customers = count(get_users($count_args));

        $all_class = $current === 'all' ? ' class="current"' : '';
        $views['all'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            admin_url('admin.php?page=customer-manager'),
            $all_class,
            __('All', 'customer-manager'),
            $total_customers
        );

        return $views;
    }
}