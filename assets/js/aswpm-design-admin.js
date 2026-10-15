jQuery(function ($) {
    const $preview = $('.aswpm-design-preview #aswpm-notice-wrap');
    const $fields = $('[data-design-key]');
    if (!$preview.length) return;

    function updatePreview() {
        const values = {};
        $fields.each(function () {
            const key = $(this).data('design-key');
            if (key.endsWith('_color')) {
                const value = $(this).val();
                values[key] = /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(value) ? value : $(this).data('default-color');
            } else if (key === 'border_style') {
                values[key] = $(this).val();
            } else {
                values[key] = Math.max(0, Math.min(Number(this.max), Number($(this).val()) || 0));
            }
        });
        const variables = { primary_color: 'primary', background_color: 'background', border_color: 'border-color', text_color: 'text', heading_color: 'heading', muted_color: 'muted' };
        Object.keys(variables).forEach(function (key) {
            $preview[0].style.setProperty('--aswpm-' + variables[key], values[key]);
        });
        ['border_width', 'border_radius'].forEach(function (key) {
            $preview[0].style.setProperty('--aswpm-' + key.replace('_', '-'), values[key] + 'px');
        });
        $preview[0].style.setProperty('--aswpm-border-style', values.border_style);
        ['padding', 'margin'].forEach(function (type) {
            $preview[0].style.setProperty('--aswpm-' + type, ['top', 'right', 'bottom', 'left'].map(function (side) {
                return values[type + '_' + side] + 'px';
            }).join(' '));
        });
    }

    $('.aswpm-color-picker').each(function () {
        const $field = $(this);
        $field.wpColorPicker({
            change: function (event, ui) { $field.val(ui.color.toString()); updatePreview(); },
            clear: function () { $field.val(''); updatePreview(); }
        });
    });
    $fields.on('input change', updatePreview);
    updatePreview();
    if (window.wp && wp.codeEditor && window.aswpmDesignEditor && aswpmDesignEditor.settings) {
        wp.codeEditor.initialize('aswpm-custom-css', aswpmDesignEditor.settings);
    }
});
