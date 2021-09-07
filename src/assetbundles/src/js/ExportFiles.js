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
            this.$trigger = $('input.submit', this.$form);
            this.$status = $('.utility-status', this.$form);
            this.$exportBtn = $('#' + buttonId);

            this.addListener(this.$exportBtn, 'click', '_showExportHud');
            this.addListener(this.$form, 'submit', 'onSubmit');
        },

        onSubmit: function(ev) {
            ev.preventDefault();

            if (!this.$trigger.hasClass('disabled')) {
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

                            var data = {
                                params: params
                            };

                            Craft.postActionRequest(params.action, data, $.proxy(function(response, textStatus) {
                                    if(textStatus === 'success')
                                    {
                                        if (response && response.error) {
                                            alert(response.error);
                                        }

                                        this.updateProgressBar();

                                        if (response && response.translatedFiles) {
                                            var $iframe = $('<iframe/>', {'src': Craft.getActionUrl('translations/files/export-file', {'filename': response.translatedFiles})}).hide();
                                            this.$form.append($iframe);
                                        }

                                        setTimeout($.proxy(this, 'onComplete'), 300);
                                    }
                                    else
                                    {
                                        Craft.cp.displayError(Craft.t('app', 'There was a problem exporting your file.'));

                                        this.onComplete(false);
                                    }

                                }, this),
                                {
                                    complete: $.noop
                                });

                        }, this)
                    });

                if (this.$allDone) {
                    this.$allDone.css('opacity', 0);
                }

                this.$trigger.addClass('disabled');
                this.$trigger.trigger('blur');
            }
        },

        updateProgressBar: function() {
            var width = 100;
            this.progressBar.setProgressPercentage(width);
        },

        onComplete: function(showAllDone) {
            this.progressBar.$progressBar.velocity({opacity: 0}, {
                duration: 'fast', complete: $.proxy(function() {

                    this.$trigger.removeClass('disabled');
                    this.$trigger.trigger('focus');
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
                    {label: 'JSON', value: 'json'}, {label: 'CSV', value: 'csv'}, {label: 'XML', value: 'xml'},
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
    
            $('<button/>', {
                type: 'submit',
                'class': 'btn submit fullwidth',
                'form': this.$formId,
                text: Craft.t('app', 'Download')
            }).appendTo($form);
    
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
                // if (submitting) {
                //     return;
                // }
    
                // submitting = true;
                // $spinner.removeClass('hidden');
    
                // var params = this.getViewParams();
                // delete params.criteria.offset;
                // delete params.criteria.limit;
    
                // params.type = $typeField.find('select').val();
                // params.format = $formatField.find('select').val();
    
                // if (selectedElementIds.length) {
                //     params.criteria.id = selectedElementIds;
                // } else {
                //     var limit = parseInt($limitField.find('input').val());
                //     if (limit && !isNaN(limit)) {
                //         params.criteria.limit = limit;
                //     }
                // }
    
                // if (Craft.csrfTokenValue) {
                //     params[Craft.csrfTokenName] = Craft.csrfTokenValue;
                // }
    
                // Craft.downloadFromUrl('POST', Craft.getActionUrl('element-indexes/export'), params)
                //     .then(function() {
                //         submitting = false;
                //         $spinner.addClass('hidden');
                //     })
                //     .catch(function() {
                //         submitting = false;
                //         $spinner.addClass('hidden');
                //         if (!this._ignoreFailedRequest) {
                //             Craft.cp.displayError(Craft.t('app', 'A server error occurred.'));
                //         }
                //     });
            });
        }
    });

})(jQuery);