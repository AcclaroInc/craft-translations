(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    Craft.Translations.TranslatorIndex = {
        checkboxes: null,
        sideBar: null,

        hasSelections: function() {
            return $("#content input[type=checkbox]").filter("tbody :checked").length;
        },

        toggleCheckboxes: function(status, selectAll) {
            if (selectAll) {
                $("#content input[type=checkbox]").prop("checked", status);
                return;
            }
            selectAll = this.hasSelections() === $("#content input[type=checkbox]").length-1;
            // Toggle select all checkbox
            $("#translator-0").prop("checked", selectAll);
        },

        toggleEditDeleteButton: function() {
            var selected = this.hasSelections();
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
            var self = this;
            this.sideBar = $('#sidebar a');
            $('#sidebar a:first').addClass("sel");
            self.toggleCheckboxes(false, true);
            self.toggleEditDeleteButton();

            $("body").on('click', "input:checkbox", function() {
                var isSelectAll = $(this).attr("id") === "translator-0";
                self.toggleCheckboxes($(this).is(':checked'), isSelectAll);
                self.toggleEditDeleteButton();
            });

            this.sideBar.on('click', function() {
                if ($(this).hasClass("sel")) {
                    return;
                }
                self.sideBar.removeClass("sel");
                $(this).addClass("sel");
                data = self._getTranslatorsData($(this).data("key"));
                if (data) {
                    $('#content').find(".translations-element-index").replaceWith(data);
                    self.toggleEditDeleteButton();
                }
            });

            $(".settings.icon").on('click', function() {
                if (self.hasSelections()) {
                    self.toggleEditDeleteButton();
                }
            });

            $("body").on('click', '.edit-translator', function() {
                if ($(this).hasClass("disabled")) {
                    return false;
                }
                selected = $("#content input[type=checkbox]").filter("tbody :checked");
                window.location.href = $(selected).data("url");
            });

            $("body").on('click', '.delete-translator', function () {
                if ($(this).hasClass("disabled")) {
                    return;
                }
                
                if (confirm("Are you sure you want to delete selected translators?")) {
                    $data = {
                        translatorIds: null
                    };

                    $("#content input[type=checkbox]").filter("tbody :checked").each(function () {
                        if ($data.translatorIds) {
                            $data.translatorIds += "," + $(this).data("id");
                        } else {
                            $data.translatorIds = $(this).data("id");
                        }
                    });

                    params = {
                        data: $data
                    }

                    Craft.sendActionRequest('POST', 'translations/translator/delete', params)
                        .then(() => {
                            location.reload();
                        })
                        .catch(({ response }) => {
                            $message = 'An unknown error occurred.';
                            if (response.data.message != null) {
                                $message = response.data.message;
                            }
                            Craft.cp.displayError(Craft.t('app', $message));
                        });
                }
            });
        },

        _getTranslatorsData: function(key) {
            $mainDiv = $('<div>', {class: "translations-element-index"});

            params = {
                data: {service: key}
            }

            Craft.sendActionRequest('POST', 'translations/translator/get-translators', params)
                .then((response) => {
                    if (response.data != "") {
                        $table = $('<table>', {class: "data"});
                        $table.appendTo($mainDiv);
                        $thead = $('<thead>\
                            <tr>\
                                <td class="checkbox-cell selectallcontainer orderable">\
                                    <input type="checkbox" title="select-all" id="translator-0"/>\
                                    <label for="translator-0"></label>\
                                </td>\
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
                            $service = this.service == "export_import" ? "Export/Import"
                                : this.service.substr(0,1).toUpperCase()+this.service.substr(1);
                            $url = "translators/detail/"+this.id;
                            $statusClass = this.status == "active" ? "green" : "red";
                            $status = this.status.substr(0,1).toUpperCase()+this.status.substr(1);
                            $label = this.label !== "" ? this.label : $service;

                            $td = $("<td class=checkbox-cell><input type=checkbox title=select id="+$id+"\
                                data-url="+$url+" data-id="+this.id+"> <label for="+$id+"></label></td>"+'\
                                <td><a href='+$url+'>'+$label+'\
                                </a></td><td><span class="status '+$statusClass+'"></span>\
                                '+$status+'</td><td>'+$service+'</td>');

                            $td.appendTo($tr);
                            $tr.appendTo($tbody);
                        });
                    }
                })
                .catch(() => {
                    Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
                    return null;
                });

            return $mainDiv;
        }
    };

    $(function() {
        Craft.Translations.TranslatorIndex.init();
    });
})(jQuery);