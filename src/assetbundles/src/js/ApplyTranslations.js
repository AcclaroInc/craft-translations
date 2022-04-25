(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

function isDraftEdit() {
    $url = window.location.href;
    return $url.includes("&draftId=");
}

Craft.Translations.ApplyTranslations = {
    init: function(draftId, file) {
        // Disable default Craft publishing on Translation drafts
        window.draftEditor.settings.canUpdateSource = false;
        $("#publish-changes-btn-container :input[type='button']").disable();
        $("#publish-changes-btn-container :input[type='button']").attr('disabled', true);

        if (!isDraftEdit()) {
            $("#publish-draft-btn-container :input[type='button']").disable();
            $("#publish-draft-btn-container :input[type='button']").attr('disabled', true);
        }

        $("a[data-action='entry-revisions/publish-draft']").addClass("disabled");
        $("a[data-action='entry-revisions/publish-draft']").attr("disabled", true);
    },
};

})(jQuery);
