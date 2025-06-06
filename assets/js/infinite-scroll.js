jQuery(function ($) {
    let offset = 0;
    const $results = $('#lot-results');
    const $loader = $('#scroll-loader');
    const $backToTop = $('#back-to-top');
    let loading = false;

    function loadLots() {
        if (loading) return;
        loading = true;
        $loader.show();

        $.post(lbpInfinite.ajax_url, {
            action: 'lbp_load_more_lots',
            offset: offset,
            rate_id: lbpInfinite.rate_id,
            checkin: lbpInfinite.checkin,
            checkout: lbpInfinite.checkout
        }, function (res) {
            if (res.success && res.data.trim()) {
                $results.append(res.data);
                offset += 5;
                loading = false;
                $loader.hide();
            } else {
                $loader.html('<p>No more results.</p>');
            }
        });
    }

    $(window).on('scroll', function () {
        if ($(window).scrollTop() > 300) {
            $backToTop.fadeIn();
        } else {
            $backToTop.fadeOut();
        }

        if ($(window).scrollTop() + $(window).height() >= $(document).height() - 100) {
            loadLots();
        }
    });

    $backToTop.on('click', function () {
        $('html, body').animate({ scrollTop: 0 }, 600);
    });

    // Initial load
    loadLots();
});
