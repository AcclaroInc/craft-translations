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
        this.$publishSelectedBtn = $('#draft-publish');
        this.$formId = 'publish-form';
        this.$form = $('#' + this.$formId);
        this.$selectAllCheckbox = $('thead .translations-checkbox-cell :checkbox');
        this.$checkboxes = $('tbody .translations-checkbox-cell :checkbox').not('[disabled]');

        this.$selectAllCheckbox.on('change', function() {
            Craft.Translations.OrderEntries.toggleSelected($(this).is(':checked'));
        });

        this.$checkboxes.on('change', function() {
            Craft.Translations.OrderEntries.togglePublishButton();
            Craft.Translations.OrderEntries.toggleSelectAllCheckbox();
        });

        var form = this._buildExportHud();
        
        this.$publishSelectedBtn.on('click', function (e) {
            $(this).addClass("disabled");
            $(this).width(130);
            $(this).toggleClass("spinner");

            var hud = new Garnish.HUD($(this), form);

            hud.on('hide', $.proxy(function() {
                $(this).removeClass("disabled");
                $(this).toggleClass("spinner");
            }, this));
        });

        this.$form.on('submit', function (e) {
            // alert('worked');
        });
    },

    _buildExportHud: function() {
        var $form = $('<div/>', {
            'class' : 'export-form',
        });

        var $draft = $('<button/>', {
            type: 'submit',
            name : 'submit',
            form: this.$formId,
            class : 'btn submit fullwidth mb-1 p-0',
            value : 'draft',
            text: Craft.t('app', 'Create drafts')
        });

        $draft.appendTo($form);

        var $publish = $('<button/>', {
            type: 'submit',
            name : 'submit',
            form: this.$formId,
            class : 'btn submit fullwidth p-0',
            value : 'publish',
            text: Craft.t('app', 'Create drafts and publish')
        });

        $publish.appendTo($form);

        return $form;
    }
};

$(function() {
    Craft.Translations.OrderEntries.init();
});

})(jQuery);