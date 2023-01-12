(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.TranslatorDetail = {
    updateService: function() {
        var service = this.getService();
        var apiToken = $('#settings-apiToken');
        var sandboxMode = $('#settings-sandboxMode');

        switch (service) {
            case 'acclaro':
                this.updateServiceTokenLabel(service);
                apiToken.removeClass('hidden');
                sandboxMode.removeClass('hidden');
                break;
            case 'google':
                this.updateServiceTokenLabel(service);
                apiToken.removeClass('hidden');
                sandboxMode.addClass('hidden');
                break;
            default:
                apiToken.addClass('hidden');
                sandboxMode.addClass('hidden');
        }
    },

    getToken: function () {
        return $('input[name="settings[apiToken]"]').val();
    },

    getService: function () {
        return $('#service').val();
    },

    updateServiceTokenLabel: function (service) {
        let label = $('.translations-translator-form').data('settings')[service];
        $('#settings-apiToken').find('div.heading label').html(label);
    },

    validate: function() {
        var $service = $('#service');
        var service = $service.val();
        var serviceValid = !!service;
        var valid = serviceValid;

        this.toggleInputState($service, serviceValid, Craft.t('app', 'Please choose a translation service.'));

        switch (service) {
            case 'export_import':
                break;
            default:
                var $apiToken = $('input[name="settings[apiToken]"]');
                var apiTokenValid = $apiToken.val() !== '';
                this.toggleInputState($apiToken, apiTokenValid, Craft.t('app', `Please enter your ${$service.find('option:selected').text()} API token.`));
                valid = valid && apiTokenValid
                break;
        }

        return valid;
    },

    validateFormFilled: function() {
        hasTitle = $('#label').val() != "";
        hasService = this.getService() !== "";
        hasToken = this.getToken() !== "";
        
        switch (this.getService()) {
            case 'acclaro':
            case 'google':
                return hasToken && hasService && hasTitle;
            case 'export_import':
                return hasTitle;
            default:
                return false;
        }
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
                .append($('<li>', {'html': message}));
        }
    },

    isEditTranslatorScreen: function() {
        return $('form input[type=hidden][name=id]').length > 0;
    },

    isDefaultTranslator: function() {
        return $('form #service').val() == "export_import" || $('form #service').val() == "";
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

    init: function() {
        var self = this;

        if (this.isEditTranslatorScreen()) {
            this._initSaveButtonContainer();
            this.toggleSaveButton();
        }

        $('#label, input.api-token-field').on('keyup', function() {
            self.toggleSaveButton();
        });
        
        $('#service').on('change', function() {
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

    _initSaveButtonContainer: function() {
        var $btngroup = $('<div>', {
            id: "save-button-container",
            class: "btngroup submit"
        });
        $btngroup.appendTo('#action-buttons');

        $saveText = "Create translator";
        $continueText = "Create and add another";

        if (this.isExistingTranslator()) {
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
