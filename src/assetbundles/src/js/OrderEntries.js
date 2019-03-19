(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

/**
 * Order entries class
 */
Craft.Translations.OrderEntries = {
    $checkboxes: null,
    $selectAllCheckbox: null,
    $publishSelectedBtn: null,

    hasSelections: function() {
        return this.$checkboxes.filter(':checked').length > 0;
    },
    toggleSelected: function(toggle) {
        this.$checkboxes.prop('checked', toggle);

        this.togglePublishButton();
    },
    toggleSelectAllCheckbox() {
        this.$selectAllCheckbox.prop(
            'checked',
            this.$checkboxes.filter(':checked').length === this.$checkboxes.length
        );
    },
    togglePublishButton: function() {
        if (this.hasSelections()) {
            this.$publishSelectedBtn.prop('disabled', false).removeClass('disabled');
        } else {
            this.$publishSelectedBtn.prop('disabled', true).addClass('disabled');
        }
    },
    init: function() {
        this.$publishSelectedBtn = $('.translations-publish-selected-btn');
        this.$selectAllCheckbox = $('thead .translations-checkbox-cell :checkbox');
        this.$checkboxes = $('tbody .translations-checkbox-cell :checkbox').not('[disabled]');

        this.$selectAllCheckbox.on('change', function() {
            Craft.Translations.OrderEntries.toggleSelected($(this).is(':checked'));
        });

        this.$checkboxes.on('change', function() {
            Craft.Translations.OrderEntries.togglePublishButton();
            Craft.Translations.OrderEntries.toggleSelectAllCheckbox();
        });
    }
};

$(function() {
    Craft.Translations.OrderEntries.init();
});

})(jQuery);