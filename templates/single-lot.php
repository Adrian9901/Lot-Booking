<?php
/**
 * Template Name: Lot Detail Page
 * Template Post Type: lot
 */

get_header();

while ( have_posts() ) : the_post();
    $lot_id = get_the_ID();
    $rate_id = isset($_GET['rate_id']) ? intval($_GET['rate_id']) : 0;
    $checkin = sanitize_text_field($_GET['checkin'] ?? '');
    $checkout = sanitize_text_field($_GET['checkout'] ?? '');
    $rate_ids = get_post_meta($lot_id, 'lot_rates', true);
    $service_ids = get_post_meta($lot_id, 'lot_services', true);
    $featured_image = get_the_post_thumbnail($lot_id, 'large', ['class' => 'img-fluid mb-4']);
?>

<main id="primary" class="site-main">
    <div class="container my-5">
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

            <?php if ($featured_image): ?>
                <div class="lot-thumbnail-wrapper text-center">
                    <?php echo $featured_image; ?>
                </div>
            <?php endif; ?>

            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
            </header>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <?php if ($rate_id && in_array($rate_id, (array)$rate_ids)) :
                $rate_post = get_post($rate_id);
                if ($rate_post) : ?>
                    <section class="lot-rates mt-5">
                        <!-- <h2>Rate Description</h2> -->
                        <div>
                            <?php
                                echo wp_kses_post(
                                    // '<strong>' . esc_html($rate_post->post_title) . ':</strong> ' .
                                    apply_filters('the_content', $rate_post->post_content)
                                );
                            ?>
                        </div>
                    </section>
            <?php endif; endif; ?>

            <?php if (!empty($checkin) && !empty($checkout)) : ?>
                <section class="lot-dates mt-4">
                    <p><strong>Check-in:</strong> <?php echo esc_html($checkin); ?></p>
                    <p><strong>Check-out:</strong> <?php echo esc_html($checkout); ?></p>
                </section>
            <?php endif; ?>

            <?php if (!empty($service_ids)) : ?>
                <section class="lot-services mt-5">
                    <h2>Available Services</h2>
                    <form id="services-form">
                        <ul class="list-unstyled">
                            <?php foreach ((array)$service_ids as $service_id) :
                                $title = get_the_title($service_id);
                                $price = get_post_meta($service_id, 'service_price', true);
                            ?>
                                <li class="mb-3">
                                    <label>
                                        <input type="checkbox" name="services[<?php echo esc_attr($service_id); ?>][enabled]" class="service-checkbox" />
                                        <?php echo esc_html($title); ?> - $<?php echo number_format(floatval($price), 2); ?>
                                    </label>
                                    <div class="days-wrapper ms-3 mt-2" style="display: none;">
                                        Days: <input type="number" min="1" value="1" name="services[<?php echo esc_attr($service_id); ?>][days]" />
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn btn-primary" id="continue-booking">Continue</button>
                    </form>
                </section>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.service-checkbox').forEach(function(checkbox) {
                        checkbox.addEventListener('change', function() {
                            const wrapper = this.closest('li').querySelector('.days-wrapper');
                            wrapper.style.display = this.checked ? 'block' : 'none';
                        });
                    });

                    document.getElementById('continue-booking').addEventListener('click', function () {
                        const params = new URLSearchParams();
                        params.set('book_lot_now', '1');
                        params.set('lot_id', '<?php echo esc_js($lot_id); ?>');
                        params.set('rate_id', '<?php echo esc_js($rate_id); ?>');
                        params.set('checkin', '<?php echo esc_js($checkin); ?>');
                        params.set('checkout', '<?php echo esc_js($checkout); ?>');

                        document.querySelectorAll('.service-checkbox:checked').forEach(cb => {
                            const match = cb.name.match(/services\[(\d+)\]/);
                            if (match) {
                                const serviceId = match[1];
                                const daysInput = cb.closest('li').querySelector('input[type="number"]');
                                const days = daysInput ? daysInput.value : 1;
                                params.append(`services[${serviceId}]`, days);
                            }
                        });

                        window.location.href = '<?php echo esc_url(site_url()); ?>' + '?' + params.toString();
                    });
                });
                </script>
            <?php endif; ?>
        </article>
    </div>
</main>

<?php
endwhile;
get_footer();
