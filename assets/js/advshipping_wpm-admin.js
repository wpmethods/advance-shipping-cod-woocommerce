jQuery(function ($) {
    const $options = $('input[name="advshipping_wpm_payment_modes[]"]');
    const $defaults = $('input[name="advshipping_wpm_advance_cost_type"]');

    function updateOptions() {
        const enabled = $options.filter(':checked').map(function () { return this.value; }).get();
        $defaults.each(function () {
            const available = enabled.includes(this.value);
            $(this).prop('disabled', !available);
            if (!available) $(this).prop('checked', false);
            $(this).closest('tr').toggleClass('is-enabled', available);
        });
        if (!$defaults.filter(':checked').length && enabled.length) {
            $defaults.filter(function () { return this.value === enabled[0]; }).prop('checked', true);
        }
        $('.advshipping_wpm-fixed-setting').toggle(enabled.some(function (mode) {
            return ['custom_price', 'both', 'combined'].includes(mode);
        }));
        const hasPercentage = enabled.some(function (mode) {
            return ['percentage', 'shipping_percentage', 'combined'].includes(mode);
        });
        $('.advshipping_wpm-percentage-setting').toggle(hasPercentage);
    }

    $options.on('change', function () {
        // Keep at least one payment option enabled.
        if (!$options.filter(':checked').length) $(this).prop('checked', true);
        updateOptions();
    });
    updateOptions();
});
