(function($) {

function unique(array) {
    return $.grep(array, function(el, index) {
        return index === $.inArray(el, array);
    });
}

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.AssetsTranslations = {

    assets: [],
    $btn: null,

    init: function(orders, assetId) {
        self = this;
        self.addTranslationOrderButton(orders, assetId);

        $(document).on('click', '#sidebar nav ul li a .label',function() {
            endpoint = (window.location.href).split('/');
            if ($(this).closest("a").data("volume-handle") !== endpoint[endpoint.length-1]) {
                self.$btn.addClass('link-disabled');
                self.$menubtn.addClass('link-disabled');
                self.$btn.find(".btn-text").addClass('display-none');
            }
        });
    },

    isEditAssetScreen: function() {
        return $('form#main-form input[type=hidden][name=action][value="elements/save"]').length > 0;
    },

    getEditAssetId: function() {
        return $('form#main-form input[type=hidden][name=elementId]').val();
    },

    updateSelectedAssets: function() {
        var entries = [];

        $('.elements table.data tbody tr.sel[data-id]').each(function() {
            entries.push($(this).data('id'));
        });

        this.assets = unique(entries);

        $(this.$btn[0]).toggleClass('link-disabled', this.assets.length === 0);
        $(this.$btn[0]).find(".btn-text").toggleClass('display-none', this.assets.length === 0);
        $(this.$menubtn[0]).toggleClass('link-disabled', this.assets.length === 0);

        this.updateCreateNewLink();
    },

    updateCreateNewLink: function() {
        var href = this.$btn.attr('href').split('?')[0];

        href += '?sourceSite='+this.getSourceSite();

        for (var i = 0; i < this.assets.length; i++) {
            href += '&elements[]=' + this.assets[i];
        }

        this.$btn.attr('href', href);
    },

    getSourceSite: function() {
        if (this.isEditAssetScreen()) {
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

    addTranslationOrderButton: function(orders, assetId) {
        var self = this;
        assetId = this.getEditAssetId();

        var $btncontainer = document.createElement('div');
        $btncontainer.id = "translations-field";
        $btncontainer.className = "field";

        var $btngroup = $('<div>', {'class': 'btngroup translations-dropdown'});

        if (this.isEditAssetScreen()) {
            $settings = document.getElementById('new-filename-field').closest('div.meta');
            $settings.insertBefore($btncontainer, $settings.firstChild);
            var $headinggroup = $('<div>', {'class': 'heading'}).html('<label id="translations-label" for="translations">Translations</label>');
            var $inputgroup = $('<div>', {'class': 'input ltr'});

            $headinggroup.appendTo($btncontainer);
            $inputgroup.appendTo($btncontainer);
            $btngroup.appendTo($inputgroup);
        } else {
            $btngroup.insertBefore('header#header > div:last');
        }


        this.$btn = $('<a>', {
            'class': 'btn icon',
            'href': '#',
            'data-icon': "language",
        });

        this.$btn.html("<span class='btn-text'>" + Craft.t('app', 'New Translation') + "</span>");

        this.$menubtn = $('<div>', {
            'class': 'btn menubtn'
        });

        if (!this.isEditAssetScreen()) {
            this.$btn.addClass('link-disabled');
            this.$menubtn.addClass('link-disabled');
            this.$btn.find(".btn-text").addClass('display-none');
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

        if (orders.length == '0') {
            var $item = $('<li>');

            $item.appendTo($dropdown);

            var $link = $('<a>', {
                'class': 'link-disabled',
                'text': 'No saved orders available for this site...'
            });

            $link.appendTo($item);
        }

        for (var i = 0; i < orders.length; i++) {
            var order = orders[i];

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

                for (var j = 0; j < self.assets.length; j++) {
                    $('<input>', {
                        'type': 'hidden',
                        'name': 'elements[]',
                        'value': self.assets[j]
                    }).appendTo($form);
                }

                var $submit = $('<input>', {
                    'type': 'submit'
                });

                $submit.appendTo($form);

                $form.submit();
            });
        }

        var $link = Craft.getUrl('translations/orders/create', {'elements[]': assetId, 'sourceSite': self.getSourceSite()});

        this.$btn.attr('href', $link);

        this.$menubtn.menubtn();

        var self = this;

        $(document).on('click', '.elements .checkbox, .elements .selectallcontainer .btn', function() {
            setTimeout($.proxy(self.updateSelectedAssets(), self), 100);
        });

        // on edit entry screen
        if (this.isEditAssetScreen()) {
            this.assets.push(this.getEditAssetId());
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

            for (var j = 0; j < self.assets.length; j++) {
                $('<input>', {
                    'type': 'hidden',
                    'name': 'elements[]',
                    'value': self.assets[j]
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
