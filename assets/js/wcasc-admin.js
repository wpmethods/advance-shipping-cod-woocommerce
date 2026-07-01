jQuery(function ($) {
    const $costType = $('#wcasc_advance_cost_type');
    const $customPriceRow = $('.wcasc-custom-price-row');

    function toggleCustomPrice() {
        const value = $costType.val();
        if (value === 'custom_price' || value === 'both') {
            $customPriceRow.removeClass('is-hidden');
        } else {
            $customPriceRow.addClass('is-hidden');
        }
    }

    toggleCustomPrice();
    $costType.on('change', toggleCustomPrice);
});
