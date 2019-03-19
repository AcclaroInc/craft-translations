(function($) {

if (typeof Craft.TranslationsForCraft === 'undefined') {
    Craft.TranslationsForCraft = {};
}

/**
 * Order entries class
 */
Craft.TranslationsForCraft.OrderEntries = {
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
        this.$publishSelectedBtn = $('.translations-for-craft-publish-selected-btn');
        this.$selectAllCheckbox = $('thead .translations-for-craft-checkbox-cell :checkbox');
        this.$checkboxes = $('tbody .translations-for-craft-checkbox-cell :checkbox').not('[disabled]');

        this.$selectAllCheckbox.on('change', function() {
            Craft.TranslationsForCraft.OrderEntries.toggleSelected($(this).is(':checked'));
        });

        this.$checkboxes.on('change', function() {
            Craft.TranslationsForCraft.OrderEntries.togglePublishButton();
            Craft.TranslationsForCraft.OrderEntries.toggleSelectAllCheckbox();
        });
    }
};

$(function() {
    Craft.TranslationsForCraft.OrderEntries.init();
});

})(jQuery);