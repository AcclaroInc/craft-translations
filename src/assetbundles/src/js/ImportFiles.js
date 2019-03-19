(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    /**
     * Order Import Files class
     */
    Craft.Translations.ImportFiles =
    {
        init: function()
        {
            var self = this;

            var $btn = $('#import-tool');
            
            var form = self.buildImportFilesForm();
            var url = Craft.getUrl('translations/orders/importfile');
            
            $btn.on('click',function(){
                var hud = new Garnish.HUD($btn, form, {
                    orientations: ['top', 'bottom', 'right', 'left'],
                    hudClass: 'hud toolhud import',
                });
            return false;
            });
        },

        buildImportFilesForm: function()
        {
            var $form = $('<form>', {
                'method': 'POST',
                'enctype' : 'multipart/form-data',
                'accept-charset' : 'UTF-8',
                'class' : 'form last',
                'action' : '',
                'id' : 'translations-form-import'
            });


            $form.append(Craft.getCsrfInput());

            var $label = $('<label>Select ZIP or XML File to Import</label>');

            $label.appendTo($form);

            var $divFile = $('<div class="input-file"></div>');
            var $file = $('<input>', {    
                'type': 'file',
                'name': 'zip-upload',
            });

            $file.appendTo($divFile);
            $divFile.appendTo($form);

            var $hiddenAction = $('<input>', {
                'type': 'hidden',
                'name': 'action',
                'value': 'translations/files/import-file'
            });

            $hiddenAction.appendTo($form);

            var $hiddenOrderId = $('<input>', {
                'type': 'hidden',
                'name': 'orderId',
                'value': $('#order_id').val()
            });

            $hiddenOrderId.appendTo($form);
            var $div = $('<div class="buttons"></div>');
            var $submit = $('<input>', {
                'type': 'submit',
                'id' : 'submit',
                'class' : 'btn submit',
                'value' : 'Go!'
            });

            $submit.appendTo($div);
            $div.appendTo($form);

            return $form;
        }
    };

    $(function() {
        Craft.Translations.ImportFiles.init();
    });

})(jQuery);

// Craft.Translations.ImportFiles = Garnish.Base.extend(
//     {
//         uploader: null,
//         allowedKinds: null,
//         $element: null,
//         settings: null,

//         init: function($element, settings) {
//             this.$element = $element;
//             this.allowedKinds = null;

//             settings = $.extend({}, Craft.Translations.ImportFiles.defaults, settings);

//             var events = settings.events;
//             delete settings.events;

//             settings.autoUpload = false;

//             this.uploader = this.$element.fileupload(settings);
//             for (var event in events) {
//                 if (!events.hasOwnProperty(event)) {
//                     continue;
//                 }

//                 this.uploader.on(event, events[event]);
//             }

//             this.settings = settings;

//             this.uploader.on('fileuploadadd', $.proxy(this, 'onFileAdd'));
//         },

//         /**
//          * Set uploader parameters.
//          */
//         setParams: function(paramObject) {
//             // If CSRF protection isn't enabled, these won't be defined.
//             if (typeof Craft.csrfTokenName !== 'undefined' && typeof Craft.csrfTokenValue !== 'undefined') {
//                 // Add the CSRF token
//                 paramObject[Craft.csrfTokenName] = Craft.csrfTokenValue;
//             }

//             this.uploader.fileupload('option', {formData: paramObject});
//         },

//         /**
//          * Get the number of uploads in progress.
//          */
//         getInProgress: function() {
//             return this.uploader.fileupload('active');
//         },

//         /**
//          * Called on file add.
//          */
//         onFileAdd: function(e, data) {
//             e.stopPropagation();

//             // Make sure that file API is there before relying on it
//             data.process().done($.proxy(function() {
//                 data.submit();
//             }, this));

//             return true;
//         },

//         destroy: function() {
//             this.$element.fileupload('destroy');
//             this.base();
//         }
//     },

// // Static Properties
// // =============================================================================

//     {
//         defaults: {
//             dropZone: null,
//             pasteZone: null,
//             fileInput: true,
//             sequentialUploads: false,
//             allowedKinds: null,
//             events: {},
//             canAddMoreFiles: false,
//             headers: {'Accept' : 'application/json;q=0.9,*/*;q=0.8'},
//             paramName: 'zip-upload'
//         }
// });