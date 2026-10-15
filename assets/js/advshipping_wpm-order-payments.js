jQuery(function ($) {
    $(document).on('click', '.advshipping_wpm-record-collection, .advshipping_wpm-reverse-collection, .advshipping_wpm-sync-analytics', function () {
        const $button = $(this);
        const $panel = $button.closest('.advshipping_wpm-order-payments');
        const operation = $button.hasClass('advshipping_wpm-record-collection') ? 'record' : ($button.hasClass('advshipping_wpm-reverse-collection') ? 'reverse' : 'sync');
        if (operation === 'reverse' && !window.confirm($panel.data('reverse-confirm'))) return;
        const $feedback = $panel.find('.advshipping_wpm-collection-feedback');
        if (operation === 'record' && !$panel.find('.advshipping_wpm-collection-amount')[0].reportValidity()) return;
        $panel.find('button').prop('disabled', true);
        $panel.attr('aria-busy', 'true');
        $feedback.removeClass('is-error').text($panel.data('saving-text'));
        $.post(ajaxurl, {
            action: 'advshipping_wpm_order_collection',
            order_id: $panel.data('order-id'),
            nonce: $panel.data('nonce'),
            request_id: $panel.data('request-id'),
            operation: operation,
            amount: $panel.find('.advshipping_wpm-collection-amount').val(),
            method: $panel.find('.advshipping_wpm-collection-method').val(),
            reference: $panel.find('.advshipping_wpm-collection-reference').val(),
            entry_id: $button.data('entry-id') || ''
        }).done(function (response) {
            if (response.success) {
                const $updated = $(response.data.html);
                $panel.find('details[open]').each(function () {
                    const detailClass = this.className;
                    $updated.find('details').filter(function () {
                        return this.className === detailClass;
                    }).prop('open', true);
                });
                if (operation === 'record' || operation === 'reverse') {
                    $updated.find('.advshipping_wpm-collection-history').prop('open', true);
                }
                $panel.replaceWith($updated);
                $updated.find('.advshipping_wpm-collection-feedback').text(response.data.message).trigger('focus');
            } else {
                $feedback.addClass('is-error').text(response.data.message || $panel.data('error-text'));
            }
        }).fail(function (xhr) {
            const error = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
            $feedback.addClass('is-error').text(error || $panel.data('error-text'));
        }).always(function () {
            $panel.find('button').prop('disabled', false);
            $panel.removeAttr('aria-busy');
        });
    });
    $(document).on('keydown', '.advshipping_wpm-collection-fields input', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $(this).closest('.advshipping_wpm-order-payments').find('.advshipping_wpm-record-collection:not(:disabled)').trigger('click');
        }
    });
});
