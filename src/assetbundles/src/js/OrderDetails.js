(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }
    // Defaults to open file tab on detail page
    var isSubmitted = $("#order-attr").data("submitted");
    var hasOrderId = $("input[type=hidden][name=id]").val() != '';
    var isPending = $("#order-attr").data("status") === "pending" || $("#order-attr").data("status") === "failed";
    var isUpdateable = $("#order-attr").data("isupdateable");
    var isCompleted = $("#order-attr").data("status") === "complete";
    var isCanceled = $("#order-attr").data("status") === "canceled";
    var isPublished = $("#order-attr").data("status") === "published";
    var isDefaultTranslator = $("#order-attr").data("translator") === "export_import";
    var defaultTranslatorId = $("#originalTranslatorId").data("id");

    function validateForm() {
        var buttonStatus = true;
        setUnloadEvent();
        $entries = getEntryIds();
        var $targetLang = $(':checkbox[name="targetSites[]"]:checked');

        if ($('#title').val() == "") {
            buttonStatus = false;
        }

        if (!$entries.length > 0) {
            buttonStatus = false;
        }

        if ($("#sourceSite").val() == "") {
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

    function isOrderChanged() {
		// Validate Target Sites
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
		if (haveDifferences($originalTargetSiteIds.split(","), targetSites)) return true;

		// Validate Source Site
		var originalSourceSite = $('#originalSourceSiteId').val();
		var site = $("#sourceSite").val();
		if (typeof originalSourceSite !== 'undefined') {
			originalSourceSite = originalSourceSite.split(",");
		}

		if (haveDifferences(originalSourceSite, site.split(","))) return true;

		// Validate Entries
		$originalElementIds = $('#originalElementIds').val().split(',');
		var currentElementIds = getEntryIds(false, true);

		if (haveDifferences($originalElementIds, currentElementIds)) return true;

		// Validate Title
		$originalTitle = $('#originalTitle').val();
		var currentTitle = $('#title').val();

		if (typeof $originalTitle !== 'undefined') {
			$originalTitle = $originalTitle.split(",");
		}

		if (haveDifferences($originalTitle, currentTitle.split(","))) return true;

		// Validate Track Changes
		$originalTrackChanges = $('#originalTrackChanges').val();
		var currentTrackChanges = $('input[type=hidden][name=trackChanges]').val();

		if (currentTrackChanges == undefined || currentTrackChanges == '') {
			currentTrackChanges = 0;
		}

		if ($originalTrackChanges != currentTrackChanges) return true;

		// Validate Track Target Changes
		$originalTrackTargetChanges = $('#originalTrackTargetChanges').val();
		var currentTrackTargetChanges = $('input[type=hidden][name=trackTargetChanges]').val();

		if (currentTrackTargetChanges == undefined || currentTrackTargetChanges == '') {
			currentTrackTargetChanges = 0;
		}

		if ($originalTrackTargetChanges != currentTrackTargetChanges) return true;

		// Validate Translator
		$originalTranslatorId = $('#originalTranslatorId').val().split(',');
		var currentTranslatorId = $('#translatorId').val().split(",");

		if (haveDifferences($originalTranslatorId, currentTranslatorId)) return true;

		if (! isDefaultTranslator) {
			// Validate Due Date
			$originalDueDate = $('#originalRequestedDueDate').val();
			var currentDueDate = $('#requestedDueDate-date').val();
			if (currentDueDate == undefined || currentDueDate == '') {
				dueDate = new Date();
				currentDueDate = (dueDate.getMonth('mm')+1)+"/"+dueDate.getDate()+"/"+dueDate.getFullYear();
			}

			if (Date.parse($originalDueDate) != Date.parse(currentDueDate)) return true;

			// Validate Comments
			$originalComments = $('#originalComments').val();
			var currentComments = $('#comments').val();

			if ($originalComments != currentComments) return true;

			// Validate order tags
			$originalTags = [];
			if ($('#originalTags').val() != "") {
				$originalTags = $('#originalTags').val().split(',');
			}
			$currentTags = [];
			$("#elementTags .removable").each(function() {
				$currentTags.push($(this).data("label"));
			});

			if (haveDifferences($originalTags, $currentTags)) return true;
		}

        return false;
    }

    function setButtonText($button, $value) {
        if (! isSubmitted) {
            return;
        }
        $($button).text($value);
    }

    function getFieldValuesAsUrlParams() {
        title = $('#title').val();
		trackChanges = $('input[type=hidden][name=trackChanges]').val();
		trackTargetChanges = $('input[type=hidden][name=trackTargetChanges]').val();
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

		url += "&trackChanges=" + trackChanges + "&trackTargetChanges=" + trackTargetChanges
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
        var site = $("#sourceSite").val();

        if (typeof originalSourceSite !== 'undefined') originalSourceSite = originalSourceSite.split(",");

        if (haveDifferences(originalSourceSite, site.split(","))) return true;

        // Translator check
        $originalTranslatorId = $('#originalTranslatorId').val().split(',');
        var currentTranslatorId = $('#translatorId').val().split(",");

        if (haveDifferences($originalTranslatorId, currentTranslatorId)) return true;

        // Order Modification on completed order
        if (isDefaultTranslator && isPublished && isOrderChanged()) return true;
        if (!isDefaultTranslator && (isCompleted || isPublished) && isOrderChanged()) return true;

        return false;
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

    function syncSites($all = false) {
        var source = $("#sourceSite").val();
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
            let $source = $("#sourceSite").val() == '' ? 0 : 1;
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
            elementIds = getEntryIds();

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

            if ($orderHasInputs && ((isPending && ! hasOrderId) || isOrderChanged())) {
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

    function getEntries($selected = false) {
		$elementCheckboxes = $('tbody .element :checkbox');
		if ($selected) {
			return $elementCheckboxes.filter(':checked').closest('tr');
		}
        return $elementCheckboxes.closest('tr');
    }

	function getEntryIds($canonical = false, $current = false) {
		$entries = getEntries();
		$result = [];
		if ($current) {
			$entries.each(function() {
				$entryId = String($(this).find('input[name="elements[]"]').val());
				$result.push($entryId);
			});
		} else {
			$entries.each(function() {
				$entryId = String($(this).data('element-id'));
				if ($canonical) {
					$entryId = String($(this).data('canonical-id'));
				}
				$result.push($entryId);
			});
		}
		return $result;
	}

    Craft.Translations.OrderDetails = {
        $elementCheckboxes: null,
        $allElementCheckbox: null,
        $elementActions: null,
        $headerContainer: null,
        $files: null,
        $isMobile: null,

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

			// this is to make right side settings tab not shrink when scrolling
			$('#details').addClass('unset-width');

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

            if (validateForm() && (isPending || isOrderChanged())) {
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
                    var $sourceSite = $("#sourceSite").val();
                    $sourceSite = $sourceSite == '' ? 0 : 1;

                    if (($all.length - $sourceSite) == $checkboxes.length) {
                        $(':checkbox[name=targetSites]').prop('checked', true);
                        $(':checkbox[name="targetSites[]"]').prop('disabled', true);
                    }
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

                if (validateForm() && (isPending || isOrderChanged())) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            // Order Elements Version script
            $('select[name=version]').on('change', function() {
				$(this).closest('tr').find('input[name="elements[]"]').val($(this).val());

                if (validateForm() && (isPending || isOrderChanged())) {
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
                    if (validateForm() && isOrderChanged()) {
                        setSubmitButtonStatus(true);
                    }
                }
            });

			$('#trackChanges, #trackTargetChanges').on('change', function () {
                if (validateForm() && (isPending || isOrderChanged())) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('#title').on('change, keyup', function() {
                if (validateForm() && (isPending || isOrderChanged())) {
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

                if (validateForm() && (isPending || isOrderChanged())) {
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
            $("#sourceSite").change(function (e) {
                $(window).off('beforeunload.windowReload');

                syncSites();

                if (shouldCreateNewOrder()) {
                    setButtonText('.translations-submit-order.submit', 'Create new order');
                } else {
                    setButtonText('.translations-submit-order.submit', 'Update order');
                }

                if (validateForm() && (isPending || isOrderChanged())) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('.translations-order-form').on('submit', function(e) {
                if (! validateForm()) {
                    return false;
                }

                setUnloadEvent(false);
            });

            $("input[id^=requestedDueDate]").datepicker('option', 'minDate', +1);

            $(".addEntries").on('click', function (e) {

                elementIds = [];

                var site = $("#sourceSite").val();

                var currentElementIds = getEntryIds(false, true);
                var canonicalElementIds = getEntryIds(true);

                var $url = removeParams(window.location.href);
                setUnloadEvent(false);

                this.assetSelectionModal = Craft.createElementSelectorModal('craft\\elements\\Entry', {
                    storageKey: null,
                    sources: null,
                    elementIndex: null,
                    criteria: {siteId: site},
                    multiSelect: 1,
                    showSiteMenu: false,
                    disabledElementIds: canonicalElementIds,

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

                            if (! isSubmitted || haveDifferences($originalElementIds, elementIds)) {
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
                if (validateForm() && (isPending || isOrderChanged())) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('#elementTags').on('DOMNodeRemoved', function() {
                if (validateForm() && (isPending || isOrderChanged())) {
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

			$('#export-preview-links').on('click', function () {
				if (hasOrderId) {
					$data = {
						'id': $("input[type=hidden][name=id]").val()
					};

					Craft.postActionRequest('translations/export/export-preview-links', $data, function (response, textStatus) {
						if (response.success && response.previewFile) {
							var $iframe = $('<iframe/>', { 'src': Craft.getActionUrl('translations/files/export-file', { 'filename': response.previewFile }) }).hide();
							$('#regenerate-preview-urls').append($iframe);
						} else {
							Craft.cp.displayError(Craft.t('app', 'Unable to download your file.'));
						}
					});
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
                if (validateForm() && (isPending || isOrderChanged())) {
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
                    $form.submit();
                }else if ($(that).text() == "Update order") {
                    Craft.postActionRequest('translations/order/update-order', $form.serialize(), function(response, textStatus) {
                        if (response == null) {
                            Craft.cp.displayError(Craft.t('app', "Unable to update order."));
                            sendingOrderStatus(false);
                        } else if (textStatus === 'success' && response.success) {
                            if (response.message) {
                                Craft.cp.displayNotice(Craft.t('app', response.message));
                                setTimeout(function() {
                                    window.location.href = removeParams(location.href);
                                }, 200);
                            } else {
                                Craft.cp.displayError(Craft.t('app', "Something went wrong"));
                                sendingOrderStatus(false);
                            }
                        } else {
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

            // Update entrie button
            $updateLi = $('<li>');
            $dropdown.append($updateLi);

            $updateAction = $('<a>', {
                'class': 'update-element disabled noClick',
                'href': '#',
                'text': 'Update entries',
            });
            $updateLi.append($updateAction);
            this._addUpdateElementAction($updateAction);

            // Update and download entries button
            $updateAndDownloadAction = $('<a>', {
                'class': 'update-element disabled noClick',
                'href': '#',
                'text': 'Update entries and download files',
            });
            $updateLi.append($updateAndDownloadAction);
            this._addUpdateElementAction($updateAndDownloadAction, true);

            // Download/Sync TM Files Button
            $dropdown.append($('<hr>'));
            $downloadTmLi = $('<li>');
            $dropdown.append($downloadTmLi);
            $label = (isDefaultTranslator ? 'Download ' : 'Sync ') + 'memory alignment files';

            $downloadTmAction = $('<a>', {
                'class': 'download-tm-files',
                'href': '#',
                'text': $label,
            });
            $downloadTmLi.append($downloadTmAction);
            this._addDownloadTmFilesAction($downloadTmAction);

            // Delete Button
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
                $rows = getEntries(true);
                $rows.each(function() {
                    if ($(this).data('is-updated') == 1)
						elements.push($(this).data('element-id'));
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
                var $rows = getEntries(true);

                e.preventDefault();

                if (confirm(Craft.t('app', 'Are you sure you want to remove this entry from the order?'))) {
                    $rows.each(function() {
                        $(this).remove();
                    });

                    if (getEntryIds().length === 0) {
						$table.remove();
                        $('.translations-order-submit').addClass('disabled').prop('disabled', true);
                    }

                    var wordCount = 0;

                    $('#order [data-word-count]').each(function() {
                        wordCount += Number($(this).data('word-count'));
                    });

                    $('#wordCount').text(wordCount);

                    if (shouldCreateNewOrder()) {
                        setButtonText('.translations-submit-order.submit', 'Create new order');
                    } else {
                        if (! isOrderChanged()) {
                            setButtonText('.translations-submit-order.submit', 'Update order');
                        }
                    }

                    if (validateForm()) {
                        setSubmitButtonStatus(true);
                    } else {
                        setSubmitButtonStatus(false);
                    }

                    self.toggleElementAction(false);
                }
            });
        },
        _addDownloadTmFilesAction: function(that) {
            var self = this;
            var action = isDefaultTranslator ? 'download' : 'sync';
            $(that).on('click', function(e) {
                e.preventDefault();

                self.onStart();
                var elements = [];
                $rows = getEntries(true);
                $rows.each(function() {
                    // if ($(this).data('is-target-updated') == 1)
                    elements.push($(this).data('element-id'));
                });

                $data = {
                    elements: JSON.stringify(elements),
                    orderId: $("input[type=hidden][name=id]").val()
                }

                actions = {
                    download: 'translations/files/create-tm-export-zip',
                    sync: 'translations/files/sync-tm-files'
                }

                Craft.postActionRequest(actions[action], $data, function(response, textStatus) {
                    if (textStatus === 'success') {
                        if (response.success && !isDefaultTranslator) {
                            Craft.cp.displayNotice('Translation memory files sent successfully.');
                        } else if (response.success && response.tmFiles) {
                            let $downloadForm = $('#export-zip');
                            let $iframe = $('<iframe/>', {'src': Craft.getActionUrl('translations/files/export-file', {'filename': response.tmFiles})}).hide();
                            $downloadForm.append($iframe);

                            self.onComplete();
                        } else {
                            Craft.cp.displayError(Craft.t('app', response.message));
                            self.onComplete();
                        }
                    } else {
                        Craft.cp.displayError(Craft.t('app', 'Unable to '+ action +' files.'));
                        self.onComplete();
                    }
                });
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
						setTimeout(function() {
							self.onComplete(true);
						}, 500);
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
        },
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
            $updateButton = this.$elementActions.find('.update-element');
            // enable only if checked checkbox entry has the updated source
            var selectedHasChanges = false;
            $.each(getEntries(true), function() {
				if (!selectedHasChanges) selectedHasChanges = $(this).data('is-updated') == 1;
            });

            if (selectedHasChanges && !isPublished && !isCanceled) {
                $updateButton.removeClass('disabled noClick');
            } else {
                $updateButton.addClass('disabled noClick');
            }
        },
        toggleElementAction: function($show = true) {
            if ($show && this.$elementCheckboxes.filter(':checked').length > 0) {
                if (! this.$elementActions) {
                    this._buildElementActions();
                    this.$elementActions = $('#order-element-action-menu');
                } else {
                    $('#toolbar').show();
                }
                this.toggleUpdateActionButton();
            } else {
                $('#toolbar').hide();
            }
        }
    }

    $(function() {
        Craft.Translations.OrderDetails.init();

        Garnish.$doc.mouseover($.proxy(function() {
            Craft.initUiElements('#main');
        }, this));
    });
})(jQuery);
