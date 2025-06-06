<?php
class LBP_Shortcodes {
    public static function init() {
        add_shortcode('lot_search_form', [self::class, 'render_search_form']);
        add_shortcode('lot_search_results', [self::class, 'render_search_results']);
    }

    public static function render_search_form() {
        wp_enqueue_script(
            'lbp-date-autofill',
            plugin_dir_url(dirname(__DIR__)) . 'lot-booking/assets/js/booking-date-autofill.js',
            [],
            '1.0',
            true
        );

        ob_start();
        $rates = get_posts(['post_type' => 'rate', 'numberposts' => -1]);
        ?>
        <form id="lot-form" method="GET" action="<?php echo esc_url(site_url('/lot-search-results/')); ?>">
            <div class="form-group">
                <label for="rate_selector">Rate Type:</label>
                <select name="rate_id" id="rate_selector">
                    <?php foreach ($rates as $rate): ?>
                        <option value="<?php echo esc_attr($rate->ID); ?>" data-rate-type="<?php echo esc_attr($rate->post_title); ?>">
                            <?php echo esc_html($rate->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="checkin">Check-in:</label>
                <input type="date" name="checkin" id="checkin" required>
            </div>

            <div class="form-group">
                <label for="checkout">Check-out:</label>
                <input type="date" name="checkout" id="checkout" required>
            </div>

            <div class="form-group full-width">
                <input type="submit" value="Search">
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function render_search_results() {
        wp_enqueue_script('lbp-infinite-scroll', plugin_dir_url(dirname(__DIR__)) . 'lot-booking/assets/js/infinite-scroll.js', ['jquery'], '1.0', true);
        wp_localize_script('lbp-infinite-scroll', 'lbpInfinite', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rate_id' => intval($_GET['rate_id'] ?? 0),
            'checkin' => sanitize_text_field($_GET['checkin'] ?? ''),
            'checkout' => sanitize_text_field($_GET['checkout'] ?? '')
        ]);

        echo '<h3>Available Lots</h3>';
        echo '<div id="lot-results"></div>';
        echo '<div id="scroll-loader" style="text-align:center;display:none;"><img src="' . esc_url(plugin_dir_url(dirname(__DIR__)) . 'lot-booking/assets/img/spinner.gif') . '" width="40" /></div>';
        echo '<button id="back-to-top" style="display:none;position:fixed;bottom:20px;right:20px;">↑ Back to Top</button>';
    }
}

add_action('wp_ajax_lbp_load_more_lots', 'lbp_load_more_lots');
add_action('wp_ajax_nopriv_lbp_load_more_lots', 'lbp_load_more_lots');

function lbp_load_more_lots() {
    $rate_id = intval($_POST['rate_id']);
    $checkin = sanitize_text_field($_POST['checkin']);
    $checkout = sanitize_text_field($_POST['checkout']);
    $offset = intval($_POST['offset']);

    $lots = get_posts([
        'post_type' => 'lot',
        'posts_per_page' => 5,
        'offset' => $offset,
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    

    $search_start = new DateTime($checkin);

    // Get rate type duration
    $rate_title = get_the_title($rate_id);
    $rate_durations = [
        'Annual' => 365,
        'Monthly' => 30,
        'Weekly' => 7,
        'Nightly' => 1,
    ];
    $duration_days = $rate_durations[$rate_title] ?? 1;

    // Force search_end = search_start + duration (ignore user-modified checkout)
    $search_end = (clone $search_start)->modify("+{$duration_days} days");


    ob_start();
    foreach ($lots as $lot) {
        $rates = get_post_meta($lot->ID, 'lot_rates', true);
        if (!in_array($rate_id, (array)$rates)) continue;

        $is_available = true;
        $latest_end = null;

        $existing_orders = wc_get_orders([
            'limit' => -1,
            'status' => ['wc-processing', 'wc-completed'],
        ]);

        foreach ($existing_orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_meta('lot_id') != $lot->ID) continue;

                $book_start = new DateTime($item->get_meta('checkin'));
                $book_end_raw = $item->get_meta('checkout');
                $rate_type = get_post_meta($item->get_meta('rate_id'), '_rate_type', true);

                if ($book_end_raw) {
                    $book_end = new DateTime($book_end_raw);
                } else {
                    $block_days = $rate_durations[$rate_type] ?? 1;
                    $book_end = (clone $book_start)->modify("+{$block_days} days");
                }

                $search_start_dt = new DateTime($checkin);
                $search_end_dt = new DateTime($checkout);

                // Treat booking as [book_start, book_end)
                if (
                    $search_start_dt < $book_end &&
                    $search_end_dt > $book_start
                ) {
                    $is_available = false;
                }


                if (!$latest_end || $book_end > $latest_end) {
                    $latest_end = $book_end;
                }
            }
        }

        $description = apply_filters('the_content', $lot->post_content);
        $rate_price = get_post_meta($rate_id, 'rate_price', true);
        $rate_title = get_the_title($rate_id);

        echo '<div class="lot-result">';
        // if (has_post_thumbnail($lot->ID)) {
        //     echo '<div class="lot-thumbnail-wrapper">';
        //     echo get_the_post_thumbnail($lot->ID, 'large', ['class' => 'lot-thumbnail']);
        //     echo '</div>';
        // }
        echo '<h4 class="lot-title">' . esc_html($lot->post_title) . '</h4>';
        echo '<div class="lot-description">' . $description . '</div>';

        if ($rate_title && $rate_price) {
            echo '<p><strong>Rate:</strong> ' . esc_html($rate_title) . ' - $' . number_format(floatval($rate_price), 2) . '</p>';
        }

        if ($is_available) {
            echo '<div class="lot-book"><a class="book-now" href="' . esc_url(add_query_arg([
                'rate_id' => $rate_id,
                'checkin' => $checkin,
                'checkout' => $checkout
            ], get_permalink($lot->ID))) . '">Book Now</a></div>';
        } else {
            echo '<p style="color:red;"><strong>This lot is not available for the selected dates.</strong></p>';
            if ($latest_end) {
                echo '<p><em>Next available: ' . esc_html($latest_end->format('F j, Y')) . '</em></p>';
            }
            echo '<button class="book-now" disabled style="background-color:red">Unavailable</button>';
        }

        echo '</div><hr>';
    }

    wp_send_json_success(ob_get_clean());
}
