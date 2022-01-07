(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    /**
     * Order Import Files class
     */
    Craft.Translations.ImportFiles =
    {
        $importBtn: null,

        init: function()
        {
            var self = this;
            var buttonId = 'import-tool';
            this.$importBtn = $('#'+buttonId);
            this.$status = this.$importBtn.find(".utility-status");

            this.$importBtn.on('click',function(){
                self._showImportHud();
            });
        },
		getChangedEntriesId: function() {
			$result = [];
			$entries = $('input[type=hidden][name="elements[]"]');
			$entries.each(function() {
				if ($(this).closest('tr').data('is-updated') == 1) {
					$result.push($(this).val());
				}
			});

			return $result.join(',');
		},
        _showImportHud: function()
        {
            var self = this;
            this.$importBtn.addClass('active');
            var $form = $('<form/>', {
                'method': 'POST',
                'enctype' : 'multipart/form-data',
                'accept-charset' : 'UTF-8',
                'class' : 'form last export-form',
                'action' : '',
                'id' : 'translations-form-import'
            });

            $form.append(Craft.getCsrfInput());

            var $label = $('<div class="mb-1"><label>Supported file formats<br>[ ZIP, XML, JSON, CSV ]</label></div>');

            $label.appendTo($form);

            $label = $('<label for="import-formId"><strong>Select File to Import</strong></label>');
            $label.appendTo($form);

            var $divFile = $('<div class="input-file"></div>');
            var $file = $('<input/>', {
                'type': 'file',
                'name': 'zip-upload',
                'id': 'import-formId'
            });

            $file.css({
                'width': '100%',
                'text-overflow': 'clip',
                'overflow': 'hidden'
            });

            $file.appendTo($divFile);
            $divFile.appendTo($form);

            var $hiddenAction = $('<input/>', {
                'type': 'hidden',
                'name': 'action',
                'value': 'translations/files/import-file'
            });
            $hiddenAction.appendTo($form);

            var $hiddenOrderId = $('<input/>', {
                'type': 'hidden',
                'name': 'orderId',
                'value': $('#order_id').val()
            });
            $hiddenOrderId.appendTo($form);

            var $hiddenField = $('<input/>', {
                'type': 'hidden',
                'name': 'isProcessing',
                'value': '1'
            });
            $hiddenField.appendTo($form);

            var $hiddenElements = $('<input/>', {
                'type': 'hidden',
                'name': 'elements',
                'value': self.getChangedEntriesId()
            });
            $hiddenElements.appendTo($form);

            var $div = $('<div class="buttons"></div>');
            var $submit = $('<input>', {
                'type': 'submit',
                'id' : 'submit',
                'class' : 'btn submit fullwidth',
                'value' : 'Upload'
            });

            $submit.appendTo($div);
            $div.appendTo($form);

            var hud = new Garnish.HUD(this.$importBtn, $form, {
                orientations: ['top', 'bottom', 'right', 'left'],
                hudClass: 'hud toolhud import',
            });

            $submit.on('click', function() {
                hud.hide();
                self._showProgressBar();
            });

            hud.on('hide', $.proxy(function() {
                this.$importBtn.removeClass('active');
            }, this));
        },
        _showProgressBar: function() {
            if (!this.$importBtn.hasClass('processing')) {
                this.$importBtn.addClass('processing').css('pointer-events', 'none');
                this.$importBtn.css('color', '#ced8e5');
                if (!this.progressBar) {
                    this.progressBar = new Craft.ProgressBar(this.$status);
                }
                else {
                    this.progressBar.resetProgressBar();
                    this.$importBtn.css('color', '#3f4d5a');
                }

                this.progressBar.$progressBar.removeClass('hidden');
                this.progressBar.$progressBar.velocity('stop').velocity(
                    {
                        opacity: 1
                    },
                    {
                        complete: $.proxy(function() {}, this)
                    });
            }
        }
    };

    $(function() {
        Craft.Translations.ImportFiles.init();
    });

})(jQuery);
