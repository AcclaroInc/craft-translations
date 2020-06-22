(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.CategoryTranslations = {
    init: function(orders, categoryId) {
        this.initAddToTranslationOrderButton(orders, categoryId);
    },

    initAddToTranslationOrderButton: function(orders, categoryId) {
        var self = this;

        var sourceSite = $('form#main-form input[type=hidden][name=siteId]').val();

        var $btncontainer = document.createElement('div');
        $btncontainer.id = "translations-field";
        $btncontainer.className = "field";

        var $btngroup = $('<div>', {'class': 'btngroup translations-dropdown'});

        $settings = document.getElementById('settings');
        $settings.insertBefore($btncontainer, $settings.firstChild);
        var $headinggroup = $('<div>', {'class': 'heading'}).html('<label id="translations-label" for="translations">Translations</label>');
        var $inputgroup = $('<div>', {'class': 'input ltr'});

        $headinggroup.appendTo($btncontainer);
        $inputgroup.appendTo($btncontainer);
        $btngroup.appendTo($inputgroup);

        var $btn = $('<a>', {
            'class': 'btn submit icon',
            'href': '#',
            'data-icon': "language",
            'text': Craft.t('app', 'New Translation')
        });

        var $menubtn = $('<div>', {
            'class': 'btn submit menubtn'
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

                var $hiddenCategoryId = $('<input>', {
                    'type': 'hidden',
                    'name': 'elements[]',
                    'value': categoryId
                });

                $hiddenCategoryId.appendTo($form);

                var $submit = $('<input>', {
                    'type': 'submit'
                });

                $submit.appendTo($form);

                $form.submit();
            });
        }

        var $link = Craft.getUrl('translations/orders/new', {'elements[]': categoryId, 'sourceSite': sourceSite});

        $btn.attr('href', $link);

        $menubtn.menubtn();
    }
};

$(function() {
});

})(jQuery);
