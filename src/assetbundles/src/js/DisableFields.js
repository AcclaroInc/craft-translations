(function($) {

if (typeof Craft.TranslationsForCraft === 'undefined') {
    Craft.TranslationsForCraft = {};
}

Craft.TranslationsForCraft.DisableFields = {
    init: function() {
        var $form = $('form');
        // $form.find(':input').addClass('disabled').prop('disabled', true);
        // $form.find(':input,a,button').attr('tabindex', -1);
        // $form.find('.input').addClass('disabled');
        // $form.find('.btn').addClass('disabled');
        // $form.find('.redactor-box').addClass('disabled');
        $form.append('<span class="icon translations-for-craft-lock"></span>');
    }
};

$(function() {
    Craft.TranslationsForCraft.DisableFields.init();
});

})(jQuery);