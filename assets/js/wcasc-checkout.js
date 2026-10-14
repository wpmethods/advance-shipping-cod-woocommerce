jQuery(function ($) {
    function positionSummary() {
        const $summary = $('#wcasc-notice-wrap');
        if ($summary.data('position') === 'subtotal') {
            const $table = $summary.closest('table');
            const $subtotal = $table.find('tr.cart-subtotal').first();
            if ($subtotal.length) $summary.closest('tr').insertAfter($subtotal);
        }
    }
    positionSummary();
    $(document.body).on('updated_checkout', positionSummary);
    $(document.body).on('change', 'input[name="wcasc_payment_mode"]', function () {
        $(document.body).trigger('update_checkout');
    });
    $(document.body).on('payment_method_selected', function () {
        if ($('#wcasc-notice-wrap').data('allow-cod') === true) {
            $(document.body).trigger('update_checkout');
        }
    });
});
