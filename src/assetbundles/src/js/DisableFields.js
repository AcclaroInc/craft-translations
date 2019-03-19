(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.DisableFields = {
    init: function() {
        var $form = $('form');
        // $form.find(':input').addClass('disabled').prop('disabled', true);
        // $form.find(':input,a,button').attr('tabindex', -1);
        // $form.find('.input').addClass('disabled');
        // $form.find('.btn').addClass('disabled');
        // $form.find('.redactor-box').addClass('disabled');
        $form.append('<span class="icon translations-lock"></span>');
    }
};

$(function() {
    Craft.Translations.DisableFields.init();
});

})(jQuery);