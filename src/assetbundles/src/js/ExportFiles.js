(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    /**
     * Order Export Files To Translate
     */
    Craft.Translations.ExportFiles = Garnish.Base.extend(
    {
        $trigger: null,
        $form: null,
        $formId: null,

        init: function(formId, buttonId) {
            this.$formId = formId;
            this.$form = $('#' + formId);
            this.$trigger = $('#export-btn');
            this.$exportBtn = $('#' + buttonId);
            this.$status = this.$exportBtn.find(".utility-status");

            this.addListener(this.$exportBtn, 'click', '_showExportHud');
            this.addListener(this.$form, 'submit', 'onSubmit');
        },

        onSubmit: function(ev) {
            ev.preventDefault();

            if (!this.$trigger.hasClass('processing')) {
                this.$trigger.css('color', '#ced8e5');

                if (!this.progressBar) {
                    this.progressBar = new Craft.ProgressBar(this.$status);
                }
                else {
                    this.progressBar.resetProgressBar();
                }

                this.progressBar.$progressBar.removeClass('hidden');

                this.progressBar.$progressBar.velocity('stop').velocity(
                    {
                        opacity: 1
                    },
                    {
                        complete: $.proxy(function() {
                            var postData = Garnish.getPostData(this.$form),
                                params = Craft.expandPostArray(postData);
                                $data = {'params' : params};

                            Craft.sendActionRequest('POST', params.action, {data: $data})
                                .then((response) => {
                                    this.updateProgressBar();

                                    if (response.data.translatedFiles) {
                                        var $iframe = $('<iframe/>', {'src': Craft.getActionUrl('translations/files/export-file', {'filename': response.data.translatedFiles})}).hide();
                                        this.$form.append($iframe);
                                    }

                                    setTimeout($.proxy(this, 'onComplete'), 300);
                                }).catch(({response}) => {
                                    Craft.cp.displayError(Craft.t('app', response.data.message));

                                    this.onComplete(false);
                                });

                        }, this)
                    });

                this.$trigger.addClass('processing').css('pointer-events', 'none');
                this.$trigger.trigger('blur');
            }
            this.$trigger.css('background-color', '');
        },

        updateProgressBar: function() {
            var width = 100;
            this.progressBar.setProgressPercentage(width);
        },

        onComplete: function(showAllDone = true) {
            this.progressBar.$progressBar.velocity({opacity: 0}, {
                duration: 'fast', complete: $.proxy(function() {

                    this.$trigger.removeClass('processing').css('pointer-events', '');
                    this.$trigger.css('color', '#3f4d5a');
                    this.$trigger.trigger('focus');
                    if (showAllDone) location.reload();
                },
                this)
            });
        },

        _showExportHud: function() {
            this.$exportBtn.addClass('active');

            var $form = $('<form/>', {
                'class': 'export-form'
            });

            var $formatField = Craft.ui.createSelectField({
                label: Craft.t('app', 'Format'),
                options: [
                    {label: 'XML', value: 'xml'}, {label: 'JSON', value: 'json'}, {label: 'CSV', value: 'csv'},
                ],
                'class': 'fullwidth',
            }).appendTo($form);

            let $typeSelect = $formatField.find('select');
            this.addListener($typeSelect, 'change', () => {
                $('<input/>', {
                    'class': 'hidden',
                    'name': 'format',
                    'value': $typeSelect.val()
                }).appendTo(this.$form);
            });
            $typeSelect.trigger('change');

            $download = $('<button/>', {
                type: 'submit',
                'class': 'btn submit fullwidth',
                'form': this.$formId,
                text: Craft.t('app', 'Download')
            }).appendTo($form);

            var hud = new Garnish.HUD(this.$exportBtn, $form);

            this.addListener($download, 'click', () => {
                hud.hide();
            });

            hud.on('hide', $.proxy(function() {
                this.$exportBtn.removeClass('active');
            }, this));
        }
    });

})(jQuery);
