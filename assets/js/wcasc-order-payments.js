jQuery(function ($) {
    $(document).on('click', '.wcasc-record-collection, .wcasc-reverse-collection, .wcasc-sync-analytics', function () {
        const $button = $(this);
        const $panel = $button.closest('.wcasc-order-payments');
        const operation = $button.hasClass('wcasc-record-collection') ? 'record' : ($button.hasClass('wcasc-reverse-collection') ? 'reverse' : 'sync');
        if (operation === 'reverse' && !window.confirm($panel.data('reverse-confirm'))) return;
        const $feedback = $panel.find('.wcasc-collection-feedback');
        if (operation === 'record' && !$panel.find('.wcasc-collection-amount')[0].reportValidity()) return;
        $panel.find('button').prop('disabled', true);
        $panel.attr('aria-busy', 'true');
        $feedback.removeClass('is-error').text($panel.data('saving-text'));
        $.post(ajaxurl, {
            action: 'wcasc_order_collection',
            order_id: $panel.data('order-id'),
            nonce: $panel.data('nonce'),
            request_id: $panel.data('request-id'),
            operation: operation,
            amount: $panel.find('.wcasc-collection-amount').val(),
            method: $panel.find('.wcasc-collection-method').val(),
            reference: $panel.find('.wcasc-collection-reference').val(),
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
                    $updated.find('.wcasc-collection-history').prop('open', true);
                }
                $panel.replaceWith($updated);
                $updated.find('.wcasc-collection-feedback').text(response.data.message).trigger('focus');
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
    $(document).on('keydown', '.wcasc-collection-fields input', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $(this).closest('.wcasc-order-payments').find('.wcasc-record-collection:not(:disabled)').trigger('click');
        }
    });
});
