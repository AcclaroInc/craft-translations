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
    var alert = true;
    var isGridLoaded = setInterval(() => {
        if ($(document).find('.spinner').hasClass('invisible')) {
            Craft.initUiElements('#main');
            clearInterval(isGridLoaded);
        }

        // To inject warning tooltip js after table load
        if (isGridLoaded && $('span').hasClass('order-warning') && alert) {
            $('.order-warning', '#global-container').infoicon();
            alert = false;
        }
    }, 100);
}, this));

})(jQuery);