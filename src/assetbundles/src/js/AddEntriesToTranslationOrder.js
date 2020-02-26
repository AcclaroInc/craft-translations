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

    isEditEntryScreen: function() {
        return $('form#main-form input[type=hidden][name=action][value="entries/save-entry"]').length > 0;
    },

    getEditEntryId: function() {
        return $('form#main-form input[type=hidden][name=entryId]').val();
    },
    
    updateSelectedEntries: function() {
        var entries = [];

        $('.elements table.data tbody tr.sel[data-id]').each(function() {
            entries.push($(this).data('id'));
        });

        this.entries = unique(entries);

        $(this.$btn[0]).toggleClass('disabled', this.entries.length === 0);
        $(this.$menubtn[0]).toggleClass('disabled', this.entries.length === 0);

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

    init: function(data) {
        if (this.isEditEntryScreen() && !this.getEditEntryId()) {
            return;
        }

        var self = this;

        this.data = data;

        var $btncontainer = document.createElement('div');
            $btncontainer.id = "translations-field";
            $btncontainer.className = "field";

        var $btngroup = $('<div>', {'class': 'btngroup translations-dropdown'});
        
        if (this.isEditEntryScreen()) {
            $settings = document.getElementById('settings');
            $settings.insertBefore($btncontainer, $settings.firstChild);
            var $headinggroup = $('<div>', {'class': 'heading'}).html('<label id="translations-label" for="translations">Translations</label>');
            var $inputgroup = $('<div>', {'class': 'input ltr'});
            
            $headinggroup.appendTo($btncontainer);
            $inputgroup.appendTo($btncontainer);
            $btngroup.appendTo($inputgroup);
        } else {
            if (data.licenseStatus === 'valid') {
                $btngroup.insertBefore('#header #action-button');
            }
        }

        this.$btn = $('<a>', {
            'class': 'btn submit icon',
            'href': '#',
            'data-icon': "language",
            'text': Craft.t('app', 'New Translation')
        });

        this.$menubtn = $('<div>', {
            'class': 'btn submit menubtn'
        });

        if (!this.isEditEntryScreen()) {
            this.$btn.addClass('disabled');
            this.$menubtn.addClass('disabled');
        }

        this.$btn.appendTo($btngroup);

        this.$menubtn.appendTo($btngroup);

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

        var $link = Craft.getUrl('translations/orders/new');

        this.$btn.attr('href', $link);

        this.$createNewLink = $link;

        this.$menubtn.menubtn();

        var self = this;

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
                'action': Craft.getUrl('translations/orders/new')
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
    }
};

$(function() {
});

})(jQuery);