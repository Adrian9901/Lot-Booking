<?php
class LBP_BookingHandler {
    public static function init() {
        // Optional: Hook into admin or other places if needed
    }
}   
    add_action('wp_loaded', function () {
        if (!isset($_GET['book_lot_now'], $_GET['lot_id'], $_GET['rate_id'])) {
            return;
        }

        
        // Validate parameters
        $lot_id = intval($_GET['lot_id']);
        $rate_id = intval($_GET['rate_id']);
        $checkin = sanitize_text_field($_GET['checkin']);
        $checkout = sanitize_text_field($_GET['checkout']);
        $services = $_GET['services'] ?? [];

        // Get base rate price
        $base_price = floatval(get_post_meta($rate_id, 'rate_price', true));
        if (!$base_price) {
            wp_die('Rate price not found.');
        }

        // Calculate number of days
        $checkin_date = new DateTime($checkin);
        $checkout_date = new DateTime($checkout);
        $days = $checkin_date->diff($checkout_date)->days;

        // Adjust price based on rate type
        $rate_type = strtolower(get_the_title($rate_id));
        $base_price_total = 0;
        $extra_days_total = 0;

        // Duration map
        $rate_days_map = [
            'nightly' => 1,
            'weekly'  => 7,
            'monthly' => 30,
            'annual'  => 365,
        ];

        // Base rate days
        $base_days = $rate_days_map[$rate_type] ?? 0;

        if ($rate_type === 'nightly') {
            $base_price_total = $base_price * $days;
        } elseif ($base_days && $days > 0) {
            $multiplier = intdiv($days, $base_days);
            $remainder = $days % $base_days;

            $base_price_total = $base_price * $multiplier;

            // Add nightly price for remainder days
            $nightly_rate = get_page_by_title('Nightly', OBJECT, 'rate');
            if ($nightly_rate && ($nightly_price = get_post_meta($nightly_rate->ID, 'rate_price', true))) {
                $extra_days_total = floatval($nightly_price) * $remainder;
            }

            $base_price_total += $extra_days_total;
        }

        // Add services
        $services_total = 0;
        foreach ($services as $service_id => $svc_days) {
            $svc_price = floatval(get_post_meta($service_id, 'service_price', true));
            $services_total += $svc_price * intval($svc_days);
        }

        // Add tax if any
        $rate_tax_percent = floatval(get_post_meta($rate_id, 'rate_tax', true));
        $subtotal = $base_price_total + $services_total;
        
        $tax_amount = ($rate_tax_percent / 100) * $subtotal;
        $final_price = $subtotal + $tax_amount;

        // Get WooCommerce product
        $product_id = get_option('lbp_virtual_product_id');
        if (!$product_id || get_post_type($product_id) !== 'product') {
            wp_die('Virtual product is missing.');
        }

        // Empty cart first
        WC()->cart->empty_cart();

        // Add booking to cart
        WC()->cart->add_to_cart($product_id, 1, 0, [], [
            'custom_lot_booking' => true,
            'lot_id' => $lot_id,
            'rate_id' => $rate_id,
            'rate_base_price' => $base_price,
            'rate_title' => ucfirst($rate_type),
            'checkin' => $checkin,
            'checkout' => $checkout,
            'lot_title' => get_the_title($lot_id),
            'selected_services' => $services,
            'tax_amount' => $tax_amount,
            'rate_tax_percent' => $rate_tax_percent,
            'price' => $final_price,
            'base_days' => $base_days,
            'extra_days' => $remainder ?? 0,
            'extra_days_total' => $extra_days_total,
        ]);


        // Redirect to checkout
        wp_redirect(wc_get_checkout_url());
        exit;
    });


    add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id) {
        if (!empty($cart_item_data['custom_lot_booking'])) {
            $cart_item_data['unique_key'] = md5(microtime()); // Prevent merge
        }
        return $cart_item_data;
    }, 10, 2);

    add_filter('woocommerce_get_cart_item_from_session', function($cart_item, $values) {
        foreach (['custom_lot_booking', 'lot_id', 'lot_title', 'rate_id', 'checkin', 'checkout', 'price'] as $key) {
            if (!empty($values[$key])) {
                $cart_item[$key] = $values[$key];
            }
        }
        if (!empty($values['selected_services'])) {
            $cart_item['selected_services'] = $values['selected_services'];
        }

        return $cart_item;
    }, 10, 2);

    add_filter('woocommerce_cart_item_price', function($price, $cart_item, $cart_item_key) {
        if (!empty($cart_item['custom_lot_booking'])) {
            $price = wc_price($cart_item['price']);
        }
        return $price;
    }, 10, 3);

    add_filter('woocommerce_before_calculate_totals', function ($cart) {
        foreach ($cart->get_cart() as $item_key => $item) {
            if (!empty($item['custom_lot_booking']) && isset($item['price'])) {
                $item['data']->set_price($item['price']);
            }
        }
    });
    add_filter('woocommerce_cart_item_name', function ($name, $cart_item, $cart_item_key) {
        if (!empty($cart_item['custom_lot_booking'])) {
            $name = 'Booking: ' . esc_html($cart_item['lot_title']);
        }
        return $name;
    }, 10, 3);

    add_action('woocommerce_before_checkout_form', function () {
        echo '<pre style="background:#fff;padding:10px;border:1px solid #ccc;">';
        print_r(WC()->cart->get_cart());
        echo '</pre>';
    }, 0);


    add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
        if (!empty($cart_item['custom_lot_booking'])) {
            $item_data[] = [
                'key'   => 'Rental Type',
                'value' => $cart_item['rate_title'] ?? '',
            ];

            $item_data[] = [
                'key'   => 'Rental Base Price',
                'value' => '$' . number_format(floatval($cart_item['rate_base_price']), 2),
                
            ];

            $item_data[] = [
                'key'   => 'Lot',
                'value' => get_the_title($cart_item['lot_id'] ?? 0),
            ];
            $item_data[] = [
                'key'   => 'Check-in',
                'value' => $cart_item['checkin'] ?? '—',
            ];
            $item_data[] = [
                'key'   => 'Check-out',
                'value' => $cart_item['checkout'] ?? '—',
            ];
            if (!empty($cart_item['extra_days'])) {
                $item_data[] = [
                    'key' => 'Extra Days',
                    'value' => $cart_item['extra_days'] . ' day(s) - $' . number_format(floatval($cart_item['extra_days_total']), 2),
                ];
            }

            $item_data[] = [
                'key'   => 'Tax',
                'value' => number_format($cart_item['tax_amount'], 2) . ' (' . $cart_item['rate_tax_percent'] . '%)',
            ];
            if (!empty($cart_item['selected_services'])) {
                foreach ($cart_item['selected_services'] as $service_id => $days) {
                    $title = get_the_title($service_id);
                    $price = get_post_meta($service_id, 'service_price', true);
                    $item_data[] = [
                        'key' => 'Service',
                        'value' => $title . ' - ' . $days . ' day(s) - $' . number_format(floatval($price) * $days, 2),
                    ];
                }
            }
        }
        return $item_data;
    }, 10, 2);

    add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
        if (!empty($values['custom_lot_booking'])) {
            $service_summary = [];

            if (!empty($values['selected_services']) && is_array($values['selected_services'])) {
                foreach ($values['selected_services'] as $service_id => $days) {
                    $service_title = get_the_title($service_id);
                    $service_price = get_post_meta($service_id, 'service_price', true);
                    $total_price = floatval($service_price) * intval($days);

                    $service_summary[] = sprintf(
                        '%s ($%s / Per Day) - %d day(s) - $%s',
                        $service_title,
                        number_format((float)$service_price, 2),
                        $days,
                        number_format((float)$total_price, 2)
                    );
                }
            }

            $meta = [
                'lot_id'            => $values['lot_id'] ?? '',
                'lot_title'         => $values['lot_title'] ?? '',
                'rate_title'        => $values['rate_title'] ?? '',
                'rate_base_price'   => $values['rate_base_price'] ?? '',
                'rate_id'           => $values['rate_id'] ?? '',
                'checkin'           => $values['checkin'] ?? '',
                'checkout'          => $values['checkout'] ?? '',
                'extra_days'        => $values['extra_days'] ?? '',
                'tax'               => $values['tax_amount'] ?? '',
                'selected_services' => !empty($service_summary) ? implode("\n", $service_summary) : '',
            ];

            foreach ($meta as $label => $value) {
                if (!empty($value)) {
                    $item->add_meta_data($label, $value, true);
                }
            }
        }
    }, 10, 4);

    add_action('woocommerce_checkout_update_order_meta', function($order_id, $data) {
        $cart = WC()->cart->get_cart();
        foreach ($cart as $cart_item) {
            if (!empty($cart_item['custom_lot_booking'])) {
                update_post_meta($order_id, '_lot_id', $cart_item['lot_id']);
                update_post_meta($order_id, '_rate_id', $cart_item['rate_id']);
                update_post_meta($order_id, '_checkin', $cart_item['checkin']);
                update_post_meta($order_id, '_checkout', $cart_item['checkout']);
                update_post_meta($order_id, '_selected_services', $cart_item['selected_services']);
            }
        }
    }, 10, 2);

