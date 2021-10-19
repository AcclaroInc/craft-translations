(function($) {
    function unique(array) {
        return $.grep(array, function(el, index) {
            return index === $.inArray(el, array);
        });
    }

    Craft.Translations.RecentEntries = Garnish.Base.extend(
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
                this.$body = this.$widget.find('#recent-entries-widget');
                this.$container = this.$widget.find('.recententries-container:first');
                this.$tbody = this.$container.find('tbody:first');
                this.hasEntries = !!this.$tbody.length;

                this.$widget.addClass('loading');

                $modal_entry = new Garnish.Modal($('#diff-modal-entry').removeClass('hidden'), {
                    autoShow: false,
                });

                var data = {
                    limit: params.limit,
                    days: params.days
                };

                Craft.postActionRequest('translations/widget/get-recent-entries', data, $.proxy(function(response, textStatus) {
                    if (textStatus === 'success') {
                        this.$widget.removeClass('loading');
                        this.$widget.find('.elements').removeClass('hidden');

                        this.$widget.find('table')
                            .attr('dir', response.dir);

                        var content = [];

                        if (response.data.length) {
                            for (var i = 0; i < response.data.length; i++) {
                                var item = response.data[i],
                                    $container = $('#item-entry-'+ (i + 1));

                                content.push(item);

                                $container.attr('data-id', item.entryId);

                                var widgetHtml = `
                                <td id="check-entry-${i}" class="new-entry-check checkbox-cell"></td>
                                <td><a href="${item.entryUrl}">${item.entryName}</a></td>
                                <td><span class="nowrap">${item.entryDate}</span></td>
                                <td style="text-align:right;"><a class="view-diff-entry btn icon view" data-id="${i}" href="#">View</a></td>
                                `;


                                $container.html(widgetHtml);

                                $('#check-entry-'+ i).append(
                                    Craft.ui.createCheckbox({
                                        name: item.entryName,
                                        checked: false
                                    })
                                );
                            }
                        } else {
                            var widgetHtml = `
                            <td style="text-align:center;padding-top:15px;">No new source entry found.</td>
                            `;

                            this.$body.html(widgetHtml);
                        }
                    }

                    window.translationsdashboard.widgets[widgetId].updateContainerHeight();
                    window.translationsdashboard.grid.refreshCols(true, true);

                    $('.new-entry-check .checkbox').on('click', function(e) {
                        $(e.target).closest('tr[id^=item-entry-]').toggleClass('sel');
                        Craft.Translations.RecentEntries.prototype.updateSelected();
                    });

                    $('.view-diff-entry').on('click', function(e) {
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
                        $modal_entry.show();

                        // Set modification details
                        for (let index = 0; index < classNames.length; index++) {
                            $('.' + classNames[index]).html(content[$(e.target).attr('data-id')][classNames[index]]);
                        }

                        // Add the diff html
                        document.getElementById("modal-body-entry").innerHTML = diffHtml;

                        $('#modal-body-entry').on('click', 'div.diff-copy', function(event) {
                            Craft.Translations.OrderEntries.copyTextToClipboard(event);
                        });

                        $('#close-diff-modal-entry').on('click', function(e) {
                            e.preventDefault();
                            $modal_entry.hide();
                        });
                    });
                }, this));
            },
            updateSelected: function() {
                var entries = [];

                $('.tableview table.data tr.sel[data-id]').each(function() {
                    entries.push($(this).data('id'));
                });

                this.entries = unique(entries);

                if (this.entries.length) {
                    $('#new-entry-orders').removeClass('disabled');
                } else {
                    $('#new-entry-orders').addClass('disabled');
                }

                var elements = '';
                for (var i = 0; i < this.entries.length; i++) {
                    elements += '&elements[]=' + this.entries[i];
                }

                if (!$('#new-entry-orders').hasClass('disabled')) {
                    $('#new-entry-orders').attr('href', Craft.getUrl('translations/orders/create?sourceSite='+ Craft.siteId + elements));
                }
            }
        });
})(jQuery);