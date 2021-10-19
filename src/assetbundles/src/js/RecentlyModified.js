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

                this.$widget.addClass('loading');

                $modal = new Garnish.Modal($('#diff-modal').removeClass('hidden'), {
                    autoShow: false,
                });

                $('#bulk-reorder').on('click', function(e){
                    if ($(this).hasClass('disabled')) {
                        e.preventDefault();
                    }
                });

                var modified = $('#modifiedEntries');
                var recent = $('#recentEntries');

                $('#modifiedEntries').on('click', function() {
                    if ($(this).hasClass('sel')) return;

                    recent.removeClass('sel');
                    modified.addClass('sel');
                    $("#"+modified.data('widget-id')).removeClass('hidden');
                    $("#"+recent.data('widget-id')).addClass('hidden');
                });
                
                $('#recentEntries').on('click', function() {
                    if ($(this).hasClass('sel')) return;
                    
                    recent.addClass('sel');
                    modified.removeClass('sel');
                    $("#"+modified.data('widget-id')).addClass('hidden');
                    $("#"+recent.data('widget-id')).removeClass('hidden');
                });

                var data = {
                    limit: params
                };

                Craft.postActionRequest('translations/widget/get-recently-modified', data, $.proxy(function(response, textStatus) {
                    if (textStatus === 'success') {
                        this.$widget.removeClass('loading');
                        this.$widget.find('.elements').removeClass('hidden');

                        this.$widget.find('table')
                            .attr('dir', response.dir);

                        var content = [];

                        if (response.data.length) {
                            this.$widget.find('#recently-modified-widget .tableview').prepend('<h5 style="padding: 20px;margin-bottom: -20px;">Primary site entries that have been modified since being translated.</h5>');
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
                            <td style="text-align:center;padding-top:15px;">Translated source entries are up to date.</td>
                            `;

                            this.$body.html(widgetHtml);
                        }
                    }
                    
                    window.translationsdashboard.widgets[widgetId].updateContainerHeight();
                    window.translationsdashboard.grid.refreshCols(true, true);

                    $('.entry-check .checkbox').on('click', function(e) {
                        $(e.target).closest('tr[id^=item-]').toggleClass('sel');
                        Craft.Translations.RecentlyModified.prototype.updateSelected();
                    });

                    var modified = $('#modifiedEntries');
                    var recent = $('#recentEntries');

                    $('#modifiedEntries').on('click', function() {
                        if ($(this).hasClass('sel')) return;

                        recent.removeClass('sel');
                        modified.addClass('sel');
                        $("#"+modified.data('widget-id')).addClass('hidden');
                        $("#"+recent.data('widget-id')).removeClass('hidden');

                    });
                    
                    $('#recentEntries').on('click', function() {
                        if ($(this).hasClass('sel')) return;

                        recent.addClass('sel');
                        modified.removeClass('sel');
                        $("#"+modified.data('widget-id')).removeClass('hidden');
                        $("#"+recent.data('widget-id')).addClass('hidden');
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

                        $('#modal-body').on('click', 'div.diff-copy', function(event) {
                            Craft.Translations.OrderEntries.copyTextToClipboard(event);
                        });
                        
                        $('#close-diff-modal').on('click', function(e) {
                            e.preventDefault();
                            $modal.hide();
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
                    $('#bulk-reorder').removeClass('disabled');
                } else {
                    $('#bulk-reorder').addClass('disabled');
                }

                var elements = '';
                for (var i = 0; i < this.entries.length; i++) {
                    elements += '&elements[]=' + this.entries[i];
                }

                if (!$('#bulk-reorder').hasClass('disabled')) {
                    $('#bulk-reorder').attr('href', Craft.getUrl('translations/orders/create?sourceSite='+ Craft.siteId + elements));
                }
            }
        });
})(jQuery);