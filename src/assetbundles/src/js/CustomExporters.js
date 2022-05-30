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
        $sidebar: null,

        init: function(buttonId) {
            this.$exportBtn = $('#' + buttonId);
            this.siteId = Craft.siteId;
            this.$toolbar = $('#toolbar');
            this.$search = this.$toolbar.find('.search:first input:first');
            this.$elementType = "acclaro\\translations\\elements\\Order";
            this.action = "translations/export/export-files";
            this.$sidebar = $('#sidebar');

            this.addListener(this.$exportBtn, 'click', '_showExportHud');
        },

        hasSelections: function() {
            return  $('#content tbody tr.sel').length > 0;
        },

        getOrders: function($selected = false) {
            if ($selected) {
                return $('#content tbody tr.sel');
            } else {
                return $('#content tbody tr');
            }
        },

        getOrderIds: function($selected = false) {
            var $ids = '';
            $orders = this.getOrders($selected);

            $orders.each(function() {
                $ids += String($(this).data('id')) + ',';
            });

            return $ids.replace(/,\s*$/, "");
        },

        isTrashed: function () {
            return this.$toolbar.find('.statusmenubtn').text() === 'Trashed' || this.$toolbar.find('form#craft-elements-actions-Restore-actiontrigger').length > 0;
        },

        getViewParams: function() {

            var criteria = {
                siteId: this.siteId,
                search: this.$search.val(),
            };

            if (this.$sidebar.find('.sel').data('key') != 'all') {
                $status = this.$sidebar.find('.sel').data('key');
                criteria['status'] = $status.replace(/-/g, ' ');
            }

            if (this.isTrashed()) {
                criteria['trashed'] = true;
            }

            var params = {
                criteria: criteria,
                elementType: this.$elementType,
                action: this.action,
            };

            if (this.hasSelections()) {
                params['orderIds'] = this.getOrderIds(true);
            }

            if (Craft.csrfTokenValue) {
                params[Craft.csrfTokenName] = Craft.csrfTokenValue;
            }

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

            $exportButton = $('<button/>', {
                type: 'submit',
                'class': 'btn submit fullwidth',
                text: Craft.t('app', 'Export')
            });
            $exportButton.appendTo($form);

            var $spinner = $('<div/>', {
                'class': 'spinner hidden'
            }).appendTo($form);

            var hud = new Garnish.HUD(this.$exportBtn, $form);

            this.addListener($exportButton, 'click', () => {
                hud.hide();
            });

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

                Craft.downloadFromUrl('POST', Craft.getActionUrl(params.action), params)
                .then(() => {})
                .catch(() => {
                    Craft.cp.displayError(
                        Craft.t('app', 'There was a problem downloading your files.')
                        );
                    })
                .finally(() => {
                    submitting = false;
                    $spinner.addClass('hidden');
                });
            });
        },
    });
}) (jQuery);