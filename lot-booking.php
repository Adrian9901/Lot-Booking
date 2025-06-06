<?php
/*
Plugin Name: Lot Booking Plugin
Description: A full-featured lot booking plugin with rate, service, and availability management.
Version: 1.0
Author: Adrian Abellanosa (Ad-ios Digital Marketing)
*/
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

add_action('plugins_loaded', function () {
    $updateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/Adrian9901/Lot-Booking/',
        __FILE__,
        'lot-booking' // Must match your plugin folder name
    );

    // Optional: Enable release assets if you use GitHub Releases
    $updateChecker->getVcsApi()->enableReleaseAssets();
});

if (!defined('ABSPATH')) exit;

if (version_compare(PHP_VERSION, '7.4', '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die('This plugin requires PHP version 7.4 or higher.');
}

// Load plugin components on plugins_loaded
add_action('plugins_loaded', 'lbp_boot_plugin');
function lbp_boot_plugin() {
    require_once plugin_dir_path(__FILE__) . 'includes/post-types.php';
    require_once plugin_dir_path(__FILE__) . 'includes/meta-boxes.php';
    require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
    require_once plugin_dir_path(__FILE__) . 'includes/booking-handler.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin-columns.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin-bookings.php';

    add_action('init', function () {
        if (class_exists('LBP_PostTypes'))         LBP_PostTypes::register();
        if (class_exists('LBP_MetaBoxes'))         LBP_MetaBoxes::init();
        if (class_exists('LBP_Shortcodes'))        LBP_Shortcodes::init();
        if (class_exists('LBP_BookingHandler'))    LBP_BookingHandler::init();
        if (class_exists('LBP_Admin_Columns'))     LBP_Admin_Columns::init();
        if (class_exists('LBP_Admin_Bookings_Page')) LBP_Admin_Bookings_Page::init();
    });

    // Delay rate creation after post types are registered
    //add_action('init', 'lbp_create_default_rates', 20);
}


// Enqueue plugin frontend CSS
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('lbp-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
});


// WooCommerce UI renaming
add_filter('gettext', 'lbp_replace_woocommerce_order_text', 999, 3);
add_filter('ngettext', 'lbp_replace_woocommerce_order_text', 999, 3);

function lbp_replace_woocommerce_order_text($translated, $text, $domain) {
    if ($domain === 'woocommerce' && is_string($translated)) {
        $translated = str_ireplace('Orders', 'Bookings', $translated);
        $translated = str_ireplace('Order', 'Booking', $translated);
    }
    return $translated;
}


// Use custom template for single Lot page
add_filter('single_template', function ($template) {
    if (get_post_type() === 'lot') {
        $custom = plugin_dir_path(__FILE__) . 'templates/single-lot.php';
        if (file_exists($custom)) {
            return $custom;
        }
    }
    return $template;
});
add_action('admin_head', function () {
    $screen = get_current_screen();
    if ($screen->post_type === 'rate') {
        echo '<style>.page-title-action, .wrap .add-new-h2 { display: none !important; }</style>';
    }
});
add_action('admin_menu', function () {
    remove_submenu_page('edit.php?post_type=rate', 'post-new.php?post_type=rate');
});

register_activation_hook(__FILE__, 'lbp_create_default_rates');

function lbp_create_default_rates() {
    $default_rates = [
        'Annual'  => 5000,
        'Monthly' => 600,
        'Weekly'  => 420,
        'Nightly' => 60,
    ];

    foreach ($default_rates as $title => $price) {
        $existing = get_posts([
            'post_type'   => 'rate',
            'title'       => $title,
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        if (empty($existing)) {
            $rate_id = wp_insert_post([
                'post_title'  => $title,
                'post_type'   => 'rate',
                'post_status' => 'publish',
                'post_author' => 1,
            ]);

            if (!is_wp_error($rate_id)) {
                update_post_meta($rate_id, 'rate_price', $price);
            }
        }
    }
}
// Register default WooCommerce virtual product on activation
register_activation_hook(__FILE__, function () {
    set_transient('lbp_create_virtual_product', true, 60);
});


add_filter('user_has_cap', function ($all_caps, $caps, $args, $user) {
    if (!isset($args[2])) return $all_caps; // No post ID

    $post_id = $args[2];
    $post = get_post($post_id);

    if ($post && $post->post_type === 'rate') {
        $default_titles = ['Annual', 'Monthly', 'Weekly', 'Nightly'];
        if (in_array($post->post_title, $default_titles, true)) {
            $all_caps['delete_post'] = false;
            $all_caps['delete_page'] = false;
        }
    }

    return $all_caps;
}, 10, 4);

add_action('admin_head', function () {
    global $post;
    if ($post && $post->post_type === 'rate') {
        $default_titles = ['Annual', 'Monthly', 'Weekly', 'Nightly'];
        if (in_array($post->post_title, $default_titles, true)) {
            echo '<style>#delete-action { display: none !important; }</style>';
        }
    }
});

add_action('admin_head-post.php', function () {
    global $post;
    if ($post->post_type !== 'rate') return;

    $default_titles = ['Annual', 'Monthly', 'Weekly', 'Nightly'];
    if (in_array($post->post_title, $default_titles, true)) {
        echo '<style>#titlediv, #edit-slug-box { display: none !important; }</style>';
    }
});

add_filter('wp_insert_post_data', function ($data, $postarr) {
    if ($data['post_type'] !== 'rate') return $data;

    $original = get_post($postarr['ID']);
    if (!$original) return $data;

    $locked_titles = ['Annual', 'Monthly', 'Weekly', 'Nightly'];

    if (in_array($original->post_title, $locked_titles, true)) {
        $data['post_title'] = $original->post_title;
        $data['post_name']  = $original->post_name; // prevent slug changes
    }

    return $data;
}, 10, 2);

// Register custom Lot Booking admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'Lot Booking Settings',
        'Lot Booking',
        'manage_woocommerce',
        'lot-booking-settings',
        'lbp_render_settings_page',
        'dashicons-admin-generic',
        8
    );
});

// Render Lot Booking settings page
function lbp_render_settings_page() {

    $product_id = get_option('lbp_virtual_product_id');
    $product = ($product_id && get_post_type($product_id) === 'product' && get_post_status($product_id) === 'publish')
    ? get_post($product_id)
    : null;
    $status = $_GET['status'] ?? '';

    ?>
    <div class="wrap">
        <h1>Lot Booking Settings</h1>

        <?php if ($status === 'created'): ?>
            <div class="notice notice-success is-dismissible"><p>✅ Virtual product created successfully.</p></div>
        <?php elseif ($status === 'exists'): ?>
            <div class="notice notice-info is-dismissible"><p>ℹ️ Virtual product already exists.</p></div>
        <?php elseif ($status === 'error'): ?>
            <div class="notice notice-error is-dismissible"><p>❌ Failed to create virtual product.</p></div>
        <?php endif; ?>

        <?php if ($product && $product->post_type === 'product'): ?>
            <p><strong>Virtual product:</strong>
                <a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>" target="_blank">
                    <?php echo esc_html($product->post_title); ?>
                </a>
            </p>
        <?php else: ?>
            <p><strong>No virtual product found.</strong></p>
            <a href="<?php echo esc_url(admin_url('admin-post.php?action=create_virtual_lbp_product')); ?>" class="button button-primary">
                Create Virtual Product
            </a>
        <?php endif; ?>
    </div>
    <?php
}

// Handle Create Virtual Product admin POST
add_action('admin_post_create_virtual_lbp_product', function () {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('❌ Unauthorized access.');
    }

    $product_id = get_option('lbp_virtual_product_id');
    $product = $product_id ? get_post($product_id) : null;


    if ($product && get_post_type($product_id) === 'product' && get_post_status($product_id) === 'publish') {
        wp_redirect(admin_url('admin.php?page=lot-booking-settings&status=exists'));
        exit;
    }

    if (!$product || get_post_type($product_id) !== 'product' || get_post_status($product_id) !== 'publish') {
        delete_option('lbp_virtual_product_id'); // cleanup
    }


    $post_id = wp_insert_post([
        'post_title'   => 'Lot Booking',
        'post_type'    => 'product',
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
    ]);
    

    if (!is_wp_error($post_id)) {
        update_post_meta($post_id, '_regular_price', '1');
        update_post_meta($post_id, '_price', '1');
        update_post_meta($post_id, '_virtual', 'yes');
        update_post_meta($post_id, '_sold_individually', 'yes');
        update_post_meta($post_id, '_manage_stock', 'no');
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, '_visibility', 'hidden');

        update_option('lbp_virtual_product_id', $post_id);

        wp_redirect(admin_url('admin.php?page=lot-booking-settings&status=created'));
        exit;
    }

    wp_redirect(admin_url('admin.php?page=lot-booking-settings&status=error'));
    exit;
});


