(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.GlobalSetEdit = {
    init: function(orders, globalSetId, drafts) {
        this.initAddToTranslationOrderButton(orders, globalSetId);
        this.initDraftsDropdown(drafts);
        this.initSaveDraftButton();
    },

    initDraftsDropdown: function(drafts) {
        var $container = $('<div>', {'class': 'select'}).css('margin-left', '0.75em');

        var $select = $('<select>');

        $select.appendTo($container);

        $select.on('change', function () {
            var url = $(this).val();

            if (url != '') {
                window.location.href = url;
            }
        });

        var $option = $('<option>', {'value': '', 'text': Craft.t('app', 'Current')});

        $option.appendTo($select);

        $.each(drafts, function(i, draft) {
            var $option = $('<option>', {'value': draft.url, 'text': draft.name});

            $option.appendTo($select);
        });

        $container.appendTo('#page-title');
    },

    initSaveDraftButton: function() {
        var $form = $('input[name=action][value="globals/save-content"]').closest('form');

        var $buttons = $form.find('> .buttons');

        var $btngroup = $('<div>', {'class': 'group'})
            .appendTo($buttons);

        var $submit = $buttons.find('.submit:first')
            .appendTo($btngroup);

        var $btn = $('<div>', {'class': 'btn menubtn'})
            .appendTo($btngroup);

        var $menu = $('<div>', {'class': 'menu'})
            .appendTo($btngroup);

        var $list = $('<ul>').appendTo($menu);

        var $item = $('<li>').appendTo($list);

        var $formsubmit = $('<a>', {
            'class': 'formsubmit',
            'text': Craft.t('app', 'Save as a draft'),
            'data-action': 'translations/global-set/save-draft'
        }).appendTo($item);

        $btn.menubtn();

        $formsubmit.formsubmit();
    },

    initAddToTranslationOrderButton: function(orders, globalSetId) {
        var self = this;

        var sourceSite = $('form#main-form input[type=hidden][name=siteId]').val();

        var $btngroup = $('<div>', {'class': 'btngroup right translations-dropdown'});

        var submitBtn = $('#header > .submit').length;

        if (submitBtn > 0) {
            $btngroup.insertBefore('#header > .btngroup');
        } else {
            $btngroup.insertBefore('#header .btngroup');
        }

        var $btn = $('<a>', {
            'class': 'btn icon',
            'href': '#',
            'data-icon': "language",
            'text': Craft.t('app', 'New translation')
        });

        var $menubtn = $('<div>', {
            'class': 'btn menubtn'
        });

        $btn.appendTo($btngroup);
        
        $menubtn.appendTo($btngroup);

        $menubtn.on('click', function(e) {
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
                    'value': sourceSite
                });

                $hiddenSourceSite.appendTo($form);

                var $hiddenGlobalSetId = $('<input>', {
                    'type': 'hidden',
                    'name': 'elements[]',
                    'value': globalSetId
                });

                $hiddenGlobalSetId.appendTo($form);

                var $submit = $('<input>', {
                    'type': 'submit'
                });

                $submit.appendTo($form);

                $form.submit();
            });
        }

        var $link = Craft.getUrl('translations/orders/create', {'elements[]': globalSetId, 'sourceSite': sourceSite});

        $btn.attr('href', $link);

        $menubtn.menubtn();
    }
};

$(function() {
});

})(jQuery);