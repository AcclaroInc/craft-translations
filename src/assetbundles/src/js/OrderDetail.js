(function($) {

if (typeof Craft.Translations === 'undefined') {
    Craft.Translations = {};
}

Craft.Translations.OrderDetail = {
    goToStep: function(el) {
        var href = $(el).attr('href');
        var stepSelector = href.replace(/^.*(#step\d+)$/, '$1');
        var $btn = $('.translations-order-step-btngroup .btn[href="'+href+'"]');
        var $step = $(stepSelector);
        var $steps = $('.translations-order-step');

        $btn.closest('li').nextAll('li').find('.btn').removeClass('active').addClass('disabled').removeClass('prev');
        $btn.closest('li').prevAll('li').find('.btn').removeClass('active').removeClass('disabled').addClass('prev');
        $btn.removeClass('disabled').removeClass('prev');
        $steps.not($step).removeClass('active');
        $step.addClass('active');
    },

    toggleInputState: function(el, valid, message) {
        var $el = $(el);
        var $input = $el.closest('.input');
        var $errors = $input.find('ul.errors');

        $el.toggleClass('error', !valid);
        $input.toggleClass('errors', !valid);

        if (valid) {
            $errors.remove();
        } else if ($errors.length === 0) {
            $('<ul>', {'class': 'errors'})
                .appendTo($input)
                .append($('<li>', {'text': message}));
        }
    },

    validateStepElement: function(el) {
        var $step = $(el).closest('.translations-order-step');
        var stepId = $step.attr('id').replace(/^step(\d+)$/, '$1');

        return this.validateStep(stepId);
    },

    validateStep: function(stepId) {
        var valid = true;

        switch (stepId) {
            case '1':
                break;
            case '2':
                var $checkboxes = $('input[type=checkbox][name="targetSites[]"]');

                valid = $checkboxes.filter(':checked').length > 0;

                this.toggleInputState($checkboxes, valid, Craft.t('app', 'Please choose one or more target sites.'));

                break;
            case '3':
                var $requestedDueDate = $('[id^=requestedDueDate][id$=-date]');
                
                valid = $requestedDueDate.val() === '' || /^\d{1,2}\/\d{1,2}\/\d{4}$/.test($requestedDueDate.val());
                
                this.toggleInputState($requestedDueDate, valid, Craft.t('app', 'Please enter a valid date.'));

                break;
            case '4':
                var $translatorId = $('#translatorId');

                valid = !!$translatorId.val();

                this.toggleInputState($translatorId, valid, Craft.t('app', 'Please choose a translation service.'));

                break;
        }

        return valid;
    },

    init: function() {
        var self = this;
        var elementIds = [];
        var modal;

        $('input, select').on('keypress', function(e) {
            if (e.which === 13) {
                event.preventDefault();
            }
        });

        $('#title, [id^=requestedDueDate][id$=-date], #comments').on('change', function() {
            var $el = $(this);
            var name = $el.attr('name');
            var val = $el.val();
            var $bound = $('[data-order-attribute="'+name+'"]');

            if (val !== '') {
                $bound.text(val);
            } else {
                $bound.html('<i>'+Craft.t('app', 'None')+'</i>');
            }
        });

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
        });

        $('.translations-order-step-next').on('click', function(e) {
            e.preventDefault();

            if (self.validateStepElement(this)) {
                self.goToStep(this);
            }
        });

        $('.translations-order-step-prev').on('click', function(e) {
            e.preventDefault();

            self.goToStep(this);
        });

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
                    $('.translations-order-submit-btn').addClass('disabled').prop('disabled', true);
                }

                var wordCount = 0;

                $('[data-word-count]').each(function() {
                    wordCount += Number($(this).data('word-count'));
                });

                $('[data-order-attribute=entriesCount]').text(entriesCount);

                $('[data-order-attribute=wordCount]').text(wordCount);

                var param = window.location.search;
                param = param.replace("&elements[]="+$button.attr('data-element'), "");
                param = 'admin/translations/orders/new'+param;

                window.history.pushState("object or string", "Translations", "/"+param);
                var currentElementIds = $('#currentElementIds').val();
                currentElementIds = currentElementIds.replace($button.attr('data-element'), '').replace(',,', ',');
                $('#currentElementIds').val(currentElementIds);
            }
        });

        $('.translations-order-submit-btn').on('click', function(e) {
            if (!self.validateStep('4')) {
                e.preventDefault();
            } else {
                $(this).closest('.translations-order-form')
                    .find('.translations-loader-msg')
                    .removeClass('hidden');
    
                // setTimeout(function(){window.location.href=Craft.getUrl('translations/orders')},1000)
            }

        });

        $('.translations-order-form').on('submit', function(e) {
            if (!self.validateStep('4')) {
                e.preventDefault();
            }

            var $form = $(this);

            $form.find('.translations-loader').removeClass('hidden');
            $form.find('.btn[type=submit]').addClass('disabled').css('pointer-events', 'none');
        });

        $("input[id^=requestedDueDate]").datepicker('option', 'minDate', +1)

        $(document).on('click', '.translations-order-step-btngroup .btn.prev', function(e) {
            e.preventDefault();

            self.goToStep(this);
        });


        $(".editOrder").on('click', function (e) {
            $('.order_label').hide();
            $('#order_name_input').show();
        });

        $(".cancelOrderName").on('click', function (e) {
            $('.order_label').show();
            $('#order_name_input').hide();
        });

        $('#saveOrderName').on('click', function (e) {
            var order_name = $('#new_order_name').val();
            var order_id = $('#order_id').val();

            var data = {
                action: 'translations/base/edit-order-name',
                orderId: order_id,
                order_name: order_name
            };

            data[Craft.csrfTokenName] = Craft.csrfTokenValue;

            $.post(
                location.href,
                data,
                function (data) {
                    if (!data.success) {
                        alert(data.error);
                    } else {
                        $('#order_name').html(order_name);
                        $('.order_label').show();
                        $('#order_name_input').hide();

                        Craft.cp.displayNotice(Craft.t('app', 'Order name updated.'));

                        location.reload();
                    }
                },
                'json'
            );
        });

        $('.tab-xml').on('click', function(e) {
            console.log("  Xml clicked ");
            $("#xml").show();
            $("#tab-xml").addClass('sel');
            $("#tab-visual").removeClass('sel');
            $("#visual").hide();
        });
        $('.tab-visual').on('click', function(e) {
            console.log("  Visual clicked ");
            $("#xml").hide();
            $("#visual").show();
            $("#tab-visual").addClass('sel');
            $("#tab-xml").removeClass('sel');

            //let fileId = $('#diff-file-id').val();
            //getFileDiffHtml(fileId);

        });

        function getFileDiffHtml(location) {
            //let location = $('#file-diff-html-url').val() + '/' + fileId;
            $.get(
                location,
                function (data) {
                    if (!data.success) {
                        alert(data.error);
                    } else {
                        data = data.data;

                        document.getElementById('original-url').src = data.originalUrl;
                        document.getElementById('new-url').src = data.newUrl;

                        $('#close-diff-modal-entry').on('click', function(e) {
                            e.preventDefault();
                            modal.hide();
                        });
                    }
                },
                'json'
            );
        }

        function createModal() {
            return new Garnish.Modal($('#diff-modal-entry').removeClass('hidden'), {
                autoShow: false,
            });
        }

        $('.view-diff').on('click', function(e) {
            e.preventDefault();
            var $el = $(this);
            //var file_id = $el.attr('data-file-id');
            var location = $el.attr('href');

            var show_visual = $el.data("show-visual");

            if (!modal) {
                modal = createModal();
            }

            $.get(
                location,
                function (data) {
                    if (!data.success) {
                        alert(data.error);
                    } else {
                        data = data.data;

                        var diffHtml = Diff2Html.getPrettyHtml(
                            data.diff,
                            {inputFormat: 'diff', showFiles: false, matching: 'lines', outputFormat: 'side-by-side'}
                        );

                        var classNames = [
                            'entryId',
                            'entryName',
                            'siteLabel',
                            'dateApplied',
                            'dateDelivered',
                            'wordDifference'
                        ];

                        if (!modal) {
                            modal = createModal();
                        } else {
                            if (!show_visual) {
                                $('.tab-visual').click();
                            } else {
                                $('.tab-xml').click();
                                $('#visual-li').addClass('disabled');
                                $('#tab-visual').off('click');
                            }
                        }
                        // Show the modal
                        modal.show();

                        // Set modification details
                        for (let index = 0; index < classNames.length; index++) {
                            $('.' + classNames[index]).html(data[classNames[index]]);
                        }
                        $('#diff-element').val(data.fileId);
                        if(data.fileStatus == 'complete') {
                            $('#apply-translation').attr('disabled', false);
                            $('#apply-translation').removeClass('disabled');
                        } else {
                            $('#apply-translation').attr('disabled', true);
                            $('#apply-translation').addClass('disabled');
                        }

                        $('#diff-file-id').val(data['fileId']);

                        // Add the diff html
                        document.getElementById("modal-body-entry").innerHTML = diffHtml;

                        $('#apply-translation').one('click', function(e) {
                            $(".apply-translation").addClass("disabled");
                            $(".apply-translation").prop("value", "");
                            $(".apply-translation").css('margin-right', 4);
                            $(".apply-translation").width(110);
                            $(".apply-translation").toggleClass("spinner");
                        });

                        $('#close-diff-modal-entry').on('click', function(e) {
                            e.preventDefault();
                            modal.hide();
                        });
                    }
                },
                'json'
            );

            //let fileId = location.substring(location.lastIndexOf('/') + 1);
            location = location.replace("/get-file-diff/", "/get-file-diff-html/");
            getFileDiffHtml(location);
        });

        $("#duplicate-continue").on("click", function (e) {

            $('#duplicate-continue').attr('disabled', true);
            $('#duplicate-continue').addClass('disabled');
            $('#duplicate-modal').addClass('elements busy');
            var skip_or_replace = $("input[name='skip_replace']:checked").val();
            $('#checkDuplicate').val(0);

            addEntries(skip_or_replace);
        });

        $("#duplicate-cancel").on("click", function (e) {
            $dup_modal.hide();
        });

        $('.duplicate-warning', '#global-container').infoicon();

        $("#sourceSiteSelect").change(function (e) {
            $(window).off('beforeunload.windowReload');
            var site = $("#sourceSiteSelect").val();
            var url = $("#newOrderUrl").val();

            var currentElementIds = [];
            if (typeof $('#currentElementIds').val() !== 'undefined') {
                currentElementIds = $('#currentElementIds').val().split(',');
            }
            url += '?sourceSite='+site;
            if (currentElementIds) {
                currentElementIds.forEach(function (element) {
                    url += '&elements[]='+element;
                })
            }
            if(url.indexOf('#step2') == -1) {
                url += '#step2';
            }
            window.location = url;

        });
        var hash = window.location.hash;
        if (hash == '#step2') {
            $('#step1').toggleClass( "active" );
            $('#step2').toggleClass( "active" );
            $('#step_first').removeClass('disabled');
            $('#step_first').addClass('prev');
            $('#step_two').toggleClass('disabled');
        }

        $(".addEntries").on('click', function (e) {
            elementIds = currentElementIds = [];

            var sourceSites = [];
            var site = $("#sourceSiteSelect").val();
            $("input:hidden.sourceSites").each(function() {
                sourceSites.push($(this).val());
            });

            var currentElementIds = [];
            if (typeof $('#currentElementIds').val() !== 'undefined') {
                currentElementIds = $('#currentElementIds').val().split(',');
            }

            this.assetSelectionModal = Craft.createElementSelectorModal('craft\\elements\\Entry', {
                storageKey: null,
                sources: null,
                elementIndex: null,
                criteria: {siteId: this.elementSiteId},
                multiSelect: 1,
                disabledElementIds: currentElementIds,
                onSelect: $.proxy(function(elements) {

                    $('#content').addClass('elements busy');
                    if (typeof $('#currentElementIds').val() !== 'undefined') {
                        currentElementIds = $('#currentElementIds').val().split(',');
                    }
                    if (elements.length) {
                        var elementUrl = '';
                        for (var i = 0; i < elements.length; i++) {
                            var element = elements[i];
                            elementIds.push(element.id);
                            elementUrl += '&elements[]='+element.id;

                            if (Array.isArray(currentElementIds)) {
                                index = currentElementIds.indexOf(element.id.toString());
                                if (index > -1) {
                                    currentElementIds.splice(index, 1);
                                }
                            }
                        }
                        for (var i = 0; i < currentElementIds.length; i++) {
                            if(currentElementIds[i]) {
                                elementUrl += '&elements[]='+currentElementIds[i];
                            }
                        }
                        if ($('#addNewEntries').val() == 1) {
                            window.location.href=Craft.getUrl('translations/orders/new')+'?sourceSite='+site+elementUrl;
                        } else {
                            addEntries();

                            setTimeout(function(){ $('#content').removeClass('elements busy') }, 5000);
                        }

                    }
                }, this),
                closeOtherModals: false,
            });

            this.assetSelectionModal.show();

        });

        function addEntries(skipOrReplace=null) {

            var order_id = $('#order_id').val();
            var checkDuplicate = $('#checkDuplicate').val();
            var post_data = {
                action: 'translations/base/add-entries',
                elements: elementIds,
                orderId: order_id,
                checkDuplicate: checkDuplicate,
                skipOrReplace: skipOrReplace,
            };

            post_data[Craft.csrfTokenName] = Craft.csrfTokenValue;

            var addEntriesUrl = $('#addEntriesLink').val();

            $.post(
                'translations/base/add-entries',
                post_data,
                function (data) {
                    if (!data.success) {
                        alert(data.error);
                    } else {

                        $(".dup-entries-title").empty();
                        if (data.data.duplicates.length>0) {
                            if (data.data.duplicates) {
                                $.each( data.data.duplicates, function( i, val ) {
                                    $(".dup-entries-title").append('<li>'+val+'</li>');
                                })
                                $dup_modal.show();
                            }
                        } else {
                            // console.log(" Adding entries... ");
                            // console.log(data);

                            if (post_data.skipOrReplace == 'replace') {
                                Craft.cp.displayNotice(Craft.t('app', (post_data.elements.length <= 1) ? 'Entry replaced' : 'Entries replaced'));
                            } else {
                                Craft.cp.displayNotice(Craft.t('app', (post_data.elements.length <= 1) ? 'Entry added' : 'Entries added'));
                            }

                            location.reload();
                        }
                    }
                },
                'json'
            );
        }

        $dup_modal = new Garnish.Modal($('#duplicate-modal').removeClass('hidden'), {
            autoShow: false,
        });
    }
};

$(function() {
    Craft.Translations.OrderDetail.init();
});

})(jQuery);
