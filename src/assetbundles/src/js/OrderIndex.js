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
        if ($('#sidebar-container').length) {
            $(document).on('click', '#toolbar button', function() {
                $selected = $('tbody').find('.sel');
                $existingElements = $("ul[class=menu]");
                if ($existingElements.length > 1) {
                    $existingElements.each(function() {
                        if ($(this).find("ul").length > 1) {
                            $(this).remove();
                        }
                    });
                }
                if ($('ul[class=menu] ul').length == 1) {
                    $('ul[class=menu]').prepend("<ul><li><a class=disabled>Edit Order</a></li></ul>");
                }

                if ($selected.length == 1) {
                    $url = $selected.find('span[class=title] a').prop('href');
                    $edit = $('ul[class=menu] li:first a');
                    $edit.removeClass('disabled');
                    $edit.attr('disabled', false);
                    $edit.prop('href', $url);
                } else {
                    $edit = $('ul[class=menu] li:first a');
                    $edit.addClass('disabled');
                    $edit.attr('disabled', true);
                }
            });
        }

        var paidNotification = localStorage.getItem(Craft.username+"PaidNotification");
        if(paidNotification == null || paidNotification == "false"){
            if (document.getElementById("trial-notice")) {
                document.getElementById("trial-notice").style.display = "block";
            }
        }

        var UpdateNotification = localStorage.getItem(Craft.username+"UpdateNotification");
        if(UpdateNotification == null || UpdateNotification == "false") {
            if (document.getElementById("update-notice")) {
                document.getElementById("update-notice").style.display = "block";
            }
        }

        $(document).on("click", ".close-notice", function() {
            var that = $(this);
            $("#"+that.data('id')).fadeOut();
            localStorage.setItem(Craft.username+that.data('key'), "true");
        })
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