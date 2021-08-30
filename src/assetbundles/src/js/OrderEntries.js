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
    $selectedFileIds: null,

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
    toggleApprovePublishButton: function(state) {
        if (state) {
            $(".apply-translation").prop('disabled', false).removeClass('disabled');
        } else {
            $(".apply-translation").prop('disabled', true).addClass('disabled');
        }
    },
    createRowClone: function(that, $cloneId) {
        $clone = $(that).clone();
        $clone.find("td[rowspan]").remove();

        $clone.find("td").addClass("diff-clone-row");
        $status = $clone.find("td .status").data("status") == 1;
        $isApplied = $.trim($clone.find("td .status").parent("td").text()) == "Applied";

        if ($status) {
            $icon = $clone.find("td .icon");
            $icon.removeClass("hidden");
        }


        $checkBoxCell = $('<td>', {
            class: "thin checkbox-cell translations-checkbox-cell"
        });
        $fileId = $(that).data("file-id");
        if (this.$selectedFileIds) {
            this.$selectedFileIds = this.$selectedFileIds +","+ $fileId;
        } else {
            this.$selectedFileIds = $fileId;
        }

        $checkbox = $('<input>', {
            "type": "checkbox",
            "id": $fileId,
            "class": "checkbox clone",
            "name": "elements[]",
            "value": $cloneId,
        });
        // ! NOTE: Keep check boxes unchecked as file and element ids are added when checked.
        // $checkbox.prop("checked", true);
        if (! $status || $isApplied) {
            $checkbox.attr("disabled", "disabled");
        }
        $checkbox.appendTo($checkBoxCell);

        $('<label>', {
            "for": $fileId,
            "class": "checkbox"
        }).appendTo($checkBoxCell);

        $clone.find('td:first').before($checkBoxCell);
        $elementId = $cloneId.split("-")[1];
        $clone.attr('data-element-id', $elementId);
        return $clone;
    },
    setFileIds: function() {
        $fileIds = null;
        $('.clone:checkbox:checked').each(function() {
            if (this.id != "element-0-clone") {
                if ($fileIds) {
                    $fileIds = $fileIds + "," + this.id;
                } else {
                    $fileIds = this.id;
                }
            }
        });
        $("input[name=fileIds]").val($fileIds);
    },
    setElementIds: function() {
        $elementIds = [];
        $(".diff-clone").each(function() {
            if ($(this).find("input:checkbox:checked").length == 1) {
                if($.inArray($(this).data("element-id"), $elementIds) === -1) {
                    $elementIds.push($(this).data("element-id"));
                }
            }
        });
        $("input[name=elementIds]").val($elementIds.join(",").replace(/^,|,$/g,''));
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

        // Modal checkboxes behaviour script
        $(document).on('click, change', '.clone:checkbox', function() {
            $value = $(this).val();
            $selected = $('tbody .clone:checkbox:checked').length;
            if ($selected == 0) {
                Craft.Translations.OrderEntries.toggleApprovePublishButton(false)
            } else {
                if ($value != "on") {
                    Craft.Translations.OrderEntries.toggleApprovePublishButton(true)
                }
            }
            $all = $('.clone:checkbox').not("[disabled]").length;
            if ($value !== 'on') {
                if ($selected === ($all-1)) {
                    $('#element-0-clone').prop('checked', this.checked);
                } else {
                    $('#element-0-clone').prop('checked', false);
                }
                Craft.Translations.OrderEntries.setFileIds();
                Craft.Translations.OrderEntries.setElementIds();
                return;
            } else {
                if ($('tbody .clone:checkbox').not("[disabled]").length > 0) {
                    Craft.Translations.OrderEntries.toggleApprovePublishButton(this.checked);
                    $('.clone:checkbox').not("[disabled]").prop('checked', this.checked);
                    Craft.Translations.OrderEntries.setFileIds();
                    Craft.Translations.OrderEntries.setElementIds();
                }
            }
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
            <input type="hidden" name="fileIds" value=""/><input type="hidden" name="elementIds" value=""/>\
            <input type="hidden" name="action" value="translations/order/save-draft-and-publish"/>');
        $hiddenFields.appendTo($form);
        $form.append(Craft.getCsrfInput());

        $body = $('<div class="body input ltr"></div>');

        var $header = $('<div class="header df position-fixed"><h1 class="mr-auto">Review and publish</h1></a></div>');
        var $closeIcon = $('<a class="icon delete close-publish-modal" id="close-publish-modal">');
        
        $($closeIcon).on('click', function() {
            $('.modal.scroll-y-auto, .modal-shade').remove();
        });

        $closeIcon.appendTo($header);
        $header.appendTo($form);

        var $table = $('<table class="data mt-4" dir="ltr"></table>');

        var $tableHeader = $('<thead><tr><td class="thin checkbox-cell translations-checkbox-cell">\
            <input class="checkbox clone" id="element-0-clone" type="checkbox"/>\
            <label class="checkbox" for="element-0-clone"></label></td><td><b>Select all</b></td><td></td><td></td><td></td>\
            <td><button type="submit" name="submit" class="btn right apply-translation disabled" disable="disabled" value="draft">Approve changes</button></td>\
            <td><button type="submit" name="submit" class="btn ml-auto submit right apply-translation disabled" disable="disabled" value="publish">Publish selected</button>\
            </td><td></td></tr></thead>');

        var $tableContent = $('<tbody></tbody>');

        $selections.each(function() {
            $cloneId = "element-" + $(this).val() + "-clone";
            $rowClass = $(this).data('detail');
            $("."+$rowClass).each(function() {
                $clone = Craft.Translations.OrderEntries.createRowClone(this, $cloneId);
                $clone.appendTo($tableContent);
                $('<tr>', {
                    id: "data-"+$($clone).data("file-id"),
                    // type: "hidden"
                }).appendTo($tableContent);
            });
        });

        $($tableContent).on('click', '.diff-clone-row', function(e) {
            isCollapsable = $(this).closest('tr').find(".status").data("status") == 1;

            if (! isCollapsable) {
                return;
            }

            $fileId = $(this).closest('tr').find("input").attr("id");
            $target = $("#data-"+$fileId);
            $icon = $(this).closest('tr').find(".icon");
            if ($target.children().length > 0) {
                $target.toggle();
                if ($target.is(":visible")) {
                    $($icon).removeClass("desc");
                    $($icon).addClass("asc");
                } else {
                    $($icon).removeClass("asc");
                    $($icon).addClass("desc");
                }
            } else {
                Craft.Translations.OrderEntries._addDiffViewEvent(this);
                $($icon).removeClass("desc");
                $($icon).addClass("asc");
            }
        });

        $tableHeader.appendTo($table);
        $tableContent.appendTo($table);

        $table.appendTo($body);
        $body.appendTo($form);
        $form.appendTo($modal)

        return $modal;
    },
    _addDiffViewEvent: function(that) {
        $fileId = $(that).closest('tr').find("input").attr("id");
        if ($fileId == undefined) {
            return;
        }
        var fileData = {
            fileId: $fileId
        };

        Craft.postActionRequest('translations/order/get-file-diff', fileData, function(response, textStatus) {
            if (textStatus === 'success' && response.success) {
                data = response.data;

                diffHtml = Craft.Translations.OrderEntries.createDiffHtmlView(data);
                diffHtml.attr("id", "data-"+$fileId)
                $("#data-"+$fileId).replaceWith(diffHtml);
                diffHtml.show();
            } else {
                Craft.cp.displayNotice(Craft.t('app', response.error));
            }
        });
    },
    createDiffHtmlView: function(data) {
        var diffData = data.diff;

        $mainContent = $('<tr>', {
            id: "main-container"
        });

        $diffTable = $('<table>', {
            id: "diffTable"
        });
        $mainTd = $('<td colspan=8>');
        $diffTable.appendTo($mainTd);
        $mainTd.appendTo($mainContent);

        $.each(diffData, function(key, value) {
            $tr = $('<tr>');
            $td = $("<td class='source'>");
            $td.appendTo($tr);
            $("<label>", {
                html: key+" :",
                class: "diff-tl"
            }).appendTo($td);
            $('<br>').appendTo($td);
            $("<span>", {
                html: value.source,
                class: "diff-bl"
            }).appendTo($td);
            $td = $("<td class='target'>").appendTo($tr);
            $td.appendTo($tr);
            $("<label>", {
                html: key+" :",
                class: "diff-tl"
            }).appendTo($td);
            $('<br>').appendTo($td);
            $("<span>", {
                html: value.target,
                class: "diff-bl"
            }).appendTo($td);
            $tr.appendTo($diffTable);
        });

        return $mainContent;
    }
};

$(function() {
    Craft.Translations.OrderEntries.init();
});

})(jQuery);