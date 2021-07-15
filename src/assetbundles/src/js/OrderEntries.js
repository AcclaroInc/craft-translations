(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

/**
 * Order entries class
 */
Craft.Translations.OrderEntries = {
    $checkboxes: null,
    $selectAllCheckbox: null,
    $publishSelectedBtn: null,

    hasSelections: function() {
        return this.$checkboxes.filter(':checked').length > 0;
    },
    getSelections: function() {
        return this.$checkboxes.filter(':checked');
    },
    toggleSelected: function(toggle) {
        this.$checkboxes.prop('checked', toggle);

        this.togglePublishButton();
    },
    toggleSelectAllCheckbox() {
        this.$selectAllCheckbox.prop(
            'checked',
            this.$checkboxes.filter(':checked').length === this.$checkboxes.length
        );
    },
    togglePublishButton: function() {
        if (this.hasSelections()) {
            this.$publishSelectedBtn.prop('disabled', false).removeClass('disabled');
        } else {
            this.$publishSelectedBtn.prop('disabled', true).addClass('disabled');
        }
    },
    init: function() {
        this.$publishSelectedBtn = $('#draft-publish');
        this.$formId = 'publish-form';
        this.$form = $('#' + this.$formId);
        this.$selectAllCheckbox = $('thead .translations-checkbox-cell :checkbox');
        this.$checkboxes = $('tbody .translations-checkbox-cell :checkbox').not('[disabled]');

        this.$selectAllCheckbox.on('change', function() {
            Craft.Translations.OrderEntries.toggleSelected($(this).is(':checked'));
        });

        this.$checkboxes.on('change', function() {
            Craft.Translations.OrderEntries.togglePublishButton();
            Craft.Translations.OrderEntries.toggleSelectAllCheckbox();
        });

        this.$publishSelectedBtn.on('click', function () {
            var form = Craft.Translations.OrderEntries._buildPublishModal();
            var $modal = new Garnish.Modal(form, {
                closeOtherModals : false
            });

            // Destroy the modal that is being hided as a new modal will be created every time
            $modal.on('hide', function() {
                $('.modal.scroll-y-auto, .modal-shade').remove();
            });
        });

        // Check is an entry is selected before form submission
        $(document).on('submit', '#approve-publish-form', function(e) {
            $selected = $('.clone:checkbox:checked').length;
            if ($selected > 0) {
                return true;
            }
            return false;
        });

        // Modal checkboxes behaviour script
        $(document).on('click, change', '.clone:checkbox', function() {
            $value = $(this).val();
            $selected = $('.clone:checkbox:checked').length;
            $all = $('.clone:checkbox').length;
            if ($value !== 'on') {
                if ($selected === ($all-1)) {
                    $('#element-0-clone').prop('checked', this.checked);
                }
                return;
            }
            $('.clone:checkbox').prop('checked', this.checked);
        });

        // TODO: Not working need to fix.
        // Close Modal on close icon click
        $(document).on('click', '#close-publish-modal', function() {
            $('.modal.scroll-y-auto, .modal-shade').hide();
        });
    },

    _buildPublishModal: function() {
        var $selections = Craft.Translations.OrderEntries.getSelections();

        var $modal = $('<div/>', {
            'class' : 'modal scroll-y-auto',
        });

        var $form = $('<form/>', {
            'id' : 'approve-publish-form',
            'method' : 'post'
        });

        var $hiddenFields = $('<input type="hidden" name="orderId" value="' + $('input[name=orderId]').val() + '"/>\
            <input type="hidden" name="action" value="translations/order/save-draft-and-publish"/>');
        $hiddenFields.appendTo($form);
        $form.append(Craft.getCsrfInput());

        $body = $('<div class="body input ltr"></div>');

        var $header = $('<div class="header df position-fixed"><h1 class="mr-auto">Review and publish</h1>\
            <a class="icon delete close-publish-modal" id="close-publish-modal"></a></div>');
        $header.appendTo($form);

        var $table = $('<table class="data mt-4" dir="ltr"></table>');

        var $tableHeader = $('<thead><tr><td class="thin checkbox-cell translations-checkbox-cell">\
            <input class="checkbox clone" id="element-0-clone" type="checkbox"/>\
            <label class="checkbox" for="element-0-clone"></label></td><td><b>Select all</b></td><td></td><td></td><td></td>\
            <td><button type="submit" name="submit" class="btn right" value="draft">Approve changes</button></td>\
            <td><button type="submit" name="submit" class="btn ml-10 submit right" value="publish">Publish selected</button>\
            </td></tr></thead>');

        var $tableContent = $('<tbody></tbody>');

        $selections.each(function() {
            $cloneId = "element-" + $(this).val() + "-clone";
            $clone = $(this).closest('tr').clone();
            $clone.find('input').attr('id', $cloneId);
            $clone.find('input').addClass('clone');
            $clone.find('label').attr('for', $cloneId);
            $clone.appendTo($tableContent);
        });

        $tableHeader.appendTo($table);
        $tableContent.appendTo($table);

        $table.appendTo($body);
        $body.appendTo($form);
        $form.appendTo($modal)

        return $modal;
    }
};

$(function() {
    Craft.Translations.OrderEntries.init();
});

})(jQuery);