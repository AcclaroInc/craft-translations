(function ($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    function unique(array) {
        return $.grep(array, function(el, index) {
            return index === $.inArray(el, array);
        });
    }

    Craft.Translations.AddTranslationsToCommerce = {
        $data: null,
        $btngroup: null,
        $btn: null,
        $menubtn: null,
        $sidebar: null,
        $selectedEntries: [],

        init: function($data) {
            var self = this;
            this.$data = $data;
            this.$sidebar = $('#sidebar');
            this.$btngroup = $('<div>', {'class': 'btngroup translations-dropdown'});

            if (this.isEditScreen()) {
                $btncontainer = $('<div/>', {
                    id: "translations-field",
                    class: "field"
                });

                $settings = $('#slug-field').closest('div.meta');

                $settings.prepend($btncontainer);
                $headinggroup = $('<div>', {'class': 'heading'}).html('<label id="translations-label" for="translations">Translations</label>');
                $inputgroup = $('<div>', {'class': 'input ltr'});

                $headinggroup.appendTo($btncontainer);
                this.$btngroup.appendTo($inputgroup);
                $inputgroup.appendTo($btncontainer);
            } else if (this.isIndexScreen()) {
                this.$btngroup.insertBefore('header#header > div:last');
            }

            this.$btn = $('<a>', {
                'class': 'btn icon',
                'href': Craft.getUrl('translations/orders/create'),
                'data-icon': "language",
            });

            this.$btn.html("<span class='btn-text'>" + Craft.t('app', 'New translation') + "</span>");

            this.$menubtn = $('<div>', {
                'class': 'btn menubtn'
            });

            if (!this.isEditScreen()) {
                this.$btn.addClass('link-disabled');
                this.$menubtn.addClass('link-disabled');
                this.$btn.find(".btn-text").addClass('display-none');
            }

            this.$btn.appendTo(this.$btngroup);
            this.$menubtn.appendTo(this.$btngroup);

            this.$menubtn.on('click', function(e) {
                e.preventDefault();
            });

            let $menu = $('<div>', {'class': 'menu'});

            $menu.appendTo(this.$btngroup);

            var $dropdown = $('<ul>', {'class': ''});

            $dropdown.appendTo($menu);

            if (this.$data.orders.length == '0') {
                var $item = $('<li>');

                $item.appendTo($dropdown);

                var $link = $('<a>', {
                    'class': 'link-disabled',
                    'text': 'No saved orders available...'
                });

                $link.appendTo($item);
            }

            for (var i = 0; i < this.$data.orders.length; i++) {
                var order = this.$data.orders[i];

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

                    for (var j = 0; j < self.$selectedEntries.length; j++) {
                        $('<input>', {
                            'type': 'hidden',
                            'name': 'elements[]',
                            'value': self.$selectedEntries[j]
                        }).appendTo($form);
                    }

                    var $submit = $('<input>', {
                        'type': 'submit'
                    });

                    $submit.appendTo($form);

                    $form.submit();
                });
            }

            this.$menubtn.menubtn();

            this.$btn.on('click', function (e) {
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

                for (var j = 0; j < self.$selectedEntries.length; j++) {
                    $('<input>', {
                        'type': 'hidden',
                        'name': 'elements[]',
                        'value': self.$selectedEntries[j]
                    }).appendTo($form);
                }

                var $submit = $('<input>', {
                    'type': 'submit'
                });

                $submit.appendTo($form);

                $form.submit();
            });

            $(document).on('click', '.elements .checkbox, .elements .selectallcontainer .btn', function() {
                setTimeout($.proxy(self.updateSelectedEntries, self), 100);
            });

            // set create url on edit entry screen
            if (this.isEditScreen()) {
                this.$selectedEntries.push(this.getEditProductId());
                this.updateCreateNewLink();
            }
            
            // This prevent the new translation button from remaining enabled when user selects an entry and changes entry group from side bar
            this.$sidebar.on('click', 'li', function () {
                $(self.$btn[0]).toggleClass('link-disabled', true);
                $(self.$menubtn[0]).toggleClass('link-disabled', true);
                self.$btn.find(".btn-text").addClass('display-none');
            });
        },
        isEditScreen: function() {
            return $('form#main-form input[type=hidden][name=productId]').length > 0;
        },
        isIndexScreen: function () {
            return $('form#main-form').length == 0;
        },
        updateSelectedEntries: function() {
            var entries = [];

            $('.elements table.data tbody tr.sel[data-id]').each(function() {
                entries.push($(this).data('id'));
            });

            this.$selectedEntries = unique(entries);

            $(this.$btn[0]).toggleClass('link-disabled', this.$selectedEntries.length === 0);
            $(this.$menubtn[0]).toggleClass('link-disabled', this.$selectedEntries.length === 0);
            $(this.$btn[0]).find(".btn-text").toggleClass('display-none', this.$selectedEntries.length === 0);

            this.updateCreateNewLink();
        },
        updateCreateNewLink: function () {
            var href = this.$btn.attr('href').split('?')[0];

            href += '?sourceSite='+this.getSourceSite();

            for (var i = 0; i < this.$selectedEntries.length; i++) {
                href += '&elements[]=' + this.$selectedEntries[i];
            }

            this.$btn.attr('href', href);
        },
        getSourceSite: function() {
            if (this.isEditScreen()) {
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
        getEditProductId: function() {
            var entryId = $('form#main-form input[type=hidden][name=productId]').val();

            return entryId;
        }
    };

})(jQuery);
