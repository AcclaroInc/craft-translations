(function($) {
    /** global: Craft */
    /** global: Garnish */
    /**
     * Dashboard class
     */
    Craft.Translations.Dashboard = Garnish.Base.extend(
        {
            $grid: null,
            $widgetManagerBtn: null,

            widgetTypes: null,
            grid: null,
            widgets: null,
            widgetManager: null,
            widgetAdminTable: null,
            widgetSettingsModal: null,

            init: function(widgetTypes) {
                this.widgetTypes = widgetTypes;
                this.widgets = {};

                this.$widgetManagerBtn = $('#widgetManagerBtn');

                this.addListener(this.$widgetManagerBtn, 'click', 'showWidgetManager');

                Garnish.$doc.ready($.proxy(function() {
                    new Craft.Grid($('#translations-dashboard'));
                    $('#translations-dashboard').css('visibility', 'visible');
                    this.$grid = $('#translations-dashboard');
                    this.grid = this.$grid.data('grid');
                    $('#newwidgetmenubtn').data('menubtn').menu.on('optionselect', $.proxy(this, 'handleNewWidgetOptionSelect'));
                }, this));
            },

            getTypeInfo: function(type, property, defaultValue) {
                if (property) {
                    if (typeof this.widgetTypes[type][property] === 'undefined') {
                        return defaultValue;
                    }
                    else {
                        return this.widgetTypes[type][property];
                    }
                }
                else {
                    return this.widgetTypes[type];
                }
            },

            handleNewWidgetOptionSelect: function(e) {
                var $option = $(e.selectedOption);
                type = $option.data('type');
                $title = $option.data('name');
                if (type == 'acclaro\\translations\\widgets\\RecentlyModified') {
                    $title = "New & Modified Entries";
                }

                settingsNamespace = 'newwidget' + Math.floor(Math.random() * 1000000000) + '-settings';
                settingsHtml = this.getTypeInfo(type, 'settingsHtml', '').replace(/__NAMESPACE__/g, settingsNamespace);
                settingsJs = this.getTypeInfo(type, 'settingsJs', '').replace(/__NAMESPACE__/g, settingsNamespace);
                $gridItem = $('<div class="item" data-colspan="1" style="display: block">');
                $container = $(
                    '<div class="widget new loading-new scaleout ' + type.toLowerCase() + '" data-type="' + type + '">' +
                    '<div class="front">' +
                    '<div class="pane">' +
                    '<div class="spinner body-loading"/>' +
                    '<div class="settings icon hidden"/>' +
                    '<h2/>' +
                    '<div class="body"/>' +
                    '</div>' +
                    '</div>' +
                    '<div class="back">' +
                    '<form class="pane">' +
                    '<input type="hidden" name="type" value="' + type + '"/>' +
                    '<input type="hidden" name="settingsNamespace" value="' + settingsNamespace + '"/>' +
                    '<h2>' + Craft.t('app', '{title} Settings', {title: Craft.escapeHtml($title)}) + '</h2>' +
                    '<div class="settings"/>' +
                    '<hr/>' +
                    '<div class="buttons clearafter">' +
                    '<input type="submit" class="btn submit" value="' + Craft.t('app', 'Save') + '"/>' +
                    '<div class="btn" role="button">' + Craft.t('app', 'Cancel') + '</div>' +
                    '<div class="spinner hidden"/>' +
                    '</div>' +
                    '</form>' +
                    '</div>' +
                    '</div>'
                ).appendTo($gridItem);

                if (settingsHtml) {
                    $container.addClass('flipped');
                } else {
                    $container.addClass('loading');
                }

                var widget = new Craft.Translations.Widget($container, settingsHtml.replace(/__NAMESPACE__/g, settingsNamespace), function() {
                    eval(settingsJs);
                });

                if (this.grid.$items.length) {
                    $gridItem.insertAfter(this.grid.$items.last());
                } else {
                    $gridItem.prependTo(this.grid.$container);
                }

                this.grid.addItems($gridItem);
                Garnish.scrollContainerToElement($gridItem);

                $container.removeClass('scaleout');

                if (!settingsHtml) {
                    var params = {
                        type: type
                    };

                    Craft.sendActionRequest('POST', 'translations/widget/create-widget', {data: params})
                        .then((response) => {
                            $container.removeClass('loading');
                            widget.update(response);
                        })
                        .catch(() => {
                            widget.destroy();
                        })
                }
            },

            showWidgetManager: function() {
                if (!this.widgetManager) {
                    var $widgets = this.$grid.find('> .item > .widget'),
                        $form = $(
                            '<form method="post" accept-charset="UTF-8">' +
                            '<input type="hidden" name="action" value="translations/widget/save-widget"/>' +
                            '</form>'
                        ).appendTo(Garnish.$bod),
                        $noWidgets = $('<p id="nowidgets"' + ($widgets.length ? ' class="hidden"' : '') + '>' + Craft.t('app', 'You don’t have any widgets yet.') + '</p>').appendTo($form),
                        $table = $('<table class="data' + (!$widgets.length ? ' hidden' : '') + '"/>').appendTo($form),
                        $tbody = $('<tbody/>').appendTo($table);

                    for (var i = 0; i < $widgets.length; i++) {
                        var $widget = $widgets.eq(i),
                            widget = $widget.data('widget');

                        // Make sure it's actually saved
                        if (!widget || !widget.id || widget.title == 'New Source Entries') {
                            continue;
                        }

                        widget.getManagerRow().appendTo($tbody);
                    }

                    this.widgetManager = new Garnish.HUD(this.$widgetManagerBtn, $form, {
                        hudClass: 'hud widgetmanagerhud',
                        onShow: $.proxy(function() {
                            this.$widgetManagerBtn.addClass('active');
                        }, this),
                        onHide: $.proxy(function() {
                            this.$widgetManagerBtn.removeClass('active');
                        }, this)
                    });

                    this.widgetAdminTable = new Craft.AdminTable({
                        tableSelector: $table,
                        noObjectsSelector: $noWidgets,
                        sortable: true,
                        reorderAction: 'translations/widget/reorder-user-widgets',
                        deleteAction: 'translations/widget/delete-user-widget',
                        onReorderItems: $.proxy(function(ids) {
                            var lastWidget = null;

                            for (var i = 0; i < ids.length; i++) {
                                var widget = this.widgets[ids[i]];

                                if (!lastWidget) {
                                    widget.$gridItem.prependTo(this.$grid);
                                } else {
                                    widget.$gridItem.insertAfter(lastWidget.$gridItem);
                                }

                                lastWidget = widget;

                                if (widget.title == 'Modified Source Entries') {
                                    let clone = this.widgets[$('div[data-title="New Source Entries"]').data('id')];
                                    clone.$gridItem.insertAfter(lastWidget.$gridItem);
                                    lastWidget = clone;
                                }
                            }

                            this.grid.resetItemOrder();

                        }, this),
                        onDeleteItem: $.proxy(function(id) {
                            var widget = this.widgets[id];

                            widget.destroy();

                            window.location.reload();
                        }, this)
                    });
                }
                else {
                    this.widgetManager.show();
                }
            }
        });


    /**
     * Dashboard Widget class
     */
    Craft.Translations.Widget = Garnish.Base.extend(
        {
            $container: null,
            $gridItem: null,

            $front: null,
            $settingsBtn: null,
            $title: null,
            $bodyContainer: null,

            $back: null,
            $settingsForm: null,
            $settingsContainer: null,
            $settingsSpinner: null,
            $settingsErrorList: null,

            id: null,
            type: null,
            title: null,

            totalCols: null,
            settingsHtml: null,
            initSettingsFn: null,
            showingSettings: false,

            colspanPicker: null,

            init: function(container, settingsHtml, initSettingsFn) {
                this.$container = $(container);
                this.$gridItem = this.$container.parent();

                // Store a reference to this object on the container element
                this.$container.data('widget', this);

                // Do a little introspection
                this.id = this.$container.data('id');
                this.type = this.$container.data('type');
                this.title = this.$container.data('title');

                if (this.id) {
                    // Store a reference to this object on the main Dashboard object
                    window.translationsdashboard.widgets[this.id] = this;
                }

                this.$front = this.$container.children('.front');
                this.$settingsBtn = this.$front.find('> .pane > .icon.settings');
                this.$title = this.$front.find('> .pane > h2');
                this.$bodyContainer = this.$front.find('> .pane > .body');

                this.setSettingsHtml(settingsHtml, initSettingsFn);

                if (!this.$container.hasClass('flipped')) {
                    this.onShowFront();
                }
                else {
                    this.initBackUi();
                    this.refreshSettings();
                    this.onShowBack();
                }

                this.addListener(this.$settingsBtn, 'click', 'showSettings');

                let timeoutID;
                window.addEventListener('resize', $.proxy(function() {
                    clearTimeout(timeoutID);
                    timeoutID = setTimeout(window.translationsdashboard.grid.refreshCols(false, true), 500)
                }, this));
            },

            initBackUi: function() {
                this.$back = this.$container.children('.back');
                this.$settingsForm = this.$back.children('form');
                this.$settingsContainer = this.$settingsForm.children('.settings');
                var $btnsContainer = this.$settingsForm.children('.buttons');
                this.$settingsSpinner = $btnsContainer.children('.spinner');

                this.addListener($btnsContainer.children('.btn:nth-child(2)'), 'click', 'cancelSettings');
                this.addListener(this.$settingsForm, 'submit', 'saveSettings');
            },

            getColspan: function() {
                return this.$gridItem.data('colspan');
            },

            setColspan: function(colspan) {
                this.$gridItem.data('colspan', colspan);
                window.translationsdashboard.grid.refreshCols(true);
            },

            getTypeInfo: function(property, defaultValue) {
                return window.translationsdashboard.getTypeInfo(this.type, property, defaultValue);
            },

            setSettingsHtml: function(settingsHtml, initSettingsFn) {
                this.settingsHtml = settingsHtml;
                this.initSettingsFn = initSettingsFn;

                if (this.settingsHtml) {
                    this.$settingsBtn.removeClass('hidden');
                }
                else {
                    this.$settingsBtn.addClass('hidden');
                }
            },

            refreshSettings: function() {
                this.$settingsContainer.html(this.settingsHtml);

                Garnish.requestAnimationFrame($.proxy(function() {
                    Craft.initUiElements(this.$settingsContainer);
                    this.initSettingsFn();
                }, this));
            },

            showSettings: function() {
                if (!this.$back) {
                    this.initBackUi();
                }

                // Refresh the settings every time
                this.refreshSettings();

                this.$container
                    .addClass('flipped')
                    .velocity({height: this.$back.height()}, {
                        complete: $.proxy(this, 'onShowBack')
                    });
            },

            hideSettings: function() {
                this.$container
                    .removeClass('flipped')
                    .velocity({height: this.$front.height()}, {
                        complete: $.proxy(this, 'onShowFront')
                    });
            },

            saveSettings: function(e) {
                e.preventDefault();
                this.$settingsSpinner.removeClass('hidden');

                var action = this.$container.hasClass('new') ? 'translations/widget/create-widget' : 'translations/widget/save-widget-settings';
                data = this.$settingsForm.serialize();

                Craft.sendActionRequest('POST', action, {data})
                    .then((response) => {
                        this.$settingsSpinner.addClass('hidden');

                        if (this.$settingsErrorList) {
                            this.$settingsErrorList.remove();
                            this.$settingsErrorList = null;
                        }

                        Craft.cp.displaySuccess(Craft.t('app', 'Success: Widget saved.'));

                        // Make sure the widget is still allowed to be shown, just in case
                        if (!response.data.info) {
                            this.destroy();
                        }
                        else {
                            this.update(response.data);
                            this.hideSettings();
                        }
                        window.location.reload();
                    }).catch(({response}) => {
                        if (this.$settingsErrorList) {
                            this.$settingsErrorList.remove();
                            this.$settingsErrorList = null;
                        }
                        Craft.cp.displayError(Craft.t('app', 'Error: Couldn’t save widget.'));

                        if (response.data.errors) {
                            this.$settingsErrorList = Craft.ui.createErrorList(response.data.errors)
                                .insertAfter(this.$settingsContainer);
                        }
                    });
            },

            update: function(response) {
                this.title = response.info.title;

                // Is this a new widget?
                if (this.$container.hasClass('new')) {
                    // Discover ourself
                    this.id = response.info.id;

                    this.$container
                        .attr('id', 'widget' + this.id)
                        .removeClass('new loading-new');

                    if (this.$settingsForm) {
                        this.$settingsForm.prepend('<input type="hidden" name="widgetId" value="' + this.id + '"/>');
                    }

                    // Store a reference to this object on the main Dashboard object, now that the widget actually exists
                    window.translationsdashboard.widgets[this.id] = this;

                    if (window.translationsdashboard.widgetAdminTable) {
                        window.translationsdashboard.widgetAdminTable.addRow(this.getManagerRow());
                    }
                }
                else {
                    if (window.translationsdashboard.widgetAdminTable) {
                        window.translationsdashboard.widgetAdminTable.$tbody.children('[data-id="' + this.id + '"]:first').children('td:nth-child(2)').html(this.getManagerRowLabel());
                    }
                }

                this.$title.text(this.title);
                this.$bodyContainer.html(response.info.bodyHtml);

                // New colspan?
                if (response.info.colspan != this.getColspan()) {
                    this.setColspan(response.info.colspan);
                    Garnish.scrollContainerToElement(this.$gridItem);
                }

                Craft.initUiElements(this.$bodyContainer);
                Craft.appendHeadHtml(response.headHtml);
                Craft.appendBodyHtml(response.footHtml);

                this.setSettingsHtml(response.info.settingsHtml, function() {
                    eval(response.info.settingsJs);
                });
            },

            cancelSettings: function() {
                if (this.id) {
                    this.hideSettings();
                }
                else {
                    this.destroy();
                }
            },

            onShowFront: function() {
                this.showingSettings = false;
                this.removeListener(this.$back, 'resize');
                this.addListener(this.$front, 'resize', 'updateContainerHeight');
            },

            onShowBack: function() {
                this.showingSettings = true;
                this.removeListener(this.$front, 'resize');
                this.addListener(this.$back, 'resize', 'updateContainerHeight');

                // Focus on the first input
                setTimeout($.proxy(function() {
                    this.$settingsForm.find(':focusable:first').trigger('focus');
                }, this), 1);
            },

            updateContainerHeight: function() {
                this.$container.height((this.showingSettings ? this.$back : this.$front).height());
            },

            getManagerRow: function() {
                var $row = $(
                    '<tr data-id="' + this.id + '" data-name="' + Craft.escapeHtml(this.title) + '">' +
                    '<td class="widgetmanagerhud-icon">' + this.getTypeInfo('iconSvg') + '</td>' +
                    '<td>' + this.getManagerRowLabel() + '</td>' +
                    '<td class="widgetmanagerhud-col-colspan-picker thin"></td>' +
                    '<td class="widgetmanagerhud-col-move thin"><a class="move icon" title="' + Craft.t('app', 'Reorder') + '" role="button"></a></td>' +
                    '<td class="thin"><a class="delete icon" title="' + Craft.t('app', 'Delete') + '" role="button"></a></td>' +
                    '</tr>'
                );

                // Initialize the colspan picker
                this.colspanPicker = new Craft.Translations.WidgetColspanPicker(this, $row.find('> td.widgetmanagerhud-col-colspan-picker'));

                return $row;
            },

            getManagerRowLabel: function() {
                var typeName = this.getTypeInfo('name');
                if (typeName == 'Modified Source Entries') {
                    return Craft.escapeHtml('New & Modified Entries');
                }
                return Craft.escapeHtml(this.title) + (this.title !== typeName ? ' <span class="light">(' + typeName + ')</span>' : '');
            },

            destroy: function() {
                delete window.translationsdashboard.widgets[this.id];
                this.$container.addClass('scaleout');
                this.base();

                setTimeout($.proxy(function() {
                    window.translationsdashboard.grid.removeItems(this.$gridItem);
                    this.$gridItem.remove();
                }, this), 200);
            }
        });


    /**
     * Widget colspan picker class
     */
    Craft.Translations.WidgetColspanPicker = Garnish.Base.extend(
        {
            widget: null,
            maxColspan: null,

            $container: null,
            $colspanButtons: null,

            totalGridCols: null,

            init: function(widget, $td) {
                this.widget = widget;
                this.$container = $('<div class="colspan-picker"/>').appendTo($td);
                this.maxColspan = this.widget.getTypeInfo('maxColspan');

                this.totalGridCols = window.translationsdashboard.grid.totalCols;
                this.createColspanButtons();

                window.translationsdashboard.grid.on('refreshCols', $.proxy(this, 'handleGridRefresh'));

                this.addListener(this.$container, 'mouseover', function(ev) {
                    $(ev.currentTarget).addClass('hover');
                });

                this.addListener(this.$container, 'mouseout', function(ev) {
                    $(ev.currentTarget).removeClass('hover');
                });
            },

            handleGridRefresh: function() {
                // Have the number of columns changed?
                if (this.totalGridCols !== (this.totalGridCols = window.translationsdashboard.grid.totalCols)) {
                    // Remove the current buttons
                    if (this.$colspanButtons) {
                        this.$colspanButtons.remove();
                    }

                    // Recreate them
                    this.createColspanButtons();
                }
            },

            createColspanButtons: function() {
                // Figure out what the current max colspan and widget colspan are.
                var currentMaxColspan = this.maxColspan ? Math.min(this.maxColspan, this.totalGridCols) : this.totalGridCols,
                    currentColspan = Math.min(this.widget.getColspan(), currentMaxColspan);

                // Create the buttons
                for (var i = 1; i <= currentMaxColspan; i++) {
                    var cssClass = '';

                    if (i <= currentColspan) {
                        cssClass = 'active';
                    }

                    if (i === currentColspan) {
                        cssClass += (cssClass ? ' ' : '') + 'last';
                    }

                    $('<a/>', {
                        title: (i === 1 ? Craft.t('app', '1 column') : Craft.t('app', '{num} columns', {num: i})),
                        role: 'button',
                        'class': cssClass,
                        data: {colspan: i}
                    }).appendTo(this.$container);
                }

                // Add listeners
                this.$colspanButtons = this.$container.children();

                this.addListener(this.$colspanButtons, 'mouseover', function(ev) {
                    var $button = $(ev.currentTarget);
                    $button.add($button.prevAll()).addClass('highlight');
                    $button.nextAll().removeClass('highlight');
                });

                this.addListener(this.$colspanButtons, 'mouseout', function() {
                    this.$colspanButtons.removeClass('highlight');
                });

                this.addListener(this.$colspanButtons, 'click', $.proxy(function(ev) {
                    this.setWidgetColspan($.data(ev.currentTarget, 'colspan'));
                }, this));
            },

            setWidgetColspan: function(newColspan) {
                // Update the button .active and .last classes
                this.$colspanButtons.removeClass('last active');
                var $activeButton = this.$colspanButtons.eq(newColspan - 1);
                $activeButton.add($activeButton.prevAll()).addClass('active');
                $activeButton.addClass('last');

                // Update the widget and grid
                this.widget.setColspan(newColspan);
                window.translationsdashboard.grid.refreshCols(true);

                let widgetIds = this.widget.id;

                if (this.widget.title == 'Modified Source Entries') {
                    widgetIds += ","+$('div[data-title="New Source Entries"]').data('id');
                }

                // Save the change
                var params = {
                    id: widgetIds,
                    colspan: newColspan
                };

                Craft.sendActionRequest('POST', 'translations/widget/change-widget-colspan', {data: params})
                    .then((response) => {
                        Craft.cp.displaySuccess(Craft.t('app', 'Success: Widget saved.'));
                        location.reload();
                    })
                    .catch(() => {
                        Craft.cp.displayError(Craft.t('app', 'Error: Couldn’t save widget.'));
                    })
            }
        });
})(jQuery);