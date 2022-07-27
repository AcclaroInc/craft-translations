(function($) {
    function unique(array) {
        return $.grep(array, function(el, index) {
            return index === $.inArray(el, array);
        });
    }

    Craft.Translations.LanguageCoverage = Garnish.Base.extend(
        {
            params: null,
            $widget: null,
            $body: null,
            $container: null,
            $tbody: null,
            hasEntries: null,

            init: function(widgetId, params) {
                this.params = params;
                this.$widget = $('#widget' + widgetId);
                this.$body = this.$widget.find('.body:first');
                this.$container = this.$widget.find('.languagecoverage-container:first');
                this.$tbody = this.$container.find('tbody:first');
                this.hasEntries = !!this.$tbody.length;

                this.$widget.addClass('loading');
                $modal = new Garnish.Modal($('#diff-modal').removeClass('hidden'), {
                    autoShow: false,
                });

                var $data = {
                    limit: params
                };

                Craft.sendActionRequest('POST', 'translations/widget/get-language-coverage', {data: $data})
                    .then((response) => {
                        this.$widget.removeClass('loading');
                        this.$widget.find('.elements').removeClass('hidden');

                        this.$widget.find('table')
                            .attr('dir', response.dir);

                        var content = [];

                        if (response.data.length) {
                            for (var i = 0; i < response.data.length; i++) {
                                var site = response.data[i],
                                    $container = $('#site-'+ (i + 1));
    
                                content.push(site);
    
                                $container.attr('data-id', site.entryId);
    
                                var widgetHtml = `
                                <td><a href="${site.url}">${site.name}</a></td>
                                <td style="text-align:center;">${site.enabledEntries}</td>
                                <td style="text-align:center;">${site.inQueue}</td>
                                <td style="text-align:center;">${site.translated}</td>
                                <td style="text-align:right;"><span style="color:${site.color};">${site.percentage}%</span></td>
                                `;
    
                                var checkbox = Craft.ui.createCheckbox({
                                    name: site.entryName,
                                    checked: false
                                });
    
                                $container.html(widgetHtml);
                                checkbox.appendTo($('#checkbox-'+ i));
                            }
                        } else {
                            var widgetHtml = `
                            <td style="text-align:center;">No coverage information available at this time.</td>
                            `;

                            this.$body.html(widgetHtml);
                        }
    
                        window.translationsdashboard.widgets[widgetId].updateContainerHeight();
                        window.translationsdashboard.grid.refreshCols(true, true);
                    })
                    .catch(({response}) => {
                        var widgetHtml = `
                        <td style="text-align:center;">Error fetching coverage information.</td>
                        `;
                        
                        this.$body.html(widgetHtml);
                    })
            },
        });
})(jQuery);