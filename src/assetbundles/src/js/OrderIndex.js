(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

/**
 * Order index class
 */
Craft.Translations.OrderIndex = Garnish.Base.extend(
{
    init: function() {}
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