(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.StaticTranslations = {

    saveStaticTranslation: function(element) {
        form = $("#static-translation");
        postData = Garnish.getPostData(form),
        $data = Craft.expandPostArray(postData);
        $data['source'] = Craft.elementIndex.sourceKey;
        $data['siteId'] = Craft.elementIndex.siteId;

        Craft.sendActionRequest('POST', 'translations/static-translations/save', {data: $data})
            .then((response) => {
                Craft.cp.displaySuccess(Craft.t('app', response.data.message));
                Craft.elementIndex.updateElements();
            })
            .catch(({response}) => {
                Craft.cp.displayError(Craft.t('app', response.data.error));
            })
            .finally(() => {
                element.removeClass('link-disabled loading');
            });
    },

    exportStaticTranslation: function() {

        params = {
            siteId: Craft.elementIndex.siteId,
            sourceKey: Craft.elementIndex.sourceKey,
            search: Craft.elementIndex.searchText
        };

        Craft.sendActionRequest('POST', 'translations/static-translations/export', {data: params})
            .then((response) => {
                var $iframe = $('<iframe/>', {'src': Craft.getActionUrl('translations/static-translations/export-file', {'filename': response.data.filePath})}).hide();
                $('#static-translation').append($iframe);
                Craft.cp.displaySuccess(Craft.t('app', 'Static Translations exported.'));
            })
            .catch(({response}) => {
                Craft.cp.displayError(Craft.t('app', response.data.error));
            });

    },

    syncToDB: function (element) { 
        params = {
            siteId: Craft.elementIndex.siteId,
            sourceKey: Craft.elementIndex.sourceKey
        };

        Craft.sendActionRequest('POST', 'translations/static-translations/sync', {data: params})
            .then((response) => {
                Craft.cp.displaySuccess(Craft.t('app', response.data.message));
            })
            .catch(({response}) => {
                Craft.cp.displayError(Craft.t('app', response.data.error));
            })
            .finally(() => {
                element.removeClass('link-disabled loading');
            });
    },

    init: function() {
        var self = this;
        var syncToDatabaseButton = $('#sync-static-translation');
        var saveStaticTranslations = $('#save-static-translation');

        $('.sortmenubtn').hide();
        $('.statusmenubtn').hide();
        $(".sitemenubtn").appendTo("#toolbar");

        saveStaticTranslations.on('click', function(e) {
            saveStaticTranslations.addClass('link-disabled loading');

            e.preventDefault();
            self.saveStaticTranslation(saveStaticTranslations);
        });

        syncToDatabaseButton.on('click', function(e) {
            syncToDatabaseButton.addClass('link-disabled loading');
            e.preventDefault();
            self.syncToDB(syncToDatabaseButton);
        });

        $('#translate-export').on('click', function(e) {

            e.preventDefault();
            if($(".elements table:first tr").length > 1) {
                self.exportStaticTranslation();
            } else {
                Craft.cp.displayNotice(Craft.t('app', 'No Translations to export.'));
            }
        });

        $('.translate-import').click(function() {

            $('input[name="trans-import"]').click().change(function() {
                $('#siteId').val(Craft.elementIndex.siteId);
                $(this).parent('form').submit();
            });

        });
    },
};

})(jQuery);