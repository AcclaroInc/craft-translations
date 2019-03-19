(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.ShowCompleteOrdersIndicator = {
    numberOfCompleteOrders: 0,
    init: function(numberOfCompleteOrders) {
        this.numberOfCompleteOrders = numberOfCompleteOrders;

        if (this.numberOfCompleteOrders > 0) {
            this.showIndicator();
        }
    },
    showIndicator: function() {
        // var $link = $('<span>', {'class': 'translations-complete-orders-indicator'});
        // var $stamp = $('<span>', {'class': 'translations-complete-orders-indicator', 'data-icon': 'newstamp'});
        // var $indicator = $('<span>', {'text': this.numberOfCompleteOrders});
        var $badge = $('<span>', {'class': 'badge number-of-orders', 'text': this.numberOfCompleteOrders});

        // $indicator.appendTo($stamp);

        // $stamp.appendTo($link);

        if (!$('#nav-translations > a > span').last().hasClass('number-of-orders')) {
            $badge.appendTo('#nav-translations > a');
        }
    }
};

})(jQuery);