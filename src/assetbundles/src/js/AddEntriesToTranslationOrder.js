(function($) {

function unique(array) {
    return $.grep(array, function(el, index) {
        return index === $.inArray(el, array);
    });
}

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.AddEntriesToTranslationOrder = {
    entries: [],

    $btn: null,
    $createNewLink: null,
    $sidebar: null,

    isEditEntryScreen: function() {
        return $('form#main-form input[type=hidden][name=action][value="elements/save"]').length > 0;
    },

    isCreateEntryScreen: function() {
        return $('form#main-form input[type=hidden][name=action][value="elements/apply-draft"]').length > 0;
    },

    isRevertRevisionScreen: function(){
        return $('form input[type=hidden][name=action][value="elements/revert"]').length > 0;
    },

    isEditDraftScreen: function(){
        return $('form#main-form input[type=hidden][name=action][value="elements/save-draft"]').length > 0;
    },

    getEditEntryId: function() {
        var entryId = $('form#main-form input[type=hidden][name=elementId]').val();
        if(!entryId) {
            entryId = $('form#main-form input[type=hidden][name=sourceId]').val();
        }

        return entryId;
    },

    updateSelectedEntries: function() {
        var entries = [];

        $('.elements table.data tbody tr.sel[data-id]').each(function() {
            entries.push($(this).data('id'));
        });

        this.entries = unique(entries);

        $(this.$btn[0]).toggleClass('link-disabled', this.entries.length === 0);
        $(this.$menubtn[0]).toggleClass('link-disabled', this.entries.length === 0);

        this.updateCreateNewLink();
    },

    updateCreateNewLink: function() {
        var href = this.$btn.attr('href').split('?')[0];

        href += '?sourceSite='+this.getSourceSite();

        for (var i = 0; i < this.entries.length; i++) {
            href += '&elements[]=' + this.entries[i];
        }

        this.$btn.attr('href', href);
    },

    getSourceSite: function() {
        if (this.isEditEntryScreen()) {
            return $('[name=siteId]').val();
        }

        var localeMenu = $('.sitemenubtn').data('menubtn').menu;

        // Figure out the initial locale
        var $option = localeMenu.$options.filter('.sel:first');


        if ($option.length === 0) {
            $option = localeMenu.$options.first();
        }

        var siteId = $option.data('site-id').toString();

        return siteId;
    },

    showWarning: function($orders, $element, callback) {
        if ($orders.length == 0) {
            return callback(false);
        }
        var $sourceSiteId = $('[name=siteId]').val();

        $orders.forEach(function($order) {
            if ($order.sourceSite == $sourceSiteId) {
                $order.elements.forEach(function($orderElement) {
                    if ($orderElement == $element) {
                        return callback(true);
                    }
                });
            }
        });
    },

    init: function(data) {
        if (this.isEditEntryScreen() && !this.getEditEntryId()) {
            return;
        }

        var self = this;
        this.$sidebar = $('#sidebar');

        this.data = data;

        var $btncontainer = document.createElement('div');
            $btncontainer.id = "translations-field";
            $btncontainer.className = "field";

        var $btngroup = $('<div>', {'class': 'btngroup translations-dropdown'});

        var $element = $('form#main-form input[type=hidden][name=sourceId]').val();

        var $showWarning = false;
        this.showWarning(data.openOrders, $element, function($result) {
            $showWarning = $result;
        });

        if ($showWarning) {
            var $warningContainer = document.createElement('div');
            $warningContainer.id = 'edit-source-warning';
            $warningContainer.className = 'meta read-only warning';
            $url = Craft.getUrl("translations/orders") + "?status[]=in+progress&status[]=in+review&status[]=in+preparation&status[]=getting+quote&status[]=needs+approval&status[]=complete&[]&elementIds[]=" + $element;
            $details = document.getElementById('details');
            $('#details > div:last').before($warningContainer);
            var $warningMessage = $('<div>').html('<label>Updates to source content may not reflect in delivered translations.</label>');
            $warningMessage.append($('<div style="margin-top: 10px;">').html('<a class=btn href='+$url+' target="_blank">View translation orders</a>'));
            $warningMessage.appendTo($warningContainer);
        }

        if (this.isEditEntryScreen()) {
            $settings = $('#slug-field').closest('div.meta');

            $settings.prepend($btncontainer);
            var $headinggroup = $('<div>', {'class': 'heading'}).html('<label id="translations-label" for="translations">Translations</label>');
            var $inputgroup = $('<div>', {'class': 'input ltr'});


            $headinggroup.appendTo($btncontainer);
            $inputgroup.appendTo($btncontainer);
            $btngroup.appendTo($inputgroup);
        } else if(! (this.isRevertRevisionScreen() || this.isEditDraftScreen() || this.isCreateEntryScreen())) {
            $btngroup.insertBefore('header#header > div:last');
        }

        this.$btn = $('<a>', {
            'class': 'btn icon',
            'href': '#',
            'data-icon': "language",
        });

        this.$btn.html("<span>" + Craft.t('app', 'New translation') + "</span>");

        this.$menubtn = $('<div>', {
            'class': 'btn menubtn'
        });

        if (!this.isEditEntryScreen()) {
            this.$btn.addClass('link-disabled');
            this.$menubtn.addClass('link-disabled');
        }

        this.$btn.appendTo($btngroup);

        (this.$menubtn).appendTo($btngroup);

        this.$menubtn.on('click', function(e) {
            e.preventDefault();
        });

        var $menu = $('<div>', {'class': 'menu'});

        $menu.appendTo($btngroup);

        var $dropdown = $('<ul>', {'class': ''});

        $dropdown.appendTo($menu);

        if (data.orders.length == '0') {
            var $item = $('<li>');

            $item.appendTo($dropdown);

            var $link = $('<a>', {
                'class': 'link-disabled',
                'text': 'No saved orders available...'
            });

            $link.appendTo($item);
        }

        for (var i = 0; i < data.orders.length; i++) {
            var order = data.orders[i];

            var $item = $('<li>');

            $item.appendTo($dropdown);

            var $link = $('<a>', {
                'href': '#',
                'text': 'Add to '+order.title
            });

            $link.appendTo($item);

            $link.data('order', order);

            $link.on('click', function(e) {
                e.preventDefault();

                var order = $(this).data('order');

                var $form = $('<form>', {
                    'method': 'POST'
                });

                $form.hide();

                $form.appendTo('body');

                $form.append(Craft.getCsrfInput());

                var $hiddenAction = $('<input>', {
                    'type': 'hidden',
                    'name': 'action',
                    'value': 'translations/base/add-elements-to-order'
                });

                $hiddenAction.appendTo($form);

                var $hiddenOrderId = $('<input>', {
                    'type': 'hidden',
                    'name': 'id',
                    'value': order.id
                });

                $hiddenOrderId.appendTo($form);

                var $hiddenSourceSite = $('<input>', {
                    'type': 'hidden',
                    'name': 'sourceSite',
                    'value': self.getSourceSite()
                });

                $hiddenSourceSite.appendTo($form);

                for (var j = 0; j < self.entries.length; j++) {
                    $('<input>', {
                        'type': 'hidden',
                        'name': 'elements[]',
                        'value': self.entries[j]
                    }).appendTo($form);
                }

                var $submit = $('<input>', {
                    'type': 'submit'
                });

                $submit.appendTo($form);

                $form.submit();
            });
        }

        var $item = $('<li>');

        $item.prependTo($dropdown);

        var $link = Craft.getUrl('translations/orders/create');

        this.$btn.attr('href', $link);

        this.$createNewLink = $link;

        this.$menubtn.menubtn();

        $(document).on('click', '.elements .checkbox, .elements .selectallcontainer .btn', function() {
            setTimeout($.proxy(self.updateSelectedEntries, self), 100);
        });

        // on edit entry screen
        if (this.isEditEntryScreen()) {
            this.entries.push(this.getEditEntryId());
            this.updateCreateNewLink();
        }

        this.$btn.on('click', function(e) {
            e.preventDefault();

            var $form = $('<form>', {
                'method': 'POST',
                'action': Craft.getUrl('translations/orders/create')
            });

            $form.hide();

            $form.appendTo('body');

            $form.append(Craft.getCsrfInput());

            var $hiddenSourceSite = $('<input>', {
                'type': 'hidden',
                'name': 'sourceSite',
                'value': self.getSourceSite()
            });

            $hiddenSourceSite.appendTo($form);

            for (var j = 0; j < self.entries.length; j++) {
                $('<input>', {
                    'type': 'hidden',
                    'name': 'elements[]',
                    'value': self.entries[j]
                }).appendTo($form);
            }

            var $submit = $('<input>', {
                'type': 'submit'
            });

            $submit.appendTo($form);

            $form.submit();
        });

        // This prevent the new translation button from remaining enabled when user selects an entry and changes entry group from side bar
        this.$sidebar.on('click', 'li', function () {
            $(self.$btn[0]).toggleClass('link-disabled', true);
            $(self.$menubtn[0]).toggleClass('link-disabled', true);
        });
    }
};

$(function() {
});

})(jQuery);
