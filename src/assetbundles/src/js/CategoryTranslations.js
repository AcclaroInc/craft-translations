(function($) {

function unique(array) {
    return $.grep(array, function(el, index) {
        return index === $.inArray(el, array);
    });
}

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.CategoryTranslations = {

    categories: [],
    $btn: null,
    $sidebar: null,

    init: function (orders, categoryId) {
        var self = this;
        this.$sidebar = $('#sidebar');

        this.initAddToTranslationOrderButton(orders, categoryId);

        // This prevent the new translation button from remaining enabled when user selects an entry and changes entry group from side bar
        this.$sidebar.on('click', 'li', function () {
            $(self.$btn[0]).toggleClass('link-disabled', true);
            $(self.$menubtn[0]).toggleClass('link-disabled', true);
            self.$btn.find(".btn-text").addClass('display-none');
        });
    },

    isEditCategoryScreen: function() {
        return $('form#main-form input[type=hidden][name=action][value="elements/save"]').length > 0 ||
        $('form#main-form input[type=hidden][name=action][value="elements/save-draft"]').length > 0;
    },

    getEditCategoryId: function() {
        return $('form#main-form input[type=hidden][name=elementId]').val();
    },

    isCreatingFresh: function() {
        return $('form#main-form input[type=hidden][name=fresh]').val();
    },

    updateSelectedCategories: function() {
        var entries = [];

        $('.elements table.data tbody tr.sel[data-id]').each(function() {
            entries.push($(this).data('id'));
        });

        this.categories = unique(entries);

        $(this.$btn[0]).toggleClass('link-disabled', this.categories.length === 0);
        $(this.$menubtn[0]).toggleClass('link-disabled', this.categories.length === 0);
        $(this.$btn[0]).find(".btn-text").toggleClass('display-none', this.categories.length === 0);

        this.updateCreateNewLink();
    },

    updateCreateNewLink: function() {
        var href = this.$btn.attr('href').split('?')[0];

        href += '?sourceSite='+this.getSourceSite();

        for (var i = 0; i < this.categories.length; i++) {
            href += '&elements[]=' + this.categories[i];
        }

        this.$btn.attr('href', href);
    },

    getSourceSite: function() {
        if (this.isEditCategoryScreen()) {
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

    initAddToTranslationOrderButton: function(orders, categoryId) {
        var self = this;

        var $btncontainer = document.createElement('div');
            $btncontainer.id = "translations-field";
            $btncontainer.className = "field";

        var $btngroup = $('<div>', {'class': 'btngroup translations-dropdown'});

        if (this.isEditCategoryScreen()) {
            $settings = $('#slug-field').closest('div.meta')

            $settings.prepend($btncontainer);
            var $headinggroup = $('<div>', {'class': 'heading'}).html('<label id="translations-label" for="translations">Translations</label>');
            var $inputgroup = $('<div>', {'class': 'input ltr'});

            $headinggroup.appendTo($btncontainer);
            $inputgroup.appendTo($btncontainer);
            $btngroup.appendTo($inputgroup);
        } else if (!this.isCreatingFresh()) {
            $btngroup.insertBefore('#header #action-buttons');
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

        if (!this.isEditCategoryScreen()) {
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

                for (var j = 0; j < self.categories.length; j++) {
                    $('<input>', {
                        'type': 'hidden',
                        'name': 'elements[]',
                        'value': self.categories[j]
                    }).appendTo($form);
                }

                var $submit = $('<input>', {
                    'type': 'submit'
                });

                $submit.appendTo($form);

                $form.submit();
            });
        }

        var $link = Craft.getUrl('translations/orders/create', {'elements[]': categoryId, 'sourceSite': self.getSourceSite()});

        this.$btn.attr('href', $link);

        this.$menubtn.menubtn();

        var self = this;

        $(document).on('click', '.elements .checkbox, .elements .selectallcontainer .btn', function() {
            setTimeout($.proxy(self.updateSelectedCategories(), self), 100);
        });

        // on edit entry screen
        if (this.isEditCategoryScreen()) {
            this.categories.push(this.getEditCategoryId());
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

            for (var j = 0; j < self.categories.length; j++) {
                $('<input>', {
                    'type': 'hidden',
                    'name': 'elements[]',
                    'value': self.categories[j]
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
