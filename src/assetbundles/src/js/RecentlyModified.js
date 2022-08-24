(function($) {
    function unique(array) {
        return $.grep(array, function(el, index) {
            return index === $.inArray(el, array);
        });
    }

    Craft.Translations.RecentlyModified = Garnish.Base.extend(
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
                this.$body = this.$widget.find('#recently-modified-entries');
                this.$container = this.$widget.find('.recentlymodified-container:first');
                this.$tbody = this.$container.find('tbody:first');
                this.hasEntries = !!this.$tbody.length;
                // Hide widget title
                this.$widget.find('h2').html('');
                this.$widget.find('div.settings.icon').addClass('on-top');
                this.$widget.addClass('loading');

                $modal = new Garnish.Modal($('#diff-modal').removeClass('hidden'), {
                    autoShow: false,
                });

                $('#bulk-reorder').on('click', function(e){
                    if ($(this).hasClass('disabled')) {
                        e.preventDefault();
                    }
                });

                var modified = 'recently-modified-widget';
                var recent = 'recent-entries-widget';

                $('#tab-'+modified).on('click', function() {
                    if ($(this).hasClass('sel')) return;

                    $('#tab-'+recent).removeClass('sel');
                    $('#tab-'+modified).addClass('sel');
                    $('div.menu ul.padded li a[data-id="'+recent+'"]').removeClass('sel');
                    $('div.menu ul.padded li a[data-id="'+modified+'"]').addClass('sel');
                    $("#"+modified).removeClass('hidden');
                    $("#"+recent).addClass('hidden');
                    window.translationsdashboard.widgets[widgetId].updateContainerHeight();
                    window.translationsdashboard.grid.refreshCols(true, true);
                });

                $('#tab-'+recent).on('click', function() {
                    if ($(this).hasClass('sel')) return;

                    $('#tab-'+recent).addClass('sel');
                    $('#tab-'+modified).removeClass('sel');
                    $('div.menu ul.padded li a[data-id="'+recent+'"]').addClass('sel');
                    $('div.menu ul.padded li a[data-id="'+modified+'"]').removeClass('sel');
                    $("#"+modified).addClass('hidden');
                    $("#"+recent).removeClass('hidden');
                    window.translationsdashboard.widgets[widgetId].updateContainerHeight();
                    window.translationsdashboard.grid.refreshCols(true, true);
                });

                $(document).on('click', '#tabs .btn.menubtn', function() {
                    if ($('#tab-'+modified).hasClass('sel')) {
                        $('div.menu ul.padded li a[data-id="'+recent+'"]').removeClass('sel');
                        $('div.menu ul.padded li a[data-id="'+modified+'"]').addClass('sel');
                    } else {
                        $('div.menu ul.padded li a[data-id="'+recent+'"]').addClass('sel');
                        $('div.menu ul.padded li a[data-id="'+modified+'"]').removeClass('sel');
                    }
                });

                var $data = {
                    limit: params
                };

                Craft.sendActionRequest('POST', 'translations/widget/get-recently-modified', {data: $data})
                    .then((response) => {
                        this.$widget.removeClass('loading');
                        this.$widget.find('.elements').removeClass('hidden');

                        this.$widget.find('table')
                            .attr('dir', response.dir);

                        var content = [];

                        if (response.data.length) {
                            this.$widget.find('#recently-modified-widget .tableview').prepend('<h2 style="padding-top: 24px;">Modified Source Entries</h2><h5>Entries that have been modified post-translation.</h5>');
                            for (var i = 0; i < response.data.length; i++) {
                                var item = response.data[i],
                                    $container = $('#item-'+ (i + 1));

                                content.push(item);

                                $container.attr('data-id', item.entryId);

                                var widgetHtml = `
                                <td id="check-${i}" class="entry-check checkbox-cell"></td>
                                <td><a href="${item.entryUrl}">${item.entryName}</a></td>
                                <td><span class="nowrap">${item.entryDate}</span></td>
                                <td style="text-align:right;"><a class="view-diff btn icon view" data-id="${i}" href="#">View</a></td>
                                `;


                                $container.html(widgetHtml);

                                $('#check-'+ i).append(
                                    Craft.ui.createCheckbox({
                                        name: item.entryName,
                                        checked: false
                                    })
                                );
                            }
                        } else {
                            var widgetHtml = `
                            <td style="text-align:center;padding-top:15px;">There are no new modified source entries.</td>
                            `;

                            this.$widget.find('#recently-modified-widget').html(widgetHtml);
                        }

                        window.translationsdashboard.widgets[widgetId].updateContainerHeight();
                        window.translationsdashboard.grid.refreshCols(true, true);

                        $('.entry-check .checkbox').on('click', function(e) {
                            $(e.target).closest('tr[id^=item-]').toggleClass('sel');
                            Craft.Translations.RecentlyModified.prototype.updateSelected();
                        });

                        $('.view-diff').on('click', function(e) {
                            e.preventDefault();

                            var diffHtml = content[$(e.target).attr('data-id')].diff;

                            var classNames = [
                                'entryId',
                                'entryName',
                                'siteLabel',
                                'fileDate',
                                'entryDate',
                                'wordDifference'
                            ];

                            // Show the modal
                            $modal.show();

                            // Set the reorder button
                            $('.reorderUrl').attr('href', Craft.getUrl('translations/orders/create?sourceSite='+ content[$(e.target).attr('data-id')].siteId +'&elements[]='+ content[$(e.target).attr('data-id')].entryId));

                            // Set modification details
                            for (let index = 0; index < classNames.length; index++) {
                                $('.' + classNames[index]).html(content[$(e.target).attr('data-id')][classNames[index]]);
                            }

                            // Add the diff html
                            document.getElementById("modal-body").innerHTML = diffHtml;

                            // Off is used to prevent the js to be added on every parent action.
                            $('#modal-body').off('click').on('click', 'div.diff-copy', function(event) {
                                Craft.Translations.OrderEntries.copyTextToClipboard(event);
                            });

                            $('#close-diff-modal').off('click').on('click', function(e) {
                                e.preventDefault();
                                $modal.hide();
                            });
                        });
                    })
            },
            updateSelected: function() {
                var entries = [];

                $('.tableview table.data tr.sel[data-id]').each(function() {
                    entries.push($(this).data('id'));
                });

                this.entries = unique(entries);

                if (this.entries.length) {
                    $('#bulk-reorder').removeClass('link-disabled');
                } else {
                    $('#bulk-reorder').addClass('link-disabled');
                }

                var elements = '';
                for (var i = 0; i < this.entries.length; i++) {
                    elements += '&elements[]=' + this.entries[i];
                }

                if (elements !== '') {
                    $url = Craft.getUrl('translations/orders/create?sourceSite=' + Craft.siteId);

                    $('#bulk-reorder').attr('href', $url + elements);
                } else {
                    $('#bulk-reorder').attr('href', '#');
                }
            }
        });
})(jQuery);