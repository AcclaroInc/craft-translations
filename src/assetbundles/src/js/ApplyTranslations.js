(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.ApplyTranslations = {
    init: function(draftId, file) {
        // Disable default Craft updating
        window.draftEditor.settings.canUpdateSource = false;
        $("#publish-changes-btn-container :input[type='button']").disable();
        $("#publish-changes-btn-container :input[type='button']").attr('disabled', true);

        $("#publish-draft-btn-container :input[type='button']").disable();
        $("#publish-draft-btn-container :input[type='button']").attr('disabled', true);
        
        // Create the Apply Translations button
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

        // Check to make sure we're in the right target site and the draft is ready to be applied
        if (file.targetSite == window.draftEditor.settings.siteId && file.status == 'complete') {
            // reactivate the button
            $(this.$btn).enable();
            $(this.$btn).attr('disabled', false);

            $(this.$btn).one("click", function (e) {
                e.preventDefault();
                $(".apply-translations > a").addClass("disabled");
                $(".apply-translations > a").html("");
                $(".apply-translations .submit").width(122);
                $(".apply-translations > a").toggleClass("spinner");
                
                var fileData = {
                    fileId: file.id,
                };
                
                Craft.postActionRequest('translations/files/apply-translation-draft', fileData, function(response, textStatus) {
                    if (textStatus === 'success' && response.success) {
                        console.log(response.data);
                        Craft.cp.displayNotice(Craft.t('app', 'Translations applied.'));
                        window.location.reload();
                    } else {
                        Craft.cp.displayNotice(Craft.t('app', 'Couldn’t apply translations.'));
                        window.location.reload();
                    }
                });
            });
        }
    },
};

})(jQuery);
