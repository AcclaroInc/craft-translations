(function ($) {
    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    Craft.Translations.EntryPreview = Garnish.Base.extend({
        thumbLoader: null, elementSelect: null, elementSort: null, elementEditor: null, $container: null, $elementsContainer: null, $elements: null, _initialized: !1,

        init: function (t) {
            if (!$.isPlainObject(t)) {
                for (var i = {}, s = ["id", "elementType"], n = 0; n < s.length && void 0 !== arguments[n]; n++)i[s[n]] = arguments[n]; t = i
            }

            this.setSettings(t, Craft.Translations.EntryPreview.defaults);
            this.$container = this.getContainer();
            this.$elementsContainer = this.getElementsContainer();
            this.thumbLoader = new Craft.ElementThumbLoader, this.resetElements()
        },

        getContainer: function () { return $("#" + this.settings.id) },

        getElementsContainer: function () { return this.$container.children(".elements") },

        getElements: function () { return this.$elementsContainer.find('div.label') },

        resetElements: function () {
            this.$elements = this.addElements(this.getElements())
        },

        addElements: function (t) {
            var e = this;
            this.thumbLoader.load(t),
                this._handleShowElementEditor = function (t) {
                var i = $(t.currentTarget);
                e.elementEditor = e.createElementEditor(i);
            },
                this.addListener(t, "click", this._handleShowElementEditor)
        },

        createElementEditor: function (t, e) {
            return e = Craft.createElementEditor(this.settings.elementType, t, e)
        },
    }, {
        defaults: {
            id: null, elementType: null
        }
    })

})(jQuery);