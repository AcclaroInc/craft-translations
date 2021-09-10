(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.TranslatorDetail = {
    updateService: function() {
        var service = $('#service').val();

        if (service != 'acclaro') {
            $('.translations-translator-settings').hide();
            $('.translations-translator-settings-header').hide();
        } else {
            var $settings = $('#settings-'+service);
            
            $settings.show();
            $('.translations-translator-settings').not($settings).hide();
            $('.translations-translator-settings-header').show();
        }
    },

    validate: function() {
        var $service = $('#service');
        var service = $service.val();
        var serviceValid = !!service;
        var valid = serviceValid;

        this.toggleInputState($service, serviceValid, Craft.t('app', 'Please choose a translation service.'));

        switch (service) {
            case 'acclaro':
                var $apiToken = $('input[name="settings[acclaro][apiToken]"]');
                var apiTokenValid = $apiToken.val() !== '';
                this.toggleInputState($apiToken, apiTokenValid, Craft.t('app', 'Please enter your Acclaro API token.'));
                valid = valid && apiTokenValid
                break;
        }

        return valid;
    },

    validateFormFilled: function() {
        hasService = $("#service").val() != "";
        hasTitle = $('#label').val() != "";

        if (! hasService || ! hasTitle) {
            return false;
        }

        if (! this.isDefaultTranslator()) {
            if (! this.hasApiToken()) {
                return false;
            }
        }

        return true;
    },

    toggleInputState: function(el, valid, message) {
        var $el = $(el);
        var $input = $el.closest('.input');
        var $errors = $input.find('ul.errors');

        $el.toggleClass('error', !valid);
        $input.toggleClass('errors', !valid);

        if (valid) {
            $errors.remove();
        } else if ($errors.length === 0) {
            $('<ul>', {'class': 'errors'})
                .appendTo($input)
                .append($('<li>', {'text': message}));
        }
    },

    hasApiToken: function() {
        return $('input[name="settings[acclaro][apiToken]"]').val() != "";
    },

    isEditTranslatorScreen: function() {
        return $('form input[type=hidden][name=id]').length > 0;
    },

    isDefaultTranslator: function() {
        return $('form #service').val() == "export_import" || $('form #service').val() == "";
    },

    toggleGreenTick: function() {
        // !NOTE: to be used with draft functionality
        // if (this.validateFormFilled()) {
        //     $pageTitle = $("#page-title");
        //     if ($pageTitle.find("span").length == 0) {
        //         $('<span>', {class: "checkmark-icon"}).appendTo($pageTitle);
        //     } else {
        //         $pageTitle.find("span").addClass("checkmark-icon");
        //     }
        // } else {
        //     $pageTitle = $("#page-title");
        //     $pageTitle.find("span").removeClass("checkmark-icon");
        // }
    },

    toggleSaveButton: function() {
        $button = $("#save-button-container .btn");
        if (this.validateFormFilled()) {
            $button.prop("disabled", false);
            $button.removeClass("disabled");
        } else {
            $button.prop("disabled", true);
            $button.addClass("disabled");
        }
    },

    isExistingTranslator: function() {
        return $("input[name=id]").val() !== '';
    },

    getButtonText: function() {
        $text = "create";
        if (this.isExistingTranslator()) {
            $text = "save";
        }
        return $text;
    },

    init: function() {
        var self = this;

        if (this.isEditTranslatorScreen()) {
            this._initSaveButtonContainer(this.getButtonText());
            this.toggleSaveButton();
            // !NOTE: to be used with draft functionality
            // if (this.validateFormFilled()) {
            //     $pageTitle = $("#page-title");
            //     $('<span>', {class: "checkmark-icon"}).appendTo($pageTitle);
            // }
        }

        $('#label, input[name="settings[acclaro][apiToken]"').on('keyup', function() {
            self.toggleGreenTick();
            self.toggleSaveButton();
        });
        
        $('#service').on('change', function() {
            self.toggleGreenTick();
            self.toggleSaveButton();
            self.updateService();
        });

        this.updateService();

        $('.translations-translator-form').on('submit', function(e) {
            if (!self.validate()) {
                e.preventDefault();
            }
        });
    },
    _initSaveButtonContainer: function(action) {
        var $btngroup = $('<div>', {
            id: "save-button-container",
            class: "btngroup submit"
        });
        $btngroup.appendTo('#action-button');

        $saveText = "Create translator";
        $continueText = "Create and add another";

        if (action == "save") {
            $saveText = "Save translator";
            $continueText = "Save and add another";
        }

        $btn = $('<button>', {
            'class': 'btn submit disabled',
            'href': '#',
            type: "submit",
            text: $saveText,
            id: "saveBtn",
            disabled: "disabled"
        });

        $menubtn = $('<button>', {
            'class': 'btn submit menubtn disabled',
            type: "button",
            disabled: "disabled"
        });

        $btn.appendTo($btngroup);

        $menubtn.appendTo($btngroup);

        $menubtn.on('click', function(e) {
            e.preventDefault();
        });

        var $menu = $('<div>', {'class': 'menu'});

        $menu.appendTo($btngroup);

        var $dropdown = $('<ul>', {'class': ''});

        $dropdown.appendTo($menu);

        var $saveContinue = $('<li>');

        $saveContinue.appendTo($dropdown);

        var $link = $('<a>', {
            'href': '#',
            'text': $continueText,
            class: "continueBtn"
        });

        $link.appendTo($saveContinue);

        $menubtn.menubtn();

        $link.on('click', function(e) {
            e.preventDefault();

            var $form = $('.translations-translator-form');
            $form.find('input[name=flow]').val("continue");

            $form.submit();
        });

        $btn.on('click', function(e) {
            e.preventDefault();

            $('.translations-translator-form').submit();
        });
    }
};

$(function() {
    Craft.Translations.TranslatorDetail.init();
});

})(jQuery);
