jQuery(function ($) {
    function positionSummary() {
        const $summary = $('#aswpm-notice-wrap');
        const position = $summary.data('position');
        if (position === 'subtotal') {
            const $table = $summary.closest('table');
            const $subtotal = $table.find('tr.cart-subtotal').first();
            if ($subtotal.length) $summary.closest('tr').insertAfter($subtotal);
        } else if (position === 'after_payment_title' || position === 'after_payment_methods') {
            const $payment = $summary.closest('#payment, .woocommerce-checkout-payment');
            const $methods = $payment.find('ul.wc_payment_methods, ul.payment_methods').first();
            if ($methods.length) {
                if (position === 'after_payment_title') {
                    $summary.insertBefore($methods);
                } else {
                    $summary.insertAfter($methods);
                }
            }
        }
    }
    function refreshInitialGateway() {
        const $summary = $('#aswpm-notice-wrap');
        const selected = $('input[name="payment_method"]:checked').val();
        if ($summary.data('gateway-plans') === true && selected && selected !== $summary.attr('data-selected-gateway')) {
            $(document.body).trigger('update_checkout');
        }
    }
    positionSummary();
    $(document.body).on('updated_checkout', function () {
        positionSummary();
        refreshInitialGateway();
    });
    $(document.body).on('change', 'input[name="aswpm_payment_mode"]', function () {
        $(document.body).trigger('update_checkout');
    });
    $(document.body).on('payment_method_selected', function () {
        const $summary = $('#aswpm-notice-wrap');
        if ($summary.data('allow-cod') === true || $summary.data('gateway-plans') === true) {
            $(document.body).trigger('update_checkout');
        }
    });
    refreshInitialGateway();
});
