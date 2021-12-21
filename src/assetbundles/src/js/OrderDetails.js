(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }
    // Defaults to open file tab on detail page
    var isSubmitted = $("#order-attr").data("submitted");
    var hasOrderId = $("input[type=hidden][name=id]").val() != '';
    var isPending = $("#order-attr").data("status") === "pending" || $("#order-attr").data("status") === "failed";
    var isUpdateable = $("#order-attr").data("isupdateable") !== "";
    var isCompleted = $("#order-attr").data("status") === "complete";
    var isCanceled = $("#order-attr").data("status") === "canceled";
    var isPublished = $("#order-attr").data("status") === "published";
    var sourceChanged = String($("#order-attr").data("hassourcechanges"));
    var isDefaultTranslator = $("#order-attr").data("translator") === "export_import";
    var defaultTranslatorId = $("#originalTranslatorId").data("id");

    function validateForm() {
        var buttonStatus = true;
        setUnloadEvent();
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

    function isOrderChanged($data, $needData = false) {
        $responseData = [];
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
                    if (!$el.parent('div').hasClass("hidden")) {
                        targetSites.push(val);
                    }
                });
                if (haveDifferences($originalTargetSiteIds.split(","), targetSites)) {
                    if (!$needData) return true;
                    $responseData.push("targetSites");
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
                    if (!$needData) return true;
                    $responseData.push("sourceSiteSelect");
                }
            }
            // Validate Entries
            if (key == "entry" || key == "all") {
                $originalElementIds = $('#originalElementIds').val().split(',');
                var currentElementIds = $('#currentElementIds').val().split(",");

                if (haveDifferences($originalElementIds, currentElementIds)) {
                    if (!$needData) return true;
                    $responseData.push("elements");
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
                    if (!$needData) return true;
                    $responseData.push("title");
                }
            }
            // Validate Track Changes
            if (key == "trackChanges" || key == "all") {
                $originalTrackChanges = $('#originalTrackChanges').val();
                var currentTrackChanges = $('input[type=hidden][name=trackChanges]').val();

                if (currentTrackChanges == undefined || currentTrackChanges == '') {
                    currentTrackChanges = 0;
                }

                if ($originalTrackChanges != currentTrackChanges) {
                    if (!$needData) return true;
                    $responseData.push("trackChanges");
                }
            }
            // Validate Translator
            if (key == "translator" || key == "all") {
                $originalTranslatorId = $('#originalTranslatorId').val().split(',');
                var currentTranslatorId = $('#translatorId').val().split(",");

                if (haveDifferences($originalTranslatorId, currentTranslatorId)) {
                    if (!$needData) return true;
                    $responseData.push("translatorId");
                }
            }
            // Validate Due Date
            if (! isDefaultTranslator && (key == "dueDate" || key == "all")) {
                $originalDueDate = $('#originalRequestedDueDate').val();
                var currentDueDate = $('#requestedDueDate-date').val();
                if (currentDueDate == undefined || currentDueDate == '') {
                    dueDate = new Date();
                    currentDueDate = (dueDate.getMonth('mm')+1)+"/"+dueDate.getDate()+"/"+dueDate.getFullYear();
                }

                if (Date.parse($originalDueDate) != Date.parse(currentDueDate)) {
                    if (!$needData) return true;
                    $responseData.push("requestedDueDate");
                }
            }
            // Validate Comments
            if (! isDefaultTranslator && (key == "comments" || key == "all")) {
                $originalComments = $('#originalComments').val();
                var currentComments = $('#comments').val();

                if ($originalComments != currentComments) {
                    if (!$needData) return true;
                    $responseData.push("comments");
                }
            }
            // Validate order tags
            if (! isDefaultTranslator && (key== "tags" || key == "all")) {
                $originalTags = [];
                if ($('#originalTags').val() != "") {
                    $originalTags = $('#originalTags').val().split(',');
                }
                $currentTags = [];
                $("#elementTags .removable").each(function() {
                    $currentTags.push($(this).data("label"));
                });
                if (haveDifferences($originalTags, $currentTags)) {
                    if (!$needData) return true;
                    $responseData.push("tags");
                }
            }
            // Validate entry version
            if (key== "version" || key == "all") {
                var currentElementVersions = $('#elementVersions').val().split(",");
                var originalElementVersions = $('#originalElementVersions').val().split(",");
                if (haveDifferences(originalElementVersions, currentElementVersions)) {
                    if (!$needData) return true;
                    $responseData.push("version");
                }
            }
        };

        return $needData ? $responseData : false;
    }

    function setButtonText($button, $value) {
        if (! isSubmitted) {
            return;
        }
        $($button).text($value);
    }

    function getFieldValuesAsUrlParams() {
        title = $('#title').val();
        trackChanges = $('input[type=hidden][name=trackChanges]').val();;
        tags = $('input[name="tags[]"]');
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
        if (tags.length > 0) {
            tags.each(function() {
                url += "&tags[]="+$(this).val()
            });
        }

        url += "&trackChanges="+trackChanges
        return url
    }

    function sendingOrderStatus($status, $btnStatus = false) {
        if ($status) {
            $('.translations-loader').removeClass('hidden');
            $('.translations-dropdown .btn').addClass('disabled').css('pointer-events', 'none');
            $('.translations-dropdown .btn').prop('disabled', true);
        } else {
            $('.translations-loader').addClass('hidden');
            if (! $btnStatus) {
                $('.translations-dropdown .btn').removeClass('disabled').css('pointer-events', '');
                $('.translations-dropdown .btn').prop('disabled', false);
            }
        }
    }

    function toggleSelections($toggle) {
        $(':checkbox[name="targetSites[]"]').prop('checked', $toggle);
    }

    function shouldCreateNewOrder() {
        // Source Site Check
        var originalSourceSite = $('#originalSourceSiteId').val();
        var site = $("#sourceSiteSelect").val();

        if (typeof originalSourceSite !== 'undefined') originalSourceSite = originalSourceSite.split(",");

        if (haveDifferences(originalSourceSite, site.split(","))) return true;

        // Target Sites Check
        var $originalTargetSiteIds = $('#originalTargetSiteIds').val().replace(/[\[\]\"]/g, '').split(",");
        var $all = $(':checkbox[name="targetSites"]');
        var $checkboxes = $all.is(':checked') ? $(':checkbox[name="targetSites[]"]') : $(':checkbox[name="targetSites[]"]:checked');
        var targetSites = [];

        $checkboxes.each(function() {
            var $el = $(this);
            var val = $el.attr('value');
            targetSites.push(val);
        });

        if ($($originalTargetSiteIds).not(targetSites).get().length > 0) return true;

        // Translator check
        $originalTranslatorId = $('#originalTranslatorId').val().split(',');
        var currentTranslatorId = $('#translatorId').val().split(",");

        if (haveDifferences($originalTranslatorId, currentTranslatorId)) return true;

        // Order Modification on completed order
        if (isDefaultTranslator && isPublished && isOrderChanged({all: "all"})) return true;
        if (!isDefaultTranslator && (isCompleted || isPublished) && isOrderChanged({all: "all"})) return true;

        return false;
    }

    function setUpdatedFields() {
        $changedFields = isOrderChanged({all: 'all'}, true);
        $changedFields = $changedFields.length > 0 ? JSON.stringify($changedFields) : "";
        $('input[name=updatedFields]').val($changedFields);
    }

    function toggleTranslatorBasedFields(status = false) {
        if (status) {
            $('#extra-fields').removeClass('hidden non-editable disabled');

            if (!isPending) {
                // required these class else the input fields will not be disabled
                $('#comments').addClass('non-editable noClick');
                $('#requestedDueDate-date').addClass('non-editable noClick');

                $('#comments-field').addClass('disabled non-editable');
                $('#comments-field').prop('title', 'This field cannot be edited.');
                $('#requestedDueDate-field').addClass('disabled non-editable');
                $('#requestedDueDate-field').prop('title', 'This field cannot be edited.');
            }
        } else {
            $('#extra-fields').addClass('hidden non-editable disabled');
        }
    }

    function syncElementVersions() {
        var currentElementIds = $('#currentElementIds').val().split(",");
        var elementVersions = $('#elementVersions').val().split(",");
        var updatedVersions = [];
        $.each(elementVersions, function(key, value) {
            elementId = value.split('_')[0];
            if ($.inArray(elementId, currentElementIds) > -1) {
                updatedVersions.push(value);
            }
        });
        $('#elementVersions').val(updatedVersions.join(','));
    }

    function syncSites($all = false) {
        var source = $("#sourceSiteSelect").val();
        var targetCheckboxes = $(':checkbox[name="targetSites[]"]');
        targetCheckboxes.each(function() {
            siteId = $(this).val();
            if ($(this).closest('div').hasClass('hidden')) {
                if (! $(':checkbox[name=targetSites]').is(':checked')) {
                    $(this).prop('disabled', false);
                }
                $(this).closest('div').removeClass('hidden');
            }
            if (siteId != '' && source != '' && siteId == source) {
                $(this).prop('disabled', true);
                $(this).closest('div').addClass('hidden');
            }
        });

        if ($all) {
            let $all = $(':checkbox[name="targetSites[]"]').length;
            let $checked = $(':checkbox[name="targetSites[]"]:checked').not(':disabled').length;
            let $source = $("#sourceSiteSelect").val() == '' ? 0 : 1;
            if (($all-$source) == $checked) {
                $(':checkbox[name=targetSites]').prop('checked', true);
                $(':checkbox[name="targetSites[]"]').prop('disabled', true);
            }
        }
    }

    function setUnloadEvent(status = true) {
        if (status) {
            $orderHasInputs = false;

            title = $('#title').val();
            tags = $('input[name="tags[]"]');
            elementIds = $('#currentElementIds').val();
            elementIds = elementIds != '' ? elementIds.split(',') : [];

            targetSites = [];
            var $all = $(':checkbox[name="targetSites"]');
            var $checkboxes = $all.is(':checked') ? $(':checkbox[name="targetSites[]"]') : $(':checkbox[name="targetSites[]"]:checked');
            $checkboxes.each(function() {
                var $el = $(this);
                var val = $el.attr('value');
                targetSites.push(val);
            });

            dueDate = $('#requestedDueDate-date').val();
            comments = $('#comments').val();

            if (typeof title !== undefined && title !== '') {
                $orderHasInputs = true;
            }
            if (typeof dueDate !== undefined && dueDate != undefined && dueDate != '') {
                $orderHasInputs = true;
            }
            if (typeof comments !== undefined && comments!== '') {
                $orderHasInputs = true;
            }

            if (targetSites.length > 0) {
                $orderHasInputs = true;
            }
            if (elementIds.length > 0) {
                $orderHasInputs = true;
            }

            if (tags.length > 0) {
                $orderHasInputs = true;
            }

            if ($orderHasInputs && ((isPending && ! hasOrderId) || isOrderChanged({all: 'all'}))) {
                window.onbeforeunload = function(e) {
                    return "All changes will be lost!";
                };
            } else {
                window.onbeforeunload = null;
            }
        } else {
            window.onbeforeunload = null;
        }
    }

    function getSelectedElements()
    {
        return Craft.Translations.OrderDetails.$elementCheckboxes.filter(':checked');
    }

    Craft.Translations.OrderDetails = {
        $elementCheckboxes: null,
        $allElementCheckbox: null,
        $hasActions: null,
        $headerContainer: null,
        $files: null,
        $isMobile: null,

        updateFixedHeader: function($top) {
            if (this.$headerContainer.height() > this.$files[0].getBoundingClientRect().top - 10) {
                if (this.$isMobile) {
                    $top = 0;
                }
                this.$files.find('#text-field').css({
                    "position": "sticky",
                    "top": $top+"px",
                    "z-index": "100",
                    "background-color": "white",
                    "max-height": "70px",
                    "box-shadow": "inset 0 -1px 0 rgba(63, 77, 90, 0.1);",
                });
                this.$files.find('#text-field div.heading').css({
                    "padding-top": "20px",
                    "padding-bottom": "20px"
                });
            } else {
                this.$files.find('#text-field').css({
                    "position": "",
                    "top": "",
                    "z-index": "",
                    "background-color": "",
                    "max-height": "",
                    "box-shadow": "",
                });
                this.$files.find('#text-field div.heading').css({
                    "padding-top": "",
                    "padding-bottom": "",
                });
            }
        },
        toggleUpdateActionButton: function() {
            $updateButton = $('#order-element-action-menu').find('.update-element');
            // enable only if checked checkbox entry has the updated source
            selectedHasChanges = false;
            $.each(this.$elementCheckboxes.filter(':checked'), function() {
                if (! selectedHasChanges) {
                    totalElementIds = $.map($('#order-attr').data('canonicaloriginalmap'), function(val, key) {
                        key = parseInt(key);
                        val = parseInt(val);
                        return key == val ? key : [key, val];
                    });

                    selectedHasChanges = $.inArray($(this).data('element'), totalElementIds) >= 0;
                }
            });
            if (sourceChanged && selectedHasChanges && !isPublished && !isCanceled) {
                $updateButton.removeClass('disabled noClick');
            } else {
                $updateButton.addClass('disabled noClick');
            }
        },
        toggleElementAction: function($show = true) {
            if ($show && this.$elementCheckboxes.filter(':checked').length > 0) {
                if (! this.$hasActions) {
                    this._buildElementActions();
                    this.$hasActions = true;
                } else {
                    $('#toolbar').show();
                }
                this.toggleUpdateActionButton();
            } else {
                $('#toolbar').hide();
            }
        },
        init: function() {
            var self = this;
            this.$headerContainer = $('#header-container');
            this.$files = $('#files');
            this.$isMobile = window.matchMedia("only screen and (max-width: 760px)").matches;

            // For elements checkboxes
            this.$allElementCheckbox = $('.all-element-checkbox :checkbox');
            this.$elementCheckboxes = $('tbody .element :checkbox');

            this.$allElementCheckbox.on('change', function() {
                self.$elementCheckboxes.prop('checked', $(this).is(':checked'));
                self.toggleElementAction();
            });

            this.$elementCheckboxes.on('change', function() {
                self.$allElementCheckbox.prop(
                    'checked',
                    self.$elementCheckboxes.filter(':checked').length === self.$elementCheckboxes.length
                );
                self.toggleElementAction();
            });

            if (isDefaultTranslator) {
                toggleTranslatorBasedFields();
            } else {
                toggleTranslatorBasedFields(true);
            }

            syncSites(true);

            if (isSubmitted) {
                this._createUpdateOrderButtonGroup();
                this._disableOrderSettingsTab();
            } else {
                this._createNewOrderButtonGroup();
            }

            if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
                setSubmitButtonStatus(true);
            }

            setUnloadEvent();

            // Target lang Ajax
            $(':checkbox[name="targetSites[]"], :checkbox[name="targetSites"]').on('change', function() {
                if ($(this).attr('name') == "targetSites") {
                    toggleSelections($(this).is(':checked'));
                } else {
                    var $all = $(':checkbox[name="targetSites[]"]');
                    var $checkboxes = $(':checkbox[name="targetSites[]"]:checked:not(:disabled)');
                    var $sourceSite = $("#sourceSiteSelect").val();
                    $sourceSite = $sourceSite == '' ? 0 : 1;

                    if (($all.length - $sourceSite) == $checkboxes.length) {
                        $(':checkbox[name=targetSites]').prop('checked', true);
                        $(':checkbox[name="targetSites[]"]').prop('disabled', true);
                    }
                    var targetSitesLabels = [];

                    $checkboxes.each(function() {
                        var $el = $(this);
                        var label = $.trim($el.next('label').text());
                        targetSitesLabels.push(label);
                    });

                    $('[data-order-attribute=targetSites]').html(targetSitesLabels.join(', '));
                }

                if (isSubmitted) {
                    if (shouldCreateNewOrder()) {
                        if (! isDefaultTranslator) {
                            setButtonText('.translations-submit-order.submit', 'Create new order');
                        }
                    } else {
                        setButtonText('.translations-submit-order.submit', 'Update order');
                    }
                }

                if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            // Order Elements Version script
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

                if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('li[data-id]').on('click', function() {
                if ($(this).data('id') == "files") {
                    if (isPending) {
                        return false;
                    }
                    setSubmitButtonStatus(false);
                } else {
                    if (validateForm() && isOrderChanged({all: "all"})) {
                        setSubmitButtonStatus(true);
                    }
                }
            });

            $('#trackChanges').on('change', function() {
                if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('#title').on('change, keyup', function() {
                if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('#translatorId').on('change', function() {
                if ($(this).find(':selected').val() == defaultTranslatorId) {
                    toggleTranslatorBasedFields();
                } else {
                    toggleTranslatorBasedFields(true);
                }
                if (shouldCreateNewOrder()) {
                    setButtonText('.translations-submit-order.submit', 'Create new order');
                } else {
                    setButtonText('.translations-submit-order.submit', 'Update order');
                }

                if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('#createNewOrder').on('click', function () {
                window.location.href = "/admin/translations/orders/create";
            });

            $('.order-warning', '#global-container').infoicon();

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

                syncSites();

                if (currentElementIds.length > 1) {
                    currentElementIds.forEach(function (element) {
                        url += '&elements[]='+element;
                    })
                }

                if (shouldCreateNewOrder()) {
                    setButtonText('.translations-submit-order.submit', 'Create new order');
                } else {
                    setButtonText('.translations-submit-order.submit', 'Update order');
                }

                if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }

                window.history.pushState("", "", url);
            });

            $('.translations-order-form').on('submit', function(e) {
                if (! validateForm()) {
                    return false;
                }

                setUnloadEvent(false);
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
                setUnloadEvent(false);

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

            $('#elementTags').on('DOMNodeInserted', 'input[type=hidden]', function() {
                if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('#elementTags').on('DOMNodeRemoved', function() {
                if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('#cancel-order-link').on('click', function() {
                var $cancelTab = $('#cancel-order-tab');
                var $cancelIcon = $('#cancel-order-link');
                if ($cancelTab.hasClass('hidden')) {
                    $cancelTab.removeClass('hidden');
                    $cancelIcon.removeClass('desc');
                    $cancelIcon.addClass('asc');
                } else {
                    $cancelTab.addClass('hidden');
                    $cancelIcon.removeClass('asc');
                    $cancelIcon.addClass('desc');
                }
            });

            $(window).on('scroll resize', function(e) {
                $width = $(window).width();
                if ($width < 973) {
                    height = 104;
                } else {
                    height = 50;
                }
                self.updateFixedHeader(height);
            });
        },
        _addOrderTag: function($newTag, $tagId) {
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
            $deleteTag = $("<a class='delete icon' data-label="+$newTag+" data-id="+$tagId+" title=Remove></a>");
            // Remove tag from order tag field
            $deleteTag.on('click', function() {
                Craft.Translations.OrderDetails._removeOrderTag(this);
                if (validateForm() && (isPending || isOrderChanged({all: "all"}))) {
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

            if (! isDefaultTranslator && isSubmitted && !(isCompleted || isPublished || isCanceled)) {
                var $cancelOrderDiv = $('<div>', {
                    class: "field hidden bg-white",
                    id: "cancel-order-tab"
                });
                var settingsDiv = $('#settings div:eq(0)');
                $cancelOrderDiv.insertAfter(settingsDiv);
                var $cancelOrderHead = $('<div class=heading><label>Order Actions</label></div>');
                $cancelOrderHead.appendTo($cancelOrderDiv);
                var $cancelOrderBody = $('<div>', {
                    class: "input ltr"
                });
                $cancelOrderBody.appendTo($cancelOrderDiv);
                var $cancelOrderLink = $('<a>', {
                    class: 'translations-submit-order right color-red',
                    href: '#',
                    text: 'Cancel order',
                });
                $cancelOrderLink.appendTo($cancelOrderBody);
                this._addCancelOrderAction($cancelOrderLink);
            }
        },
        _addSaveOrderAction: function(that, action) {
            var $form = $('#order-form');
            $(that).on('click', function(e) {
                e.preventDefault();
                sendingOrderStatus(true);
                setUnloadEvent(false);
                if ($(that).text() == "Create new order") {
                    var url = window.location.origin+"/admin/translations/orders/create";
                    $form.find("input[type=hidden][name=action]").val('translations/order/clone-order');
                    window.history.pushState("", "", url);
                    $('#text-field input:checkbox').prop('checked', true);
                    $form.submit();
                }else if ($(that).text() == "Update order") {
                    // Set updated fields before proceeding
                    setUpdatedFields();
                    $('#text-field input:checkbox').prop('checked', true);
                    Craft.postActionRequest('translations/order/update-order', $form.serialize(), function(response, textStatus) {
                        if (response == null) {
                            $('#text-field input:checkbox').prop('checked', false);
                            Craft.cp.displayError(Craft.t('app', "Unable to update order."));
                            sendingOrderStatus(false);
                        } else if (textStatus === 'success' && response.success) {
                            if (response.message) {
                                Craft.cp.displayNotice(Craft.t('app', response.message));
                                setTimeout(function() {
                                    window.location.href = removeParams(location.href);
                                }, 200);
                            } else {
                                $('#text-field input:checkbox').prop('checked', false);
                                Craft.cp.displayError(Craft.t('app', "Something went wrong"));
                                sendingOrderStatus(false);
                            }
                        } else {
                            $('#text-field input:checkbox').prop('checked', false);
                            Craft.cp.displayError(Craft.t('app', response.message));
                            sendingOrderStatus(false);
                        }
                    });
                } else {
                    var $hiddenFlow = $('<input>', {
                        'type': 'hidden',
                        'name': 'flow',
                        'value': action
                    });
                    $hiddenFlow.appendTo($form);

                    Craft.postActionRequest($form.find('input[name=action]').val(), $form.serialize(), function(response, textStatus) {
                        if (response == null) {
                            Craft.cp.displayError(Craft.t('app', "Unable to create order."));
                            sendingOrderStatus(false);
                        } else if (textStatus === 'success' && response.success) {
                            if (response.message) {
                                Craft.cp.displayNotice(Craft.t('app', response.message));
                                setTimeout(function() {
                                    location.reload();
                                }, 200);
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
                }

            });
        },
        _addSaveDraftAction: function(that) {
            var $form = $('#order-form');
            $(that).on('click', function(e) {
                e.preventDefault();
                setUnloadEvent(false);
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
        _addCancelOrderAction: function(that) {
            var $form = $('#order-form');
            $(that).on('click', function(e) {
                e.preventDefault();
                setUnloadEvent(false);
                if (confirm(Craft.t('app', 'Are you sure you want to cancel this order?'))) {
                    $form.find("input[type=hidden][name=action]").val('translations/order/cancel-order');

                    $form.submit();
                }
            });
        },
        _disableOrderSettingsTab: function() {
            var $proceed = false;
            if (!isDefaultTranslator && (isCompleted || isPublished || isCanceled)) {
                $proceed = true;
            }
            if ($proceed) {
                setUnloadEvent(false);
                $('li[data-id=order]').attr('title', 'This order is no longer editable. The corresponding My Acclaro order is complete.');
                $('#tab-order').addClass('noClick');
                $url = location.href;
                if ($url.includes('#order')) {
                    history.pushState('','',$url.replace('#order', ''));
                    $('#order').addClass('hidden');
                    $('#files').removeClass('hidden');
                    $('#tab-order').removeClass('sel');
                    $('#tab-files').addClass('sel');
                }
            }
        },
        _buildElementActions: function() {
            var $toolbar = $('<div>', {'id': 'toolbar', 'class': 'btngroup flex flex-nowrap margin-left', 'style': 'max-width: 10px'});
            $toolbar.insertAfter('#text-field #entries-label');

            $menubtn = $('<div class="btn menubtn" data-icon=settings title=Actions></div>');
            $toolbar.append($menubtn);

            $('<div class="translations-loader hidden"></div>').insertAfter($toolbar);

            $menubtn.on('click', function(e) {
                e.preventDefault();
            });

            $menu = $('<div>', {'class': 'menu', 'id': 'order-element-action-menu'});
            $menu.appendTo($toolbar);

            $dropdown = $('<ul>', {'class': ''});
            $menu.append($dropdown);

            $updateLi = $('<li>');
            $dropdown.append($updateLi);

            $updateAction = $('<a>', {
                'class': 'update-element disabled noClick',
                'href': '#',
                'text': 'Update entries',
            });
            $updateLi.append($updateAction);
            this._addUpdateElementAction($updateAction);

            $updateAndDownloadAction = $('<a>', {
                'class': 'update-element disabled noClick',
                'href': '#',
                'text': 'Update entries and download files',
            });
            $updateLi.append($updateAndDownloadAction);
            this._addUpdateElementAction($updateAndDownloadAction, true);

            $dropdown.append($('<hr>'));
            $deleteLi = $('<li>');
            $dropdown.append($deleteLi);

            $deleteAction = $('<a>', {
                'class': 'remove-element error',
                'href': '#',
                'text': 'Delete entries',
            });
            $deleteLi.append($deleteAction);
            this._addDeleteElementAction($deleteAction);
        },
        _addUpdateElementAction: function(that, $download = false) {
            var self = this;
            $(that).on('click', function(e) {
                e.preventDefault();
                $form = $('#order-form');

                if (! isDefaultTranslator && ! isUpdateable) {
                    Craft.cp.displayNotice(Craft.t('app', 'Please place a new order for updated source.'));
                    return;
                }

                self.onStart();
                elements = [];
                $rows = getSelectedElements();
                $rows.each(function() {
                    $elementId = $(this).data('element');

                    $canonicalOriginalMap = $('#order-attr').data('canonicaloriginalmap');
                    totalElementIds = $.map($canonicalOriginalMap, function(val, key) {
                        key = parseInt(key);
                        val = parseInt(val);
                        return key == val ? key : [key, val];
                    } );

                    if ($.inArray($elementId, totalElementIds) >= 0) {
                        if ($elementId.toString() in $canonicalOriginalMap) {
                            $elementId = $canonicalOriginalMap[$elementId];
                        }
                        elements.push($elementId);
                    }
                });

                $hiddenFlow = $('<input>', {
                    'type': 'hidden',
                    'name': 'update-elements',
                    'value': JSON.stringify(elements)
                });
                $hiddenFlow.appendTo($form);
                Craft.postActionRequest('translations/order/update-order-files-source', $form.serialize(), function(response, textStatus) {
                    if (textStatus === 'success') {
                        if (response.success) {
                            $download ? self._downloadFiles() : location.reload();
                        } else {
                            Craft.cp.displayError(Craft.t('app', response.message));
                            self.onComplete();
                        }
                    } else {
                        Craft.cp.displayError(Craft.t('app', 'Unable to update entries.'));
                        self.onComplete();
                    }
                });
            });
        },
        _addDeleteElementAction: function(that) {
            var self = this;
            $(that).on('click', function(e) {
                var $table = $('#elements-table');
                var $rows = getSelectedElements();

                e.preventDefault();

                if (confirm(Craft.t('app', 'Are you sure you want to remove this entry from the order?'))) {
                    $removedElements = {};

                    $rows.each(function() {
                        $removedElements[$(this).data('element')] = $(this).data('element');
                        $(this).closest('tr').remove();
                    });

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
                        if (itm != "" && !(itm in $removedElements)) {
                            return i == currentElementIds.indexOf(itm);
                        }
                    }).join(",");

                    if (shouldCreateNewOrder()) {
                        setButtonText('.translations-submit-order.submit', 'Create new order');
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
                    syncElementVersions();
                    self.toggleElementAction(false);
                }
            });
        },
        _downloadFiles: function() {
            var self = this;
            var $downloadForm = $('#export-zip');

            $('<input/>', {
                'class': 'hidden',
                'name': 'format',
                'value': 'xml'
            }).appendTo($downloadForm);

            postData = Garnish.getPostData($downloadForm);
            params = Craft.expandPostArray(postData);

            var $data = {
                params: params
            };

            Craft.postActionRequest(params.action, $data, function(response, textStatus) {
                if(textStatus === 'success') {
                    if (response && response.error) {
                        Craft.cp.displayError(response.error);
                        self.onComplete();
                    }

                    if (response && response.translatedFiles) {
                        var $iframe = $('<iframe/>', {'src': Craft.getActionUrl('translations/files/export-file', {'filename': response.translatedFiles})}).hide();
                        $downloadForm.append($iframe);
                        self.onComplete(true);
                    }
                } else {
                    Craft.cp.displayError('There was a problem exporting your file.');
                    self.onComplete();
                }
            });
        },
        onComplete: function($reload = false) {
            $('#toolbar').removeClass('disabled noClick');
            $('#text-field').find('.translations-loader').toggleClass('spinner hidden');
            if ($reload) location.reload();
        },
        onStart: function() {
            $('#toolbar').addClass('disabled noClick');
            $('#text-field').find('.translations-loader').toggleClass('spinner hidden');
        }
    }

    $(function() {
        Craft.Translations.OrderDetails.init();

        Garnish.$doc.mouseover($.proxy(function() {
            Craft.initUiElements('#main');
        }, this));
    });
})(jQuery);
