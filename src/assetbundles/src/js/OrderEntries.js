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
    createRowClone: function(that) {
        $clone = $(that).closest('tr.diff-clone').clone();
        $fileId = $clone.data("file-id");

        $checkboxClone = $clone.find("td.translations-checkbox-cell");
        $clone.find("td.translations-checkbox-cell").remove();
        $clone.find("td:nth-child(4)").remove();

        $checkBoxCell = $('<td>', {
            class: "thin checkbox-cell translations-checkbox-cell"
        });

        $checkbox = $('<input>', {
            type: "checkbox",
            id: "file-"+$fileId+"-clone",
            class: "checkbox clone",
            name: "elements[]",
            value: $fileId,
            "data-element": $clone.data("element-id")
        });

        $clone.find("td").addClass("diff-clone-row");
        $clone.find("input[type=checkbox]").addClass("clone");
        $clone.addClass("clone-modal-tr");

        $status = $clone.find("td .status").data("status") == 1;
        $isApplied = $.trim($clone.find("td .status").parent("td").text()) == "Applied";

        if ($status) {
            $icon = $clone.find("td .icon");
            $icon.removeClass("hidden");
        }

        if (this.$selectedFileIds) {
            this.$selectedFileIds = this.$selectedFileIds +","+ $fileId;
        } else {
            this.$selectedFileIds = $fileId;
        }

        if (! $status || $isApplied) {
            $checkbox.attr("disabled", "disabled");
        }
        $checkbox.appendTo($checkBoxCell);

        $('<label>', {
            "for": "file-"+$fileId+"-clone",
            "class": "checkbox"
        }).appendTo($checkBoxCell);

        $clone.find('td:first').before($checkBoxCell);

        $clone.wrapInner( "<td colspan='100%' style='padding: 0; border:none;'><table class='fullwidth'><tbody class='clone-modal-tbody'><tr></tr></tbody></table><td>" );

        return $clone;
    },
    setFileIds: function() {
        $fileIds = null;
        $('.clone:checkbox:checked').each(function() {
            if (this.id != "element-0-clone") {
                if ($fileIds) {
                    $fileIds = $fileIds + "," + $(this).val();
                } else {
                    $fileIds = $(this).val();
                }
            }
        });
        $("input[name=fileIds]").val($fileIds);
    },
    setElementIds: function() {
        $elementIds = [];
        $(".clone-modal-tr").each(function() {
            if ($(this).find("input:checkbox:checked").length == 1) {
                if($.inArray($(this).data("element-id"), $elementIds) === -1) {
                    $elementIds.push($(this).data("element-id"));
                }
            }
        });
        $("input[name=elementIds]").val($elementIds.join(",").replace(/^,|,$/g,''));
    },
    showFirstTdComparison: function() {
        $row = $(".modal.elementselectormodal").find("tr.clone-modal-tr");
        $row.each(function() {
            if ($(this).find(".status").data("status") == 1) {
                self._addDiffViewEvent(this);
                $(this).find(".icon").removeClass("desc");
                $(this).find(".icon").addClass("asc");
                return false;
            }
        });
    },
    copyTextToClipboard: function(event) {
        var txt = $( event.currentTarget ).parent().children('.diff-bl').text();
        navigator.clipboard.writeText(txt).then(function() {
            Craft.cp.displayNotice(Craft.t('app', 'Copied to clipboard.'));
        }, function(err) {
            Craft.cp.displayError(Craft.t('app', 'Could not copy text: ', err));
        });
    },

    init: function() {
        self = this;
        this.$publishSelectedBtn = $('#draft-publish');
        this.$formId = 'publish-form';
        this.$form = $('#' + this.$formId);
        this.$selectAllCheckbox = $('.select-all-checkbox :checkbox');
        this.$checkboxes = $('tbody .translations-checkbox-cell :checkbox').not('[disabled]');

        this.$selectAllCheckbox.on('change', function() {
            self.toggleSelected($(this).is(':checked'));
        });

        this.$checkboxes.on('change', function() {
            self.togglePublishButton();
            self.toggleSelectAllCheckbox();
        });

        this.$publishSelectedBtn.on('click', function () {
            var form = self._buildPublishModal();
            var $modal = new Garnish.Modal(form, {
                closeOtherModals : false,
                resizable: true
            });
            self.showFirstTdComparison();
            
            // Destroy the modal that is being hided as a new modal will be created every time
            $modal.on('hide', function() {
                $('.modal.elementselectormodal, .modal-shade').remove();
            });
        });

        // Modal checkboxes behaviour script
        $(document).on('click, change', '.clone:checkbox', function() {
            $value = $(this).val();
            $selected = $('tbody .clone:checkbox:checked').length;
            if ($selected == 0) {
                self.toggleApprovePublishButton(false)
            } else {
                if ($value != "on") {
                    self.toggleApprovePublishButton(true)
                }
            }
            $all = $('.clone:checkbox').not("[disabled]").length;
            if ($value !== 'on') {
                if ($selected === ($all-1)) {
                    $('#element-0-clone').prop('checked', this.checked);
                } else {
                    $('#element-0-clone').prop('checked', false);
                }
                self.setFileIds();
                self.setElementIds();
                return;
            } else {
                if ($('tbody .clone:checkbox').not("[disabled]").length > 0) {
                    self.toggleApprovePublishButton(this.checked);
                    $('.clone:checkbox').not("[disabled]").prop('checked', this.checked);
                    self.setFileIds();
                    self.setElementIds();
                }
            }
        });
    },

    _buildPublishModal: function() {
        var $selections = self.getSelections();

        var $modal = $('<div/>', {
            'class' : 'modal elementselectormodal',
        });

        var $form = $('<form/>', {
            'id' : 'approve-publish-form',
            'method' : 'post'
        });

        var $hiddenFields = $('<input type="hidden" name="orderId" value="' + $('input[name=orderId]').val() + '"/>\
            <input type="hidden" name="fileIds" value=""/><input type="hidden" name="elementIds" value=""/>\
            <input type="hidden" name="isProcessing" value="1"/>\
            <input type="hidden" name="action" value="translations/order/save-draft-and-publish"/>');
        $hiddenFields.appendTo($form);
        $form.append(Craft.getCsrfInput());

        $body = $('<div class="body pt-10" style="position: absolute; overflow: scroll;height: calc(100% - 132px);"></div>');

        var $header = $('<div class="header df"><h1 class="mr-auto">Review changes</h1></div>');
        var $footer = $('<div class="footer"><div class="select-all-checkbox"></div><div class="buttons right"></div></div>');
        var $draftButton = $('<button type="submit" name="submit" class="btn apply-translation disabled" style="margin:0 5px;" disabled value="draft">Merge into draft</button>');
        var $selectAllCheckbox = $('<input class="checkbox clone" id="element-0-clone" type="checkbox"/><label class="checkbox" for="element-0-clone">Select all</label>');
        var $publishButton = $('<button type="submit" name="submit" class="btn submit apply-translation disabled" style="margin:0 5px;" disabled value="publish">Merge and apply draft</button>');
        var $closeIcon = $('<a class="icon delete close-publish-modal" id="close-publish-modal" style="margin-left:15px;"></a>');
        
        $($closeIcon).on('click', function() {
            $('.modal.elementselectormodal, .modal-shade').remove();
        });

        $($draftButton).on('click', function() {
            $draftButton.addClass('disabled').css('pointer-events', 'none');
            $publishButton.addClass('disabled').css('pointer-events', 'none');
        });

        $($publishButton).on('click', function() {
            $draftButton.addClass('disabled').css('pointer-events', 'none');
            $publishButton.addClass('disabled').css('pointer-events', 'none');
        });

        $selectAllCheckbox.appendTo($footer.find('.select-all-checkbox'));
        $draftButton.appendTo($footer.find('.buttons'));
        $publishButton.appendTo($footer.find('.buttons'));
        $closeIcon.appendTo($header);
        $header.appendTo($form);
        $footer.appendTo($form);
        $('<div class="resizehandle"></div>').appendTo($form);

        var $table = $('<table class="data fullwidth" dir="ltr" style="border-spacing: 0 1em;"></table>');

        var $tableContent = $('<tbody></tbody>');

        $selections.each(function() {
            $clone = self.createRowClone(this);
            $clone.appendTo($tableContent);
            $('<tr>', {
                id: "data-"+$($clone).data("file-id")
            }).appendTo( $clone.find(".clone-modal-tbody") );
        });

        $($tableContent).on('click', '.diff-clone-row', function(e) {
            $row = $(this).closest('tr.diff-clone');
            isCollapsable = $row.find(".status").data("status") == 1;

            if (! isCollapsable) {
                return;
            }

            $fileId = $row.data("file-id");
            $target = $("#data-"+$fileId);
            $icon = $row.find(".icon");
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
                self._addDiffViewEvent($row);
                $($icon).removeClass("desc");
                $($icon).addClass("asc");
            }
        });

        $tableContent.appendTo($table);

        $table.appendTo($body);
        $body.appendTo($form);
        $form.appendTo($modal)

        return $modal;
    },
    _addDiffViewEvent: function(that) {
        $fileId = $(that).data("file-id");
        if ($fileId == undefined) {
            return;
        }
        var fileData = {
            fileId: $fileId
        };

        Craft.postActionRequest('translations/files/get-file-diff', fileData, function(response, textStatus) {
            if (textStatus === 'success' && response.success) {
                data = response.data;

                diffHtml = self.createDiffHtmlView(data);
                diffHtml.attr("id", "data-"+$fileId)
                $("#data-"+$fileId).replaceWith(diffHtml);
                diffHtml.show();
            } else {
                Craft.cp.displayNotice(Craft.t('app', response.error));
            }
            // Copy text to clipboard
            var $copyBtn = $('.diff-copy');
            $($copyBtn).on('click', function(event) {
                self.copyTextToClipboard(event);
            });
        });
    },
    createDiffHtmlView: function(data) {
        var diffData = data.diff;

        let tabBarClass = diffData == undefined ? 'hidden' : '';

        let previewClass = data.previewClass;
        let previewTitle = previewClass == 'disabled' ? "Preview not available" : 'Preview';
        let previewTabStyle = previewClass == 'disabled' ? 'style="cursor: default;"' : '';

        $mainContent = $('<tr>', {
            id: "main-container"
        });
        $mainContent.addClass('file-diff-data');

        $previewTab = $('<nav id="acc-tabs" class="preview pane-tabs '+tabBarClass+'">\
            <ul>\
                <li data-id="xml">\
                    <a id="xml-'+data.fileId+'" class="tab sel tab-xml" title="XML">Text</a>\
                </li>\
                <li data-id="preview" class='+previewClass+' '+previewClass+'>\
                    <a id="preview-'+data.fileId+'" class="tab tab-visual" title="'+previewTitle+'" '+previewTabStyle+'">Preview</a>\
                </li>\
            </ul>\
        </nav>');

        $diffTable = $('<table>', {
            id: "diffTable-"+data.fileId,
            class: "diffTable"
        });
        $mainTd = $('<td colspan=8>');
        $mainTd.css({
            "border": "none",
            "padding": "0",
        });
        $previewTab.appendTo($mainTd);
        $diffTable.appendTo($mainTd);
        $previewContent = $('<div id="visual-'+data.fileId+'" class="visual" style="display: none; position: relative;">\
            <span class="iframe-line"> </span>\
            <iframe class="original" id="original-url" src="'+data.originalUrl+'"></iframe>\
            <iframe style="float: right" class="new" id="new-url" src="'+data.newUrl+'"></iframe>\
        </div>');
        $previewContent.appendTo($mainTd);
        $mainTd.appendTo($mainContent);

        $.each(diffData, function(key, value) {
            $tr = $('<tr>');
            $td = $("<td class='source'>");
            $td.appendTo($tr);
            $("<label>", {
                html: key+" :",
                class: "diff-tl"
            }).appendTo($td);
            $("<div>", {
                html: '<svg height="1em" viewBox="0 0 24 24" width="1em"><path d="M0 0h24v24H0z" fill="none"></path><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"></path></svg>',
                class: 'diff-copy'
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
            $("<div>", {
                html: '<svg height="1em" viewBox="0 0 24 24" width="1em"><path d="M0 0h24v24H0z" fill="none"></path><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"></path></svg>',
                class: 'diff-copy'
            }).appendTo($td);
            $('<br>').appendTo($td);
            $("<span>", {
                html: value.target,
                class: "diff-bl"
            }).appendTo($td);
            $tr.appendTo($diffTable);
        });

        $previewTab.on('click', 'li', function() {
            let tab = $(this);
            let tabName = tab.data('id');
            let tabAnchor = tab.find('a');
            let tabFileId = tabAnchor.prop('id').split('-')[1];
            if (tabAnchor.hasClass('sel') || tab.hasClass('disabled')) {
                return;
            }

            if (tabName == 'xml') {
                $('#xml-'+tabFileId).addClass('sel');
                $('#preview-'+tabFileId).removeClass('sel');
                $('#visual-'+tabFileId).hide();
                $('#diffTable-'+tabFileId).show();
            } else {
                $('#xml-'+tabFileId).removeClass('sel');
                $('#preview-'+tabFileId).addClass('sel');
                $('#diffTable-'+tabFileId).hide();
                $('#visual-'+tabFileId).show();
            }
        });

        return $mainContent;
    }
};

$(function() {
    Craft.Translations.OrderEntries.init();
});

})(jQuery);