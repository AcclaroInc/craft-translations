(function($) {

if (typeof Craft.TranslationsForCraft === 'undefined') {
    Craft.TranslationsForCraft = {};
}

/**
 * Order index class
 */
Craft.TranslationsForCraft.OrderIndex = Garnish.Base.extend(
{
    init: function() {
        $(document).on("click", ".translations-delete-order", function() {
            var $button = $(this);

            if (confirm(Craft.t('app', 'Are you sure you want to delete this order?'))) {
                var data = {
                    action: 'translations-for-craft/base/delete-order',
                    orderId: $button.data('order-id')
                };

                data[Craft.csrfTokenName] = Craft.csrfTokenValue;

                $.post(
                    location.href,
                    data,
                    function (data) {
                        if (!data.success) {
                            alert(data.error);
                        } else {
                            $button.closest('tr').remove();
                            Craft.cp.displayNotice(Craft.t('app', 'Order deleted.'));
                            window.location.reload();
                        }
                    },
                    'json'
                );
            }
        });

        Garnish.$doc.mouseover($.proxy(function() {
            Craft.initUiElements('#main');
        }, this));
    }
});

$(function() {
    Craft.TranslationsForCraft.OrderIndex.prototype.init();
});

Garnish.$win.ready($.proxy(function() {
    var isGridLoaded = setInterval(() => {
        if ($(document).find('.spinner').hasClass('invisible')) {
            Craft.initUiElements('#main');
            clearInterval(isGridLoaded);
        }
    }, 100);
}, this));

})(jQuery);