(function($) {

if (typeof Craft.TranslationsForCraft === 'undefined') {
    Craft.TranslationsForCraft = {};
}

Craft.TranslationsForCraft.TranslatorDetail = {
    updateService: function() {
        var service = $('#service').val();

        if (service != 'acclaro') {
            $('.translations-for-craft-translator-settings').hide();
            $('.translations-for-craft-translator-settings-header').hide();
        } else {
            var $settings = $('#settings-'+service);

            $settings.show();
            $('.translations-for-craft-translator-settings').not($settings).hide();
            $('.translations-for-craft-translator-settings-header').show();
        }
    },

    validate: function() {
        var $service = $('#service');
        var service = $service.val();
        var serviceValid = !!service;
        var $sites = $(':checkbox[name="sites[]"]');
        var sitesValid = $sites.filter(':checked').length > 0;
        var valid = serviceValid && sitesValid;

        this.toggleInputState($service, serviceValid, Craft.t('app', 'Please choose a translation service.'));
        this.toggleInputState($sites, sitesValid, Craft.t('app', 'Please choose one or more sites.'));

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

    checkSelectedSites: function(sites) {
        sites = JSON.parse(sites);
        var $checkboxes = $(':checkbox[name="sites[]"]');
        $checkboxes.each(function(){
            var $el = $(this);
            var value = $el.val();
            if (sites.includes(value)) {
                $el[0].defaultChecked = true;
            }
        });

    },

    serializeSettings: function(service) {
        var data = {};

        var $fields = $(':input[name^="settings['+service+']"]').not(':checkbox:not(:checked), :radio:not(:checked)');

        $fields.each(function() {
            var $el = $(this);
            var regExp = new RegExp('^settings\\['+service+'\\]\\[(.*?)\\]$');
            var name = $el.attr('name').replace(regExp, '$1');
            var value = $el.val();
            var multiple = /\]\[$/.test(name);

            if (multiple) {
                name = name.substr(0, name.length - 2);

                if (typeof data[name] === 'undefined') {
                    data[name] = [];
                }

                if ($.isArray(value)) {
                    $.each(value, function(i, v) {
                        data[name].push(v);
                    });
                } else {
                    data[name].push(value);
                }
            } else {
                data[name] = value;
            }
        });

        return data;
    },

    authenticateTranslationService: function() {
        var service = $('#service').val();
        var settings = this.serializeSettings(service);

        $.post(
            location.href,
            {
                CRAFT_CSRF_TOKEN: Craft.csrfTokenValue,
                action: 'translations-for-craft/base/authenticate-translation-service',
                service: service,
                settings: settings
            },
            function(data) {
                if (data.success) {
                    $('#status').val('active');
                    Craft.cp.displayNotice(Craft.t('app', 'You are now authenticated!'));
                } else {
                    $('#status').val('inactive');
                    Craft.cp.displayError(Craft.t('app', 'Invalid API token.'));
                }
            },
            'json'
        );
    },

    init: function() {
        var self = this;

        $('#service').on('change', $.proxy(this.updateService, this));

        this.updateService();
        
        var sites = selectedSites;
        if (sites !== '') {
            this.checkSelectedSites(sites);
        }
        

        $('.translations-for-craft-authenticate-translation-service').on('click', function(e) {
            e.preventDefault();

            self.authenticateTranslationService();
        });

        $('.translations-for-craft-translator-form').on('submit', function(e) {
            if (!self.validate()) {
                e.preventDefault();
            }
        });
    }
};

$(function() {
    Craft.TranslationsForCraft.TranslatorDetail.init();
});

})(jQuery);