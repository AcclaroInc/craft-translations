(function($) {
    Craft.Translations.AcclaroNews = Garnish.Base.extend(
        {
            $widget: null,
            $container: null,
            $table: null,

            init: function(widgetId, params) {
                this.$widget = $('#widget' + widgetId);
                this.$container = this.$widget.find('.recentnews-container:first');
                this.$table = this.$container.find('table:first > tbody');

                this.$widget.addClass('loading');

                Craft.sendActionRequest('POST', 'translations/widget/get-acclaro-news', {data: params})
                    .then((response) => {
                        this.$widget.removeClass('loading');
                        this.$widget.find('.elements').removeClass('hidden');

                        this.$table.attr('dir', response.dir);

                        if (response.data.length) {
                            for (var i = 0; i < response.data.length; i++) {
                                var article = response.data[i];

                                var widgetHtml = `<tr>
                                    <td>
                                        <a href="${ article.link }">${ article.title }</a>
                                    </td>
                                    <td style="text-align:right;">
                                        <span class="light">
                                            ${ article.pubDate }
                                        </span>
                                    </td>
                                </tr>`;
                                this.$table.append(widgetHtml);
                            }
                        } else {
                            var widgetHtml = `<tr>
                                <td>
                                    <p>${ "No Acclaro news available."|Craft.t('app') }</p>
                                </td>
                            </tr>`;

                            this.$table.html(widgetHtml);
                        }
    
                        window.translationsdashboard.widgets[widgetId].updateContainerHeight();
                        window.translationsdashboard.grid.refreshCols(true, true);
                    })
                    .catch(({response}) => {
                        var widgetHtml = `<tr>
                            <td>
                                <p>${ "Unable to fetch Acclaro news."|Craft.t('app') }</p>
                            </td>
                        </tr>`;
                        
                        this.$table.html(widgetHtml);
                    })
            },
        });
})(jQuery);