(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.StaticTranslations = {

    saveStaticTranslation: function() {

        var data = $("#static-translation").serializeArray();
        data.push(
            {name: 'source', value: Craft.elementIndex.sourceKey},
            {name: 'siteId', value: Craft.elementIndex.siteId}
            );
        Craft.postActionRequest('translations/static-translations/save', data, $.proxy(function(response, textStatus) {
            if (textStatus === 'success') {
                if (response.success) {
                    Craft.cp.displayNotice(Craft.t('app', 'Static Translations saved.'));
                    Craft.elementIndex.updateElements();
                }
            } else {
                Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
            }

            $('.save-static-translation').removeClass('disabled');
            $('.save-static-translation').attr("disabled", false);

        }, this));


    },

    exportStaticTranslation: function() {

        var data = {
            siteId: Craft.elementIndex.siteId,
            sourceKey: Craft.elementIndex.sourceKey,
            search: Craft.elementIndex.searchText
        };

        Craft.postActionRequest('translations/static-translations/export', data, $.proxy(function(response, textStatus) {
            if (textStatus === 'success') {
                if (response.success) {
                    var $iframe = $('<iframe/>', {'src': Craft.getActionUrl('translations/static-translations/export-file', {'filename': response.filePath})}).hide();
                    $('#static-translation').append($iframe);
                    Craft.cp.displayNotice(Craft.t('app', 'Static Translations exported.'));
                }
            } else {
                Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
            }
        }, this));

    },

    init: function() {
        var self = this;
        $('.sortmenubtn').hide();
        $('.statusmenubtn').hide();
        $(".sitemenubtn").appendTo("#toolbar");

        $('.save-static-translation').on('click', function(e) {

            $('.save-static-translation').addClass('disabled');
            $('.save-static-translation').attr("disabled", true);

            e.preventDefault();
            console.log(Craft.elementIndex);
            self.saveStaticTranslation();
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