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

    init: function() {
        var self = this;
        $('.sortmenubtn').hide();
        $('.statusmenubtn').hide();

        $('.save-static-translation').on('click', function(e) {

            $('.save-static-translation').addClass('disabled');
            $('.save-static-translation').attr("disabled", true);

            e.preventDefault();
            console.log(Craft.elementIndex);
            self.saveStaticTranslation();
        });
    },
};

})(jQuery);