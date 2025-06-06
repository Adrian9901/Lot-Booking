document.addEventListener('DOMContentLoaded', function () {
    const rateSelector = document.getElementById('rate_selector');
    const checkinInput = document.getElementById('checkin');
    const checkoutInput = document.getElementById('checkout');

    // Disable past dates
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const minDate = `${yyyy}-${mm}-${dd}`;
    checkinInput.setAttribute('min', minDate);
    checkoutInput.setAttribute('min', minDate);

    function getRateType() {
        return rateSelector.options[rateSelector.selectedIndex]?.getAttribute('data-rate-type');
    }

    function selectRateByType(type) {
        for (let i = 0; i < rateSelector.options.length; i++) {
            if (rateSelector.options[i].getAttribute('data-rate-type') === type) {
                rateSelector.selectedIndex = i;
                break;
            }
        }
    }

    function updateCheckout() {
        const rateType = getRateType();
        const checkinValue = checkinInput.value;

        if (!checkinValue) {
            checkoutInput.value = '';
            return;
        }

        const [year, month, day] = checkinValue.split('-');
        const checkinDate = new Date(year, month - 1, day);
        const checkoutDate = new Date(checkinDate);

        if (rateType === 'Weekly') {
            checkoutDate.setDate(checkoutDate.getDate() + 7);
        } else if (rateType === 'Monthly') {
            checkoutDate.setDate(checkoutDate.getDate() + 30);
        } else if (rateType === 'Annual') {
            checkoutDate.setDate(checkoutDate.getDate() + 365);
        } else {
            checkoutInput.value = '';
            return;
        }

        const yyyy = checkoutDate.getFullYear();
        const mm = String(checkoutDate.getMonth() + 1).padStart(2, '0');
        const dd = String(checkoutDate.getDate()).padStart(2, '0');

        checkoutInput.value = `${yyyy}-${mm}-${dd}`;
    }

    function validateCheckoutDate() {
        const checkinValue = checkinInput.value;
        const checkoutValue = checkoutInput.value;

        if (!checkinValue || !checkoutValue) return;

        const checkinDate = new Date(checkinValue);
        const checkoutDate = new Date(checkoutValue);

        if (checkoutDate < checkinDate) {
            alert('Check-out date cannot be before check-in date.');
            checkoutInput.value = '';
        }
    }

    function validateAnnualStay() {
        const rateType = getRateType();
        const checkinValue = checkinInput.value;
        const checkoutValue = checkoutInput.value;

        if (!checkinValue || !checkoutValue || rateType !== 'Annual') return;

        const inDate = new Date(checkinValue);
        const outDate = new Date(checkoutValue);
        const diffDays = Math.floor((outDate - inDate) / (1000 * 60 * 60 * 24));

        if (diffDays < 365) {
            alert('Not a valid Annual stay. Changed to Monthly.');
            selectRateByType('Monthly');
            updateCheckout();
        }
    }

    function autoAdjustRateByNights() {
        const checkin = new Date(checkinInput.value);
        const checkout = new Date(checkoutInput.value);

        if (!checkinInput.value || !checkoutInput.value) return;

        const nights = Math.floor((checkout - checkin) / (1000 * 60 * 60 * 24));
        const currentRate = getRateType();

        if (currentRate === 'Nightly' && nights > 7) {
            selectRateByType('Weekly');
            alert('Booking exceeds 7 nights. Rate changed to Weekly.');
            updateCheckout();
        } else if (currentRate === 'Weekly' && nights > 14) {
            selectRateByType('Monthly');
            alert('Booking exceeds 14 nights. Rate changed to Monthly.');
            updateCheckout();
        }
    }

    rateSelector.addEventListener('change', updateCheckout);
    checkinInput.addEventListener('change', function () {
        updateCheckout();
        validateCheckoutDate();
        autoAdjustRateByNights();
    });

    checkoutInput.addEventListener('change', function () {
        validateCheckoutDate();
        validateAnnualStay();
        autoAdjustRateByNights();
    });
});
