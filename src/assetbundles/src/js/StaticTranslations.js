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

    exportStaticTranslation: function(bulkExport = false) {
        let exportSiteIds;

        if (bulkExport) {
            exportSiteIds = Craft.sites
                ?.filter(site => site.id !== Craft.primarySiteId)
                .map(site => site.id) ?? [];
        } else {
            exportSiteIds = [Craft.elementIndex.siteId];
        }

        params = {
            siteIds: exportSiteIds,
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
        this._createExportButtonGroup();

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

    _createExportButtonGroup: function () {
        const self = this;
        
        // Hide the original export button
        $('#translate-export').hide();
        
        // Create button group container
        const $btngroup = $('<div>', { class: 'btngroup translations-dropdown' });
        $btngroup.insertAfter('#translate-export');

        // Main Export button
        this.$btn = $('<a>', {
            class: 'btn icon translations-submit-order',
            href: '#',
            'data-icon': 'download',
        });

        this.$btn.html("<span class='spinner spinner-absolute translations-loader'></span><div class='label'>Export</div>");
        this.$btn.appendTo($btngroup);

        // Dropdown menu button
        this.$menubtn = $('<div>', {
            class: 'btn menubtn'
        });
        this.$menubtn.appendTo($btngroup);

        // Dropdown menu
        const $menu = $('<div>', { class: 'menu' }).appendTo($btngroup);
        const $dropdown = $('<ul>').appendTo($menu);

        // Bulk Export menu item
        const $item = $('<li>').appendTo($dropdown);
        const $bulkExportLink = $('<a>', {
            class: 'translations-submit-order update-and-new',
            href: '#',
            text: 'Export All Sites'
        }).appendTo($item);

        this.$btn.on('click', function(e) {
            e.preventDefault();
            self.exportStaticTranslation();
        });

        $bulkExportLink.on('click', function(e) {
            e.preventDefault();
            self.exportStaticTranslation(true); // Bulk export
        });

        new Garnish.MenuBtn(this.$menubtn);
    }
};

})(jQuery);