(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }
    // Defaults to open file tab on detail page
    var isSubmitted = $("#order-attr").data("submitted");
    var isNew = $("#order-attr").data("status") === "new";
    var isFailed = $("#order-attr").data("status") === "failed";

    function validateForm() {
        var buttonStatus = true;
        var $entries = $('#currentElementIds').val().split(',');
        if ($entries[0] == "") {
            $entries.splice(0);
        }
        var $targetLang = $(':checkbox[name="targetSites[]"]:checked');

        if ($('#title').val() == "") {
            buttonStatus = false;
        }

        if ($entries.length < 1) {
            buttonStatus = false;
        }

        if ($("#sourceSiteSelect").val() == "") {
            buttonStatus = false;
        }

        if ($targetLang.length == 0) {
            buttonStatus = false;
        }
        return buttonStatus;
    }

    function setSubmitButtonStatus(status) {
        if (status) {
            $('.translations-dropdown .btn').removeClass('disabled');
            $('.translations-dropdown .btn').attr('disabled', false);
        } else {
            $('.translations-dropdown .btn').addClass('disabled');
            $('.translations-dropdown .btn').attr('disabled', true);
        }
    }

    function removeParams($url) {

        if ($url.includes("#order") || $url.includes("#files")) {
            $url = $url.replace("#order", "");
            $url = $url.replace("#files", "");
        }

        if ($url.includes("?")) {
            $url = $url.split("?")[0];
        }

        return $url;
    }

    function haveDifferences($old, $new) {
        var diff = $($old).not($new).get();
        if (diff == "" && $new.length == $old.length) {
            return false;
        }

        return true;
    }

    function isOrderChanged($data) {
        for(var key in $data) {
            // Validate Target Sites
            if (key == "target" || key == "all") {
                var $originalTargetSiteIds = $('#originalTargetSiteIds').val().replace(/[\[\]\"]/g, '');
                var $all = $(':checkbox[name="targetSites"]');
                var $checkboxes = $all.is(':checked') ? $(':checkbox[name="targetSites[]"]') : $(':checkbox[name="targetSites[]"]:checked');
                var targetSites = [];

                $checkboxes.each(function() {
                    var $el = $(this);
                    var val = $el.attr('value');
                    targetSites.push(val);
                });
                if (haveDifferences($originalTargetSiteIds.split(","), targetSites)) {
                    return true;
                }
            }
            // Validate Source Site
            if (key == "source" || key == "all") {
                var originalSourceSite = $('#originalSourceSiteId').val();
                var site = $("#sourceSiteSelect").val();
                if (typeof originalSourceSite !== 'undefined') {
                    originalSourceSite = originalSourceSite.split(",");
                }

                if (haveDifferences(originalSourceSite, site.split(","))) {
                    return true;
                }
            }
            // Validate Entries
            if (key == "entry" || key == "all") {
                $originalElementIds = $('#originalElementIds').val().split(',');
                var currentElementIds = $('#currentElementIds').val().split(",");

                if (haveDifferences($originalElementIds, currentElementIds)) {
                    return true;
                }
            }
            // Validate Title
            if (key == "title" || key == "all") {
                $originalTitle = $('#originalTitle').val();
                var currentTitle = $('#title').val();

                if (typeof $originalTitle !== 'undefined') {
                    $originalTitle = $originalTitle.split(",");
                }

                if (haveDifferences($originalTitle, currentTitle.split(","))) {
                    return true;
                }
            }
            // Validate Translator
            if (key == "translator" || key == "all") {
                $originalTranslatorId = $('#originalTranslatorId').val().split(',');
                var currentTranslatorId = $('#translatorId').val().split(",");

                if (haveDifferences($originalTranslatorId, currentTranslatorId)) {
                    return true;
                }
            }
            // Validate Due Date
            if (key == "dueDate" || key == "all") {
                $originalDueDate = $('#originalRequestedDueDate').val();
                if (typeof $originalDueDate !== 'undefined') {
                    $originalDueDate = $originalDueDate.split(',');
                }
                var currentDueDate = $('#requestedDueDate').val();
                if (typeof currentDueDate !== 'undefined') {
                    var currentDueDate = currentDueDate.split(",");
                }

                if ($originalDueDate && currentDueDate && haveDifferences($originalDueDate, currentDueDate)) {
                    return true;
                }
            }
            // Validate Comments
            if (key == "comments" || key == "all") {
                $originalComments = $('#originalComments').val().split(',');
                var currentComments = $('#comments').val().split(",");

                if (haveDifferences($originalComments, currentComments)) {
                    return true;
                }
            }
            // Validate order tags
            if (key== "tags" || key == "all") {
                $originalTags = [];
                if ($('#originalTags').val() != "") {
                    $originalTags = $('#originalTags').val().split(',');
                }
                $currentTags = [];
                $("div .removable").each(function() {
                    $currentTags.push($(this).data("label"));
                });
                if (haveDifferences($originalTags, $currentTags)) {
                    return true;
                }
            }
        };

        return false;
    }

    function setButtonText($button, $value) {
        var $isSubmittedOrder = $('#order-attr').data('submitted');
        if (! $isSubmittedOrder) {
            return;
        }
        $($button).text($value);
    }

    function getFieldValuesAsUrlParams() {
        title = $('#title').val();
        translatorId = $('#translatorId').val();
        targetSites = '';
        var $all = $(':checkbox[name="targetSites"]');
        var $checkboxes = $all.is(':checked') ? $(':checkbox[name="targetSites[]"]') : $(':checkbox[name="targetSites[]"]:checked');
        $checkboxes.each(function() {
            var $el = $(this);
            var val = $el.attr('value');
            targetSites += '&targetSite[]='+val;
        });
        dueDate = $('#requestedDueDate-date').val();
        comments = $('#comments').val();
        url = '';
        if (typeof title !== undefined && title !== '') {
            url += '&title='+title
        }
        if (typeof dueDate !== undefined && dueDate != undefined) {
            url += '&dueDate='+dueDate
        }
        if (typeof comments !== undefined && comments!== '') {
            url += '&comments='+comments
        }
        if (typeof translatorId !== undefined && translatorId!== '') {
            url += '&translatorId='+translatorId
        }
        if (targetSites !== '') {
            url += targetSites
        }
        return url
    }

    function isAlreadyAdded($newTag) {
        var isNew = false;
        if ($newTag == "") {
            return isNew;
        }
        $("div .removable").each(function() {
            if ($(this).data("label").toLowerCase() === $newTag.toLowerCase()) {
                isNew = true;
            }
        });
        return isNew;
    }

    function sendingOrderStatus($status) {
        if ($status) {
            $('.translations-loader').removeClass('hidden');
            $('.translations-dropdown .btn').addClass('disabled').css('pointer-events', 'none');
            $('.translations-dropdown .btn').prop('disabled', true);
        } else {
            $('.translations-loader').addClass('hidden');
            $('.translations-dropdown .btn').removeClass('disabled').css('pointer-events', '');
            $('.translations-dropdown .btn').prop('disabled', false);
        }
    }

    function toggleSelections($toggle) {
        $(':checkbox[name="targetSites[]"]').prop('checked', $toggle);
    }

    Craft.Translations.OrderDetails = {
        init: function() {
            self = this;
            if (isSubmitted) {
                this._createUpdateOrderButtonGroup();
            } else {
                this._createNewOrderButtonGroup();
            }
            if (validateForm() && (isNew || isFailed || isOrderChanged({all: "all"}))) {
                setSubmitButtonStatus(true);
            }
            // Target lang Ajax
            $(':checkbox[name="targetSites[]"], :checkbox[name="targetSites"]').on('change', function() {
                var targetSites = [];
                if ($(this).attr('name') == "targetSites") {
                    toggleSelections($(this).is(':checked'));
                } else {
                    var $all = $(':checkbox[name="targetSites[]"]');
                    var $checkboxes = $(':checkbox[name="targetSites[]"]:checked');
                    if ($all.length == $checkboxes.length) {
                        $(':checkbox[name=targetSites]').prop('checked', true);
                        $(':checkbox[name="targetSites[]"]').prop('disabled', true);
                    }
                    var targetSitesLabels = [];
    
                    $checkboxes.each(function() {
                        var $el = $(this);
                        var val = $el.attr('value');
                        var label = $.trim($el.next('label').text());
                        targetSites.push(val);
                        targetSitesLabels.push(label);
                    });
                
                    $('[data-order-attribute=targetSites]').html(targetSitesLabels.join(', '));
                }

                var $originalTargetSiteIds = $('#originalTargetSiteIds').val().replace(/[\[\]\"]/g, '');
                if (isSubmitted) {
                    if (haveDifferences($originalTargetSiteIds.split(","), targetSites)) {
                        setButtonText('.translations-submit-order.submit', 'Create new order');
                    } else {
                        if (! isOrderChanged({source: 'source', entry: 'entry'})) {
                            setButtonText('.translations-submit-order.submit', 'Update order');
                        }
                    }
                }

                if (validateForm() && (isNew || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('select[name=version]').on('change', function() {
                var elementVersions = $('#elementVersions').val().split(",");
                var elementId = $(this).attr('id').replace('version_', '');
                var $key = elementId+'_'+$(this).val();
                elementVersions = elementVersions.filter(function(val, key) {
                    return !val.startsWith(elementId+'_');
                });

                elementVersions.push($key);
                elementVersions.join(',');

                $('#elementVersions').val(elementVersions);
            });

            $('li[data-id]').on('click', function() {
                if ($(this).data('id') == "files") {
                    if (isNew) {
                        return false;
                    }
                    setSubmitButtonStatus(false);
                } else {
                    if (validateForm() && isOrderChanged({all: "all"})) {
                        setSubmitButtonStatus(true);
                    }
                }
            });

            $('#title').on('change, keyup', function() {
                if (validateForm() && (isNew || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('#translatorId').on('change', function() {
                if (validateForm() && isOrderChanged({all: "all"})) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('#createNewOrder').on('click', function () {
                window.location.href = "/admin/translations/orders/create";
            });

            // Delete an entry
            $('.translations-order-delete-entry').on('click', function(e) {
                var $button = $(this);
                var $table = $button.closest('table');
                var $row = $button.closest('tr');
    
                e.preventDefault();
    
                if (confirm(Craft.t('app', 'Are you sure you want to remove this entry from the order?'))) {
                    $row.remove();
    
                    if ($table.find('tbody tr').length === 0) {
                        $table.remove();
                    }

                    var entriesCount = $('input[name="elements[]"]').length;

                    if (entriesCount === 0) {
                        $('.translations-order-submit').addClass('disabled').prop('disabled', true);
                    }

                    var wordCount = 0;

                    $('[data-word-count]').each(function() {
                        wordCount += Number($(this).data('word-count'));
                    });

                    $('[data-order-attribute=entriesCount]').text(entriesCount);
    
                    $('[data-order-attribute=wordCount]').text(wordCount);

                    var currentElementIds = $('#currentElementIds').val().split(",");
                    currentElementIds = currentElementIds.filter(function(itm, i, currentElementIds) {
                        if (itm != "" && itm != $button.attr('data-element')) {
                            return i == currentElementIds.indexOf(itm);
                        }
                    }).join(",");

                    $originalElementIds = $('#originalElementIds').val().split(',');

                    if (haveDifferences($originalElementIds, currentElementIds.split(","))) {
                        setButtonText('.translations-submit-order.submit', 'Submit new order');
                    } else {
                        if (! isOrderChanged({source: 'source', target: 'target'})) {
                            setButtonText('.translations-submit-order.submit', 'Update order');
                        }
                    }

                    if (currentElementIds != "" && validateForm()) {
                        setSubmitButtonStatus(true);
                    } else {
                        setSubmitButtonStatus(false);
                    }

                    $('#currentElementIds').val(currentElementIds);
                }
            });
    
            // Source Site Ajax
            $("#sourceSiteSelect").change(function (e) {
                $(window).off('beforeunload.windowReload');
                var site = $("#sourceSiteSelect").val();
                var url = window.location.href.split('?')[0];
    
                var currentElementIds = [];
                if (typeof $('#currentElementIds').val() !== 'undefined') {
                    currentElementIds = $('#currentElementIds').val().split(',');
                }

                if (site != "") {
                    url += '?sourceSite='+site;
                }
                
                if (currentElementIds.length > 1) {
                    currentElementIds.forEach(function (element) {
                        url += '&elements[]='+element;
                    })
                }

                originalSourceSite = $('#originalSourceSiteId').val();
                if (typeof originalSourceSite !== 'undefined') {
                    originalSourceSite = originalSourceSite.split(",");
                }

                if (haveDifferences(originalSourceSite, site.split(","))) {
                    setButtonText('.translations-submit-order.submit', 'Submit new order');
                } else {
                    if (! isOrderChanged({target: 'target', entry: 'entry'})) {
                        setButtonText('.translations-submit-order.submit', 'Update order');
                    }
                }

                if (validateForm() && isOrderChanged({all: "all"})) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }

                window.history.pushState("", "", url);
                // window.location = url;

            });

            $('.translations-order-form').on('submit', function(e) {
                if (! validateForm()) {
                    return false;
                }
            });

            $("input[id^=requestedDueDate]").datepicker('option', 'minDate', +1);

            $(".addEntries").on('click', function (e) {
    
                elementIds = currentElementIds = [];
    
                var site = $("#sourceSiteSelect").val();
    
                var currentElementIds = [];

                if (typeof $('#currentElementIds').val() !== 'undefined') {
                    currentElementIds = $('#currentElementIds').val().split(',');
                }

                var $url = removeParams(window.location.href);
    
                this.assetSelectionModal = Craft.createElementSelectorModal('craft\\elements\\Entry', {
                    storageKey: null,
                    sources: null,
                    elementIndex: null,
                    criteria: {siteId: this.elementSiteId},
                    multiSelect: 1,
                    disabledElementIds: currentElementIds,
    
                    onSelect: $.proxy(function(elements) {
                        $('#content').addClass('elements busy');
                        if (elements.length) {
                            var elementUrl = '';
                            for (var i = 0; i < elements.length; i++) {
                                var element = elements[i];
                                elementIds.push((element.id).toString());
                                elementUrl += '&elements[]='+element.id;

                                if (Array.isArray(currentElementIds)) {
                                    index = currentElementIds.indexOf(element.id.toString());
                                    if (index > -1) {
                                        currentElementIds.splice(index, 1);
                                    }
                                }
                            }

                            for (var i = 0; i < currentElementIds.length; i++) {
                                if (currentElementIds[i]) {
                                    elementUrl += '&elements[]='+currentElementIds[i];
                                    elementIds.push(currentElementIds[i].toString());
                                }
                            }

                            $originalElementIds = $('#originalElementIds').val().split(',');

                            if (haveDifferences($originalElementIds, elementIds)) {
                                elementUrl += "&changed=1";
                            }

                            fieldValues = getFieldValuesAsUrlParams()

                            window.location.href = $url + '?sourceSite='+site+elementUrl+fieldValues;
                        }
                    }, this),
                    closeOtherModals: false,
                });
            });

            // Add new tags to order tags fields
            $("#fields-tags").on('keydown', 'input[type=text]', function(e) {
                if (e.keyCode == 13 && !isAlreadyAdded($(this).val())) {
                    $data = {'title': $(this).val()};
                    Craft.postActionRequest('translations/order/create-order-tag', $data, function(response, textStatus) {
                        if (textStatus === 'success' && response.success) {
                            Craft.Translations.OrderDetails._addOrderTag(response.title);
                            $('#fields-tags').find('input[type=text]').val('');
                            if (validateForm() && (isNew || isOrderChanged({all: "all"}))) {
                                setSubmitButtonStatus(true);
                            } else {
                                setSubmitButtonStatus(false);
                            }
                        } else {
                            Craft.cp.displayError(Craft.t('app', "Error adding new tag"));
                        }
                    });
                }
            });

            $("#elementTags").on('click', 'a.custom-tag', function(e) {
                $(this).closest('.removable').remove();
                if (validateForm() && (isNew || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });
        },
        _addOrderTag: function($newTag) {
            $mainDiv = $('<div>', {
                class: "element small removable",
                "data-label": $newTag
            });

            $mainDiv.appendTo($("#fields-tags .elements"));
            $hiddenInput = $("<input>", {
                type: "hidden",
                class: "remove-tag",
                name: "tags[]",
                value: $newTag
            });
            $hiddenInput.appendTo($mainDiv);

            $mainContent = $("<div class=label><span class=title>"+$newTag+"</span></div>");
            $deleteTag = $("<a class='delete icon' data-label="+$newTag+" title=Remove></a>");
            // Remove tag from order tag field
            $deleteTag.on('click', function() {
                Craft.Translations.OrderDetails._removeOrderTag(this);
                if (validateForm() && (isNew || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });
            $deleteTag.appendTo($mainContent);
            $mainContent.appendTo($mainDiv);
        },
        _removeOrderTag: function(that) {
            $label = $(that).data("label");
            $mainDiv = $(that).parents(".removable");
            $mainDiv.remove();
        },
        _createNewOrderButtonGroup: function() {
            var $btngroup = $('<div>', {'class': 'btngroup translations-dropdown'});
            $btngroup.insertAfter('#header #new-order-button-group');

            this.$btn = $('<a>', {
                'class': 'btn submit icon translations-submit-order disabled',
                'href': '#',
                'data-icon': "language",
                disabled: "disabled"
            });

            this.$btn.html("<span class='spinner translations-loader hidden'></span>Create Order");

            this.$menubtn = $('<div>', {
                'class': 'btn submit menubtn disabled',
                disabled: "disabled"
            });

            this.$btn.appendTo($btngroup);
            this._addSaveOrderAction(this.$btn, "save");

            this.$menubtn.appendTo($btngroup);

            this.$menubtn.on('click', function(e) {
                e.preventDefault();
            });

            var $menu = $('<div>', {'class': 'menu'});
            $menu.appendTo($btngroup);

            var $dropdown = $('<ul>', {'class': ''});

            $dropdown.appendTo($menu);

            var $item = $('<li>');
            $item.appendTo($dropdown);

            var $orderLink = $('<a>', {
                'class': 'translations-submit-order',
                'href': '#',
                'text': 'Create and add another'
            });

            $orderLink.appendTo($item);
            this._addSaveOrderAction($orderLink, "save_new");

            var $item1 = $('<li><hr>');
            $item1.appendTo($dropdown);
            var $saveDraftLink = $('<a>', {
                'class': 'translations-submit-order',
                'href': '#',
                'text': 'Save draft',
            });

            $saveDraftLink.appendTo($item1);
            this._addSaveDraftAction($saveDraftLink);
        },
        _createUpdateOrderButtonGroup: function() {
            var $btngroup = $('<div>', {'class': 'btngroup translations-dropdown'});
            $btngroup.insertAfter('#header #new-order-button-group');

            this.$btn = $('<a>', {
                'class': 'btn submit icon translations-submit-order disabled',
                'href': '#',
                'data-icon': "language",
                disabled: "disabled"
            });

            this.$btn.html("<span class='spinner translations-loader hidden'></span>Update order");

            this.$menubtn = $('<div>', {
                'class': 'btn submit menubtn disabled',
                disabled: "disabled"
            });

            this.$btn.appendTo($btngroup);
            this._addSaveOrderAction(this.$btn, "update");

            this.$menubtn.appendTo($btngroup);

            this.$menubtn.on('click', function(e) {
                e.preventDefault();
                $buttonText = $.trim($(".translations-submit-order.submit").text());
                if ($buttonText === "Update order") {
                    $(".update-and-new").text("Update and add another");
                } else {
                    $(".update-and-new").text("Create new and add another");
                }
            });

            var $menu = $('<div>', {'class': 'menu'});
            $menu.appendTo($btngroup);

            var $dropdown = $('<ul>', {'class': ''});

            $dropdown.appendTo($menu);

            var $item = $('<li>');
            $item.appendTo($dropdown);

            var $orderLink = $('<a>', {
                'class': 'translations-submit-order update-and-new',
                'href': '#',
                'text': 'Update and add another'
            });

            $orderLink.appendTo($item);
            this._addSaveOrderAction($orderLink, "update_new");

            var $item1 = $('<li><hr>');
            $item1.appendTo($dropdown);
            var $saveDraftLink = $('<a>', {
                'class': 'translations-submit-order',
                'href': '#',
                'text': 'Save draft',
            });

            $saveDraftLink.appendTo($item1);
            this._addSaveDraftAction($saveDraftLink);
        },
        _addSaveOrderAction: function(that, action) {
            var $form = $('#order-form');
            $(that).on('click', function(e) {
                e.preventDefault();
                sendingOrderStatus(true);
                // window.history.replaceState(null, null, removeParams(window.location.href));
                var $hiddenAction = $('<input>', {
                    'type': 'hidden',
                    'name': 'flow',
                    'value': action
                });
                $hiddenAction.appendTo($form);

                Craft.postActionRequest($form.find('input[name=action]').val(), $form.serialize(), function(response, textStatus) {
                    if (response == null) {
                        Craft.cp.displayError(Craft.t('app', "Unable to create order."));
                        sendingOrderStatus(false);
                    } else if (textStatus === 'success' && response.success) {
                        if (response.message) {
                            Craft.cp.displayNotice(Craft.t('app', response.message));
                            sendingOrderStatus(false);
                        } else if (response.url) {
                            window.location.href = response.url;
                        } else if (response.job) {
                            Craft.Translations.trackJobProgressById(true, false, response.job);
                        } else {
                            Craft.cp.displayError(Craft.t('app', "No data in response"));
                            sendingOrderStatus(false);
                        }
                    } else {
                        Craft.cp.displayError(Craft.t('app', response.message));
                        sendingOrderStatus(false);
                    }
                });
            });
        },
        _addSaveDraftAction: function(that) {
            var $form = $('#order-form');
            $(that).on('click', function(e) {
                e.preventDefault();
                window.history.replaceState(null, null, removeParams(window.location.href));

                var $hiddenAction = $('<input>', {
                    'type': 'hidden',
                    'name': 'action',
                    'value': 'translations/order/save-order-draft'
                });

                $hiddenAction.appendTo($form);

                $form.submit();
            });
        },
        _addDeleteDraftAction: function(that) {
            var $form = $('#order-form');
            $(that).on('click', function(e) {
                e.preventDefault();
                window.history.replaceState(null, null, removeParams(window.location.href));

                var $hiddenAction = $('<input>', {
                    'type': 'hidden',
                    'name': 'action',
                    'value': 'translations/order/delete-order-draft'
                });

                $hiddenAction.appendTo($form);

                $form.submit();
            });
        },
    }

    $(function() {
        Craft.Translations.OrderDetails.init();
    });
})(jQuery);