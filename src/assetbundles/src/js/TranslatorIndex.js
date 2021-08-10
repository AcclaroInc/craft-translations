(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    Craft.Translations.TranslatorIndex = {
        checkboxes: null,
        sideBar: null,

        hasSelections: function() {
            return this.checkboxes.filter("tbody :checked").length;
        },

        toggleCheckboxes: function(status, selectAll) {
            if (selectAll) {
                this.checkboxes.prop("checked", status);
                return;
            }
            selectAll = this.hasSelections() === this.checkboxes.length-1;
            // Toggle select all checkbox
            $("#translator-0").prop("checked", selectAll);
        },

        toggleEditDeleteButton: function(that) {
            var selected = that.hasSelections();
            var editButton = $(".edit-translator");
            var deleteButton = $(".delete-translator");

            if (selected == 1) {
                editButton.removeClass("disabled");
                deleteButton.removeClass("disabled");
            } else if (selected > 1) {
                deleteButton.removeClass("disabled");
                editButton.addClass("disabled");
            } else {
                editButton.addClass("disabled");
                deleteButton.addClass("disabled");
            }
        },

        init: function() {
            var self = Craft.Translations.TranslatorIndex;
            this.checkboxes = $('input[type="checkbox"]');
            this.sideBar = $('#sidebar a');
            $('#sidebar a:first').addClass("sel");

            this.checkboxes.on('click', function() {
                var isSelectAll = $(this).attr("id") === "translator-0";
                self.toggleCheckboxes($(this).is(':checked'), isSelectAll);
                self.toggleEditDeleteButton(self);
            });

            this.sideBar.on('click', function() {
                self.sideBar.removeClass("sel");
                $(this).addClass("sel");
                data = self._getTranslatorsData($(this).data("key"));
                if (data) {
                    $('#content').find(".translations-element-index").replaceWith(data);
                }
            });

            $("#action-button").on('click', function() {
                if (self.hasSelections()) {
                    self.toggleEditDeleteButton(self);
                }
            });

            $("body").on('click', '.edit-translator', function(e) {
                if ($(this).hasClass("disabled")) {
                    return;
                }
                selected = self.checkboxes.filter("tbody :checked");
                window.location.href = $(selected).data("url");
            });

            $("body").on('click', '.delete-translator', function() {
                if ($(this).hasClass("disabled")) {
                    return;
                }

                data = {
                    translatorIds: null
                };

                self.checkboxes.filter("tbody :checked").each(function() {
                    if (data.translatorIds) {
                        data.translatorIds += "," + $(this).data("id");
                    } else {
                        data.translatorIds = $(this).data("id");
                    }
                });

                Craft.postActionRequest('translations/translator/delete', data, $.proxy(function(response, textStatus) {
                    if (textStatus === 'success') {
                        location.reload();
                    } else {
                        Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
                    }
                }, this));
            });
        },

        _getTranslatorsData: function(key) {
            $mainDiv = $('<div>', {class: "translations-element-index"});

            Craft.postActionRequest('translations/translator/get-translators', {service: key}, $.proxy(function(response, textStatus) {
                if (textStatus === 'success' && response.data != "") {
                    $table = $('<table>', {class: "data"});
                    $table.appendTo($mainDiv);
                    $thead = $('<thead>\
                        <tr>\
                            <th class="checkbox-cell selectallcontainer orderable">\
                                <input type="checkbox" title="select-all" id="translator-0"/>\
                                <label for="translator-0"></label>\
                            </th>\
                            <th>Name</th>\
                            <th>Status</th>\
                            <th>Service</th>\
                        </tr>\
                    </thead>');
                    $thead.appendTo($table);

                    $tbody = $('<tbody>');
                    $tbody.appendTo($table);

                    $.each(response.data, function() {
                        $tr = $('<tr>');

                        $id = "translator-"+this.id;
                        $service = this.service == "export_import" ? "Export/Import" : this.service;
                        $url = 'url(translations/translators/detail/'+$id+')';
                        $statusClass = this.status == "active" ? "green" : "red";

                        $td = $("<td class=checkbox-cell><input type=checkbox title=select id="+$id+"\
                            data-url="+$url+"/> <label for="+$id+"></label></td>"+'<td><a href='+$url+'>'+this.label+'\
                            </a></td><td><span class="status '+$statusClass+'"></span>\
                            '+this.status+'</td><td>'+$service+'</td>');

                        $td.appendTo($tr);
                        $tr.appendTo($tbody);
                    });
                } else {
                    Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
                    return null;
                }
            }, this));

            return $mainDiv;
        }
    };

    $(function() {
        Craft.Translations.TranslatorIndex.init();
    });
})(jQuery);