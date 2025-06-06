<?php
class LBP_Admin_Columns {

    public static function init() {
        // Bookings
        add_filter('manage_booking_posts_columns', [__CLASS__, 'set_custom_columns']);
        add_action('manage_booking_posts_custom_column', [__CLASS__, 'custom_column_content'], 10, 2);
        add_filter('manage_edit-booking_sortable_columns', [__CLASS__, 'set_sortable_columns']);

        // Lots
        add_filter('manage_lot_posts_columns', [__CLASS__, 'lot_custom_columns']);
        add_action('manage_lot_posts_custom_column', [__CLASS__, 'lot_custom_column_data'], 10, 2);

        // Rates
        add_filter('manage_rate_posts_columns', [__CLASS__, 'rate_custom_columns']);
        add_action('manage_rate_posts_custom_column', [__CLASS__, 'rate_custom_column_data'], 10, 2);

        // Services
        add_filter('manage_lot_service_posts_columns', [__CLASS__, 'service_custom_columns']);
        add_action('manage_lot_service_posts_custom_column', [__CLASS__, 'service_custom_column_data'], 10, 2);
    }

    public static function set_custom_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['booking_name'] = 'Name';
                $new_columns['booking_phone'] = 'Phone';
                $new_columns['booking_email'] = 'Email';
                $new_columns['booking_lot'] = 'Lot';
                $new_columns['booking_checkin'] = 'Check-in';
                $new_columns['booking_checkout'] = 'Check-out';
                $new_columns['booking_status'] = 'Status';
            }
        }
        return $new_columns;
    }

    public static function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'booking_name':
                echo esc_html(get_post_meta($post_id, 'booking_name', true));
                break;
            case 'booking_phone':
                echo esc_html(get_post_meta($post_id, 'booking_phone', true));
                break;
            case 'booking_email':
                echo esc_html(get_post_meta($post_id, 'booking_email', true));
                break;
            case 'booking_lot':
                $lot_id = get_post_meta($post_id, 'booking_lot_id', true);
                if ($lot_id) {
                    echo '<a href="' . get_edit_post_link($lot_id) . '">' . esc_html(get_the_title($lot_id)) . '</a>';
                }
                break;
            case 'booking_checkin':
                echo esc_html(get_post_meta($post_id, 'booking_checkin', true));
                break;
            case 'booking_checkout':
                echo esc_html(get_post_meta($post_id, 'booking_checkout', true));
                break;
            case 'booking_status':
                echo esc_html(get_post_meta($post_id, 'booking_status', true));
                break;
        }
    }

    public static function set_sortable_columns($columns) {
        $columns['booking_checkin'] = 'booking_checkin';
        $columns['booking_checkout'] = 'booking_checkout';
        $columns['booking_status'] = 'booking_status';
        return $columns;
    }

    public static function lot_custom_columns($columns) {
        $columns['rates'] = 'Rates';
        $columns['services'] = 'Services';
        return $columns;
    }

    public static function lot_custom_column_data($column, $post_id) {
        if ($column === 'rates') {
            $rates = get_post_meta($post_id, 'lot_rates', true);
            if ($rates) {
                foreach ((array) $rates as $rate_id) {
                    echo esc_html(get_the_title($rate_id)) . '<br>';
                }
            }
        }
        if ($column === 'services') {
            $services = get_post_meta($post_id, 'lot_services', true);
            if ($services) {
                foreach ((array) $services as $service_id) {
                    echo esc_html(get_the_title($service_id)) . '<br>';
                }
            }
        }
    }

    public static function rate_custom_columns($columns) {
        $columns['price'] = 'Price';
        return $columns;
    }

    public static function rate_custom_column_data($column, $post_id) {
        if ($column === 'price') {
            echo '$' . esc_html(get_post_meta($post_id, 'rate_price', true));
        }
    }

    public static function service_custom_columns($columns) {
        $columns['price'] = 'Price';
        return $columns;
    }

    public static function service_custom_column_data($column, $post_id) {
        if ($column === 'price') {
            echo '$' . esc_html(get_post_meta($post_id, 'service_price', true));
        }
    }
}
