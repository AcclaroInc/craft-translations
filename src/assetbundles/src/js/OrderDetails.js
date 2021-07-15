(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    function validateCreateOrderForm() {
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
            $('.translations-submit-order').removeClass('disabled');
            $('.translations-submit-order').attr('disabled', false);
        } else {
            $('.translations-submit-order').addClass('disabled');
            $('.translations-submit-order').attr('disabled', true);
        }
    }

    function updateOrderButtonStatus(that) {
        var $url = window.location.href;

        if (that) {
            if (that.data('id') == "files") {
                setSubmitButtonStatus(false);
            } else {
                if (validateCreateOrderForm()) {
                    setSubmitButtonStatus(true);
                }
            }
            return;
        }

        if ($url.includes("#files")) {
            setSubmitButtonStatus(false);
        } else {
            if (validateCreateOrderForm()) {
                setSubmitButtonStatus(true);
            }
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

    function isOrderChanged($old, $new) {
        var diff = $($old).not($new).get();
        if (diff == "" && $new.length == $old.length) {
            return false;
        }

        return true;
    }

    function validateOrderChanges($data) {
        for(var key in $data) {
            // Validate Target Sites
            if (key == "target") {
                var $originalTargetSiteIds = $('#originalTargetSiteIds').val().replace(/[\[\]\"]/g, '');
                var $all = $(':checkbox[name="targetSites"]');
                var $checkboxes = $all.is(':checked') ? $(':checkbox[name="targetSites[]"]') : $(':checkbox[name="targetSites[]"]:checked');
                var targetSites = [];

                $checkboxes.each(function() {
                    var $el = $(this);
                    var val = $el.attr('value');
                    targetSites.push(val);
                });
                if (isOrderChanged($originalTargetSiteIds.split(","), targetSites)) {
                    return true;
                }
            }
            // Validate Source Site
            if (key == "source") {
                var originalSourceSite = $('#originalSourceSiteId').val();
                var site = $("#sourceSiteSelect").val();
                if (typeof originalSourceSite !== 'undefined') {
                    originalSourceSite = originalSourceSite.split(",");
                }

                if (isOrderChanged(originalSourceSite, site.split(","))) {
                    return true;
                }
            }
            // Validate Entries
            if (key == "entry") {
                $originalElementIds = $('#originalElementIds').val().split(',');
                var currentElementIds = $('#currentElementIds').val().split(",");

                if (isOrderChanged($originalElementIds, currentElementIds)) {
                    return true;
                }
            }
        };

        return false;
    }

    function setButtonText($button, $value) {
        var $isNewOrder = $('#order-attr').data('submitted');
        if (! $isNewOrder) {
            return;
        }
        $($button).text($value);
    }

    Craft.Translations.OrderDetails = {

        $draftPublishButton: $('#draft-publish'),

        init: function() {
            updateOrderButtonStatus();
            // Target lang Ajax
            $(':checkbox[name="targetSites[]"], :checkbox[name="targetSites"]').on('change', function() {
                var $all = $(':checkbox[name="targetSites"]');
                var $checkboxes = $all.is(':checked') ? $(':checkbox[name="targetSites[]"]') : $(':checkbox[name="targetSites[]"]:checked');
                var targetSites = [];
                var targetSitesLabels = [];

                $checkboxes.each(function() {
                    var $el = $(this);
                    var val = $el.attr('value');
                    var label = $.trim($el.next('label').text());
                    targetSites.push(val);
                    targetSitesLabels.push(label);
                });
            
                $('[data-order-attribute=targetSites]').html(targetSitesLabels.join(', '));
                var $originalTargetSiteIds = $('#originalTargetSiteIds').val().replace(/[\[\]\"]/g, '');

                if (isOrderChanged($originalTargetSiteIds.split(","), targetSites)) {
                    setButtonText('.translations-submit-order', 'Submit new order');
                } else {
                    if (! validateOrderChanges({source: 'source', entry: 'entry'})) {
                        setButtonText('.translations-submit-order', 'Update order');
                    }
                }

                if (validateCreateOrderForm()) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('select[name=version]').on('change', function() {
                var elementIdVersions = $('#elementIdVersions').val().split(",");
                var elementId = $(this).attr('id').replace('version_', '');
                var $key = elementId+'_'+$(this).val();
                elementIdVersions = elementIdVersions.filter(function(val, key) {
                    return !val.includes(elementId+'_');
                });

                elementIdVersions.push($key);
                elementIdVersions.join(',');

                $('#elementIdVersions').val(elementIdVersions);
            });

            $('#title').on('change, keyup', function() {
                if (validateCreateOrderForm()) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }
            });

            $('li[data-id]').on('click', function () {
                updateOrderButtonStatus($(this));
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

                    if (isOrderChanged($originalElementIds, currentElementIds.split(","))) {
                        setButtonText('.translations-submit-order', 'Submit new order');
                    } else {
                        if (! validateOrderChanges({source: 'source', target: 'target'})) {
                            setButtonText('.translations-submit-order', 'Update order');
                        }
                    }

                    if (currentElementIds != "" && validateCreateOrderForm()) {
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

                if (isOrderChanged(originalSourceSite, site.split(","))) {
                    setButtonText('.translations-submit-order', 'Submit new order');
                } else {
                    if (! validateOrderChanges({target: 'target', entry: 'entry'})) {
                        setButtonText('.translations-submit-order', 'Update order');
                    }
                }

                if (validateCreateOrderForm()) {
                    setSubmitButtonStatus(true);
                } else {
                    setSubmitButtonStatus(false);
                }

                window.history.pushState("", "", url);
                // window.location = url;

            });

            $('.translations-submit-order').on('click', function(e) {
                $('.translations-loader-msg').removeClass('hidden');
            });

            $('.translations-order-form').on('submit', function(e) {
                $('.translations-loader').removeClass('hidden');
                $('.btn[type=submit]').addClass('disabled').css('pointer-events', 'none');
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

                            if (isOrderChanged($originalElementIds, elementIds)) {
                                elementUrl += "&changed=1";
                            }

                            window.location.href = $url + '?sourceSite='+site+elementUrl;
                        }
                    }, this),
                    closeOtherModals: false,
                });
            });
        }
    }

    $(function() {
        Craft.Translations.OrderDetails.init();
    });
})(jQuery);