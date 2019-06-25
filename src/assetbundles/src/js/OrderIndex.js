(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

/**
 * Order index class
 */
Craft.Translations.OrderIndex = Garnish.Base.extend(
{
    init: function() {
        $(document).on("click", ".translations-delete-order", function() {
            var $button = $(this);

            var message = 'Order moved to trash.';
            var conf_msg = 'move to trash';
            if ($button.data('hard-delete')) {
                message = "Order deleted permanently.";
                conf_msg = 'delete';
            }

            if (confirm(Craft.t('app', 'Are you sure you want to '+conf_msg+' this order?'))) {
                var data = {
                    action: 'translations/base/delete-order',
                    orderId: $button.data('order-id'),
                    hardDelete: $button.data('hard-delete'),
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
                            Craft.cp.displayNotice(Craft.t('app', ''+message));
                            window.location.reload();
                        }
                    },
                    'json'
                );
            }
        });

        $(document).on("click", ".translations-restore-order", function() {
            var $button = $(this);

            if (confirm(Craft.t('app', 'Are you sure you want to restore this order?'))) {
                var data = {
                    action: 'translations/base/delete-order',
                    orderId: $button.data('order-id'),
                    restore: 1
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
                            Craft.cp.displayNotice(Craft.t('app', 'Order restored.'));
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
    Craft.Translations.OrderIndex.prototype.init();
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