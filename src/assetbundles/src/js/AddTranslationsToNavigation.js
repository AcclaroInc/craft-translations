(function ($) {
  if (typeof Craft.Translations === "undefined") {
    Craft.Translations = {};
  }

  function unique(array) {
    return $.grep(array, function (el, index) {
      return index === $.inArray(el, array);
    });
  }

  Craft.Translations.AddTranslationsToNavigation = {
    assets: [],
    $btn: null,

    init: function (orders, navId) {
      this.addTranslationOrderButton(orders, navId);
    },

    updateSelectedAssets: function () {
      var entries = [];

      $(".elements table.data tbody tr.sel[data-id]").each(function () {
        entries.push($(this).data("id"));
      });

      this.assets = unique(entries);

      $(this.$btn[0]).toggleClass("link-disabled", this.assets.length === 0);
      $(this.$menubtn[0]).toggleClass(
        "link-disabled",
        this.assets.length === 0
      );

      this.updateCreateNewLink();
    },

    updateCreateNewLink: function () {
      var href = this.$btn.attr("href").split("?")[0];

      href += "?sourceSite=" + this.getSourceSite();

      for (var i = 0; i < this.assets.length; i++) {
        href += "&elements[]=" + this.assets[i];
      }

      this.$btn.attr("href", href);
    },

    getSourceSite: function () {
      var localeMenu = $(".sitemenubtn").data("menubtn").menu;

      // Figure out the initial locale
      var $option = localeMenu.$options.filter(".sel:first");

      if ($option.length === 0) {
        $option = localeMenu.$options.first();
      }

      var siteId = $option.data("site-id").toString();

      return siteId;
    },

    addTranslationOrderButton: function (orders, assetId) {
      var self = this;

      var $btncontainer = document.createElement("div");
      $btncontainer.id = "translations-field";
      $btncontainer.className = "field";

      var $btngroup = $("<div>", { class: "btngroup translations-dropdown" });

      $btngroup.prependTo("header#header > div:last");

      this.$btn = $("<a>", {
        class: "btn icon",
        href: "#",
        "data-icon": "language",
      });

      this.$btn.html(
        "<span class='btn-text'>" +
          Craft.t("app", "New Translation") +
          "</span>"
      );

      this.$menubtn = $("<div>", {
        class: "btn menubtn",
      });

      this.$btn.addClass("link-disabled");
      this.$menubtn.addClass("link-disabled");

      this.$btn.appendTo($btngroup);

      this.$menubtn.appendTo($btngroup);

      this.$menubtn.on("click", function (e) {
        e.preventDefault();
      });

      var $menu = $("<div>", { class: "menu" });

      $menu.appendTo($btngroup);

      var $dropdown = $("<ul>", { class: "" });

      $dropdown.appendTo($menu);

      if (orders.length == "0") {
        var $item = $("<li>");

        $item.appendTo($dropdown);

        var $link = $("<a>", {
          class: "link-disabled",
          text: "No saved orders available for this site...",
        });

        $link.appendTo($item);
      }

      for (var i = 0; i < orders.length; i++) {
        var order = orders[i];

        var $item = $("<li>");

        $item.appendTo($dropdown);

        var $link = $("<a>", {
          href: "#",
          text: "Add to " + order.title,
        });

        $link.appendTo($item);

        $link.data("order", order);

        $link.on("click", function (e) {
          e.preventDefault();

          var order = $(this).data("order");

          var $form = $("<form>", {
            method: "POST",
          });

          $form.hide();

          $form.appendTo("body");

          $form.append(Craft.getCsrfInput());

          var $hiddenAction = $("<input>", {
            type: "hidden",
            name: "action",
            value: "translations/base/add-elements-to-order",
          });

          $hiddenAction.appendTo($form);

          var $hiddenOrderId = $("<input>", {
            type: "hidden",
            name: "id",
            value: order.id,
          });

          $hiddenOrderId.appendTo($form);

          var $hiddenSourceSite = $("<input>", {
            type: "hidden",
            name: "sourceSite",
            value: self.getSourceSite(),
          });

          $hiddenSourceSite.appendTo($form);

          for (var j = 0; j < self.assets.length; j++) {
            $("<input>", {
              type: "hidden",
              name: "elements[]",
              value: self.assets[j],
            }).appendTo($form);
          }

          var $submit = $("<input>", {
            type: "submit",
          });

          $submit.appendTo($form);

          $form.submit();
        });
      }

      var $link = Craft.getUrl("translations/orders/create", {
        "elements[]": assetId,
        sourceSite: self.getSourceSite(),
      });

      this.$btn.attr("href", $link);

      this.$menubtn.menubtn();

      $(document).on(
        "click",
        ".elements .checkbox, table[data-name=Nodes]",
        function () {
          self.updateSelectedAssets();
        }
      );

      this.$btn.on("click", function (e) {
        e.preventDefault();

        var $form = $("<form>", {
          method: "POST",
          action: Craft.getUrl("translations/orders/create"),
        });

        $form.hide();

        $form.appendTo("body");

        $form.append(Craft.getCsrfInput());

        var $hiddenSourceSite = $("<input>", {
          type: "hidden",
          name: "sourceSite",
          value: self.getSourceSite(),
        });

        $hiddenSourceSite.appendTo($form);

        for (var j = 0; j < self.assets.length; j++) {
          $("<input>", {
            type: "hidden",
            name: "elements[]",
            value: self.assets[j],
          }).appendTo($form);
        }

        var $submit = $("<input>", {
          type: "submit",
        });

        $submit.appendTo($form);

        $form.submit();
      });
    },
  };
})(jQuery);
