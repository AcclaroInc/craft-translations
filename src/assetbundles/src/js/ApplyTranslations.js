(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.ApplyTranslations = {
    init: function(draftId) {
        // Disable default Craft updating
        window.draftEditor.settings.canUpdateSource = false;
        $("#main-form input[type='submit']").disable();
        $("#main-form input[type='submit']").attr('disabled', true);
        
        this.draftId = draftId;

        var draftData = {
            draftId: this.draftId,
        };

        Craft.postActionRequest('translations/files/is-translation-draft', draftData, function(response, textStatus) {
            if (textStatus === 'success' && response.success) {
                console.log(response.data);
                /**
                 * TODO:
                 * 1. Need to prevent form submission for "⌘+s"
                 * 2. Need to set up actionApplyTranslations
                 * 3. Need to add more form validation
                 */

                // Let's completely remove the button and add Apply Translations in column sidebar
                // Would be nice to determine if it's one of our drafts on page load vs w/ js

                var $applyTranslationsContainer = document.createElement('div');
                    $applyTranslationsContainer.id = "apply-translations";
                    $applyTranslationsContainer.className = "field";
                
                var $btngroup = $('<div>', {'class': 'apply-translations'}).css('float', 'right');

                $settings = document.getElementById('settings');
                $settings.insertBefore($applyTranslationsContainer, $settings.firstChild);
                var $headinggroup = $('<div>', {'class': 'heading'}).html('<label id="translations-label" for="translations">Translations</label>');
                var $inputgroup = $('<div>', {'class': 'input ltr'});
                
                $headinggroup.appendTo($applyTranslationsContainer);
                $inputgroup.appendTo($applyTranslationsContainer);
                $btngroup.appendTo($inputgroup);

                this.$btn = $('<a>', {
                    'class': 'btn submit',
                    'href': '',
                    'text': Craft.t('app', 'Apply Translations')
                });

                $(this.$btn).disable();
                $(this.$btn).attr('disabled', true);
                $(this.$btn).on("click", function (e) {
                    e.preventDefault();
                });

                this.$btn.appendTo($btngroup);

                // Check to make sure we're in the right target site
                // if (response.data.targetSite == window.draftEditor.settings.siteId && response.data.file.status == 'complete') {
                if (response.data.targetSite == window.draftEditor.settings.siteId) {
                    // reactivate the button
                    $(this.$btn).enable();
                    $(this.$btn).attr('disabled', false);
                    $(this.$btn).on("click", function (e) {
                        e.preventDefault();
                        
                        var fileData = {
                            fileId: response.data.file,
                        };

                        Craft.postActionRequest('translations/files/apply-translation-draft', fileData, function(response, textStatus) {
                            if (textStatus === 'success' && response.success) {
                                console.log(response.data);
                            } else {
                                console.log(response);                
                            }
                        });
                    });
                }
                
            } else {
                console.log(response);
                // Not a translation draft
                window.draftEditor.settings.canUpdateSource = true;
                $("#main-form input[type='submit']").enable();
                $("#main-form input[type='submit']").attr('disabled', false);
            }
        });
    },
};

})(jQuery);