(function($) {
    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    Craft.Translations.CustomExporters = Garnish.Base.extend({

        $exportBtn: null,
        $search: null,
        siteId: null,
        $toolbar: null,
        $elementType: null,
        $action: null,

        init: function(buttonId) {
            this.$exportBtn = $('#' + buttonId);
            this.siteId = Craft.siteId;
            this.$toolbar = $('#toolbar');
            this.$search = this.$toolbar.find('.search:first input:first');
            this.$elementType = "acclaro\\translations\\elements\\Order";
            this.action = "translations/export/export-files";

            this.addListener(this.$exportBtn, 'click', '_showExportHud');
        },

        getViewParams: function() {

            var criteria = {
                siteId: this.siteId,
                search: this.$search.val(),
            };

            var params = {
                criteria: criteria,
                elementType: this.$elementType,
                action: this.action,
            };

            return params;
        },

        _showExportHud: function() {
            this.$exportBtn.addClass('active');

            var $form = $('<form/>', {
                'class': 'export-form'
            });

            var typeOptions = [
                {"label": "Raw data (fastest)", "value": "Raw"},
                {"label": "Expanded", "value": "Expanded"},
            ];
            
            var $typeField = Craft.ui.createSelectField({
                label: Craft.t('app', 'Export Type'),
                options: typeOptions,
                'class': 'fullwidth',
            }).appendTo($form);

            var $formatField = Craft.ui.createSelectField({
                label: Craft.t('app', 'Format'),
                options: [
                    {label: 'CSV', value: 'csv'}, {label: 'JSON', value: 'json'}, {label: 'XML', value: 'xml'},
                ],
                'class': 'fullwidth',
            }).appendTo($form);

            // Not currently used
            var $limitField = Craft.ui.createTextField({
                label: Craft.t('app', 'Limit'),
                placeholder: Craft.t('app', 'No limit'),
                type: 'number',
                min: 1
            }).appendTo($form);

            $('<button/>', {
                type: 'submit',
                'class': 'btn submit fullwidth',
                text: Craft.t('app', 'Export')
            }).appendTo($form)

            var $spinner = $('<div/>', {
                'class': 'spinner hidden'
            }).appendTo($form);

            var hud = new Garnish.HUD(this.$exportBtn, $form);

            hud.on('hide', $.proxy(function() {
                this.$exportBtn.removeClass('active');
            }, this));

            var submitting = false;

            this.addListener($form, 'submit', function(ev) {
                ev.preventDefault();

                if (submitting) {
                    return;
                }
    
                submitting = true;
                $spinner.removeClass('hidden');
                var params = this.getViewParams();

                params.type = $typeField.find('select').val();
                params.format = $formatField.find('select').val();

                if (Craft.csrfTokenValue) {
                    params[Craft.csrfTokenName] = Craft.csrfTokenValue;
                }

                Craft.downloadFromUrl('POST', Craft.getActionUrl(params.action), params)
                .then(function() {
                    submitting = false;
                    $spinner.addClass('hidden');
                })
                .catch(function() {
                    submitting = false;
                    Craft.cp.displayError(
                        Craft.t('app', 'There was a problem downloading your files.')
                    );
                    $spinner.addClass('hidden');
                });
            });
        },
    });
}) (jQuery);