<?php
class LBP_MetaBoxes {
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_meta_boxes']);
    }

    public static function add_meta_boxes() {
        add_meta_box('lot_details', 'Lot Details', [__CLASS__, 'lot_meta_box'], 'lot', 'normal', 'default');
        add_meta_box('rate_details', 'Rate Details', [__CLASS__, 'rate_meta_box'], 'rate', 'normal', 'default');
        add_meta_box('service_details', 'Service Details', [__CLASS__, 'service_meta_box'], 'lot_service', 'normal', 'default');
    }

    public static function lot_meta_box($post) {
        $services = get_posts(['post_type' => 'lot_service', 'numberposts' => -1]);
        $rates = get_posts(['post_type' => 'rate', 'numberposts' => -1]);
        $selected_services = get_post_meta($post->ID, 'lot_services', true) ?: [];
        $selected_rates = get_post_meta($post->ID, 'lot_rates', true) ?: [];
        ?>
        <p><strong>Assign Rates:</strong></p>
        <?php foreach ($rates as $rate): ?>
            <label><input type="checkbox" name="lot_rates[]" value="<?php echo $rate->ID; ?>" <?php checked(in_array($rate->ID, $selected_rates)); ?>> <?php echo $rate->post_title; ?></label><br>
        <?php endforeach; ?>
        <p><strong>Assign Services:</strong></p>
        <?php foreach ($services as $service): ?>
            <label><input type="checkbox" name="lot_services[]" value="<?php echo $service->ID; ?>" <?php checked(in_array($service->ID, $selected_services)); ?>> <?php echo $service->post_title; ?></label><br>
        <?php endforeach;
    }

    public static function rate_meta_box($post) {
        $price = get_post_meta($post->ID, 'rate_price', true);
        $tax = get_post_meta($post->ID, 'rate_tax', true);
        ?>
        <p><label>Price: <input type="number" step="0.01" name="rate_price" value="<?php echo esc_attr($price); ?>"></label></p>
        <p><label>Tax %: <input type="number" step="0.01" name="rate_tax" value="<?php echo esc_attr($tax); ?>"></label></p>
        <?php
    }

    public static function service_meta_box($post) {
        $price = get_post_meta($post->ID, 'service_price', true);
        ?>
        <p><label>Price: <input type="number" step="0.01" name="service_price" value="<?php echo esc_attr($price); ?>"></label></p>
        <?php
    }

    public static function save_meta_boxes($post_id) {
        if (isset($_POST['rate_price'])) {
            update_post_meta($post_id, 'rate_price', sanitize_text_field($_POST['rate_price']));
            update_post_meta($post_id, 'rate_tax', sanitize_text_field($_POST['rate_tax']));
        }

        if (isset($_POST['service_price'])) {
            update_post_meta($post_id, 'service_price', sanitize_text_field($_POST['service_price']));
        }

        if (isset($_POST['lot_services'])) {
            update_post_meta($post_id, 'lot_services', array_map('intval', $_POST['lot_services']));
        } else {
            delete_post_meta($post_id, 'lot_services');
        }

        if (isset($_POST['lot_rates'])) {
            update_post_meta($post_id, 'lot_rates', array_map('intval', $_POST['lot_rates']));
        } else {
            delete_post_meta($post_id, 'lot_rates');
        }
    }
}
