(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.StaticTranslations = {

    saveStaticTranslation: function(element) {
        const form = $("#static-translation");
        const postData = Garnish.getPostData(form);
        const data = Craft.expandPostArray(postData);
        data.source = Craft.elementIndex.sourceKey;
        data.siteId = Craft.elementIndex.siteId;

        Craft.sendActionRequest('POST', 'translations/static-translations/save', {data: data})
            .then((response) => {
                Craft.cp.displaySuccess(Craft.t('app', response.data.message));
                this.setJobTracking(
                    response.data.jobId,
                    "Success: Static translations synced."
                );
                Craft.elementIndex.updateElements();
            })
            .catch(({response}) => {
                Craft.cp.displayError(Craft.t('app', response.data.errors.join(', ')));
            })
            .finally(() => {
                element.removeClass('link-disabled loading');
            });
    },

    setJobTracking: function (jobId, onComplete) {
        Craft.Translations.trackJobCompletion(jobId, {
            onComplete: () => {
                Craft.cp.displaySuccess(onComplete);
            },
            onError: (error) => {
                Craft.cp.displayError('Job failed: ' + error);
            }
        });
    },

    exportStaticTranslation: function() {
        const params = {
           sourceKey: Craft.elementIndex.sourceKey,
           search: Craft.elementIndex.searchText
        };

        Craft.sendActionRequest('POST', 'translations/static-translations/export', {data: params})
        .then((response) => {
            if (!response.data || !response.data.success || !response.data.token) {
                const message = response.data && response.data.error
                    ? response.data.error
                    : 'Export failed.';
                Craft.cp.displayError(Craft.t('app', message));
                return;
            }

            this.downloadExport(response.data.token);
            Craft.cp.displaySuccess(Craft.t('app', 'Static Translations exported.'));
        })
        .catch(({response}) => {
            const message = response && response.data && response.data.error
                ? response.data.error
                : 'Export failed.';
            Craft.cp.displayError(Craft.t('app', message));
        });
    },

    downloadExport: function(token) {
       const frameName = 'static-translations-export-frame';
       let $iframe = $('iframe[name="' + frameName + '"]');

       if (!$iframe.length) {
            $iframe = $('<iframe/>', {
              name: frameName,
              style: 'display:none'
           }).appendTo(document.body);
        }

        const $form = $('<form/>', {
           method: 'post',
           action: Craft.getActionUrl('translations/static-translations/export-file'),
           target: frameName
       });

       $form.append($('<input/>', {
          type: 'hidden',
          name: Craft.csrfTokenName,
          value: Craft.csrfTokenValue
       }));
       $form.append($('<input/>', {
          type: 'hidden',
          name: 'token',
          value: token
       }));

       $form.appendTo(document.body);
       $form[0].submit();
       setTimeout(function() {
          $form.remove();
       }, 1000);
    },

    syncToDB: function (element) { 
        const params = {
            siteId: Craft.elementIndex.siteId,
            sourceKey: Craft.elementIndex.sourceKey
        };

        Craft.sendActionRequest('POST', 'translations/static-translations/sync', {data: params})
            .then((response) => {
                Craft.cp.displayNotice(Craft.t('app', "Notice: Sync job added to queue."));

                this.setJobTracking(
                    response.data.jobId,
                    "Success: Static translations synced."
                );
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