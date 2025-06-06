<?php
class LBP_Admin_Bookings_Page {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_lbp_get_bookings', [__CLASS__, 'ajax_get_bookings']);
    }

    public static function add_menu_page() {
        add_menu_page(
            'Booking Calendar',
            'Bookings',
            'manage_options',
            'booking-calendar',
            [__CLASS__, 'render_booking_calendar_page'],
            'dashicons-calendar-alt',
            9
        );
    }

    public static function render_booking_calendar_page() {
        ?>
        <div class="wrap">
            <h1>Booking Calendar</h1>

            <div style="margin-bottom: 1em;">
                <select id="filter-rate" class="filter-select">
                    <option value="">All Rates</option>
                    <?php
                    $rates = get_posts(['post_type' => 'rate', 'numberposts' => -1]);
                    foreach ($rates as $rate) {
                        echo '<option value="' . esc_attr($rate->ID) . '">' . esc_html($rate->post_title) . '</option>';
                    }
                    ?>
                </select>

                <select id="filter-lot" class="filter-select">
                    <option value="">All Lots</option>
                    <?php
                    $lots = get_posts(['post_type' => 'lot', 'numberposts' => -1]);
                    foreach ($lots as $lot) {
                        echo '<option value="' . esc_attr($lot->ID) . '">' . esc_html($lot->post_title) . '</option>';
                    }
                    ?>
                </select>

                <input type="date" id="filter-checkin" />
                <input type="date" id="filter-checkout" />
            </div>

            <div id="lbp-booking-calendar"></div>
        </div>
        <?php
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_booking-calendar') return;
        // Modular global plugin scripts
        wp_enqueue_script('fc-core', 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/index.global.min.js', [], null, true);
        wp_enqueue_script('fc-daygrid', 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/index.global.min.js', ['fc-core'], null, true);
        wp_enqueue_script('fc-timegrid', 'https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.8/index.global.min.js', ['fc-core'], null, true);
        wp_enqueue_script('fc-list', 'https://cdn.jsdelivr.net/npm/@fullcalendar/list@6.1.8/index.global.min.js', ['fc-core'], null, true);

        // Your script (after FC)
        wp_enqueue_script(
            'lbp-calendar-init',
            plugin_dir_url(__DIR__) . 'assets/js/lbp-calendar-init.js',
            ['jquery', 'fc-core', 'fc-daygrid', 'fc-timegrid', 'fc-list'],
            '1.0',
            true
        );

        wp_localize_script('lbp-calendar-init', 'LBP_Calendar_Data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('lbp_calendar_nonce')
        ]);
    }
    public static function ajax_get_bookings() {
    check_ajax_referer('lbp_calendar_nonce', 'nonce');

    $orders = wc_get_orders([
        'limit' => -1,
        'status' => ['wc-completed'],
    ]);

    $events = [];

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $lot_id   = $item->get_meta('lot_id');
            $lot_title  = get_the_title($lot_id);
            $checkin  = $item->get_meta('checkin');
            $checkout = $item->get_meta('checkout');
            $rate_id = $item->get_meta('rate_id');
            $rate_title = $rate_id ? get_the_title($rate_id) : '';
            $customer = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

            $color = '#999'; // Default gray

            if (stripos($rate_title, 'Annual') !== false) {
                $color = '#007bff'; // Blue
            } elseif (stripos($rate_title, 'Monthly') !== false) {
                $color = '#28a745'; // Green
            } elseif (stripos($rate_title, 'Weekly') !== false) {
                $color = '#ffc107'; // Yellow
            } elseif (stripos($rate_title, 'Nightly') !== false) {
                $color = '#dc3545'; // Red
            }


            if ($lot_id && $checkin && $checkout) {
                $events[] = [
                    'title' => "{$lot_title} – {$rate_title} ( {$customer} )",
                    'start' => $checkin,
                    'end'   => (new DateTime($checkout))->modify('+1 day')->format('Y-m-d'),
                    'color' => $color,
                    'extendedProps' => [
                        'rate'     => $rate_title,
                        'customer' => $customer
                    ]
                ];
            }
        }
    }

    wp_send_json($events);
}

}
