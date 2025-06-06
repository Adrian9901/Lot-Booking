jQuery(document).ready(function () {
    const calendarEl = document.getElementById('lbp-booking-calendar');

    if (!calendarEl || typeof FullCalendar === 'undefined') {
        console.error('FullCalendar is not loaded or calendar element missing.');
        return;
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        plugins: [
            FullCalendar.DayGrid.default,
            FullCalendar.TimeGrid.default,
            FullCalendar.List.default
        ],
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            const params = new URLSearchParams();
            params.append('action', 'lbp_get_bookings');
            params.append('nonce', LBP_Calendar_Data.nonce);

            const rate = document.getElementById('filter-rate')?.value;
            const lot = document.getElementById('filter-lot')?.value;
            const checkin = document.getElementById('filter-checkin')?.value;
            const checkout = document.getElementById('filter-checkout')?.value;

            if (rate) params.append('rate_id', rate);
            if (lot) params.append('lot_id', lot);
            if (checkin) params.append('checkin', checkin);
            if (checkout) params.append('checkout', checkout);

            fetch(`${LBP_Calendar_Data.ajax_url}?${params.toString()}`)
                .then(res => res.json())
                .then(events => successCallback(events))
                .catch(err => failureCallback(err));
        },
        eventDidMount: function(info) {
            const tooltip = `${info.event.title}\nRate: ${info.event.extendedProps?.rate ?? ''}\nCustomer: ${info.event.extendedProps?.customer ?? ''}`;
            info.el.setAttribute('title', tooltip);
        }
    });

    calendar.render();

    ['filter-rate', 'filter-lot', 'filter-checkin', 'filter-checkout'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => calendar.refetchEvents());
    });
});
