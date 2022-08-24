(function ($) {
    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }
    var O = Garnish;

    Craft.Translations.FilePreview = O.Base.extend({
        $container: null,
        $previewBtn: null,
        openingPreview: !1,
        preview: null,
        activatedPreviewToken: !1,
        previewTokenQueue: null,
        previewLinks: null,
        scrollY: null,
        hasSpinner: null,
        $originalContent: null,

        init: function (t, e) {
            this.$container = $('#files'),
            this.$originalContent = $('#content'),
            this.setSettings(e, Craft.Translations.FilePreview.defaults),

            this.previewLinks = [],
            this.previewTokenQueue = this._createQueue(),
            this.$previewBtn = this.$container.find(t);
            var s = $("#page-title");

            if ($('#page-title').find('div.revision-spinner').length == 0) {
                this.$spinner = $("<div/>", { class: "revision-spinner spinner hidden", title: Craft.t("app", "Loading") }).appendTo(s);
            } else {
                this.$spinner = $('#page-title').find('div.revision-spinner');
            }

            if (this.settings.previewTargets.length) {
                this.addListener(this.$previewBtn, "click", "openPreview");
            }
        },

        updatePreviewLinks: function () {
            var t = this; this.previewLinks.forEach((function (e) { t.updatePreviewLinkHref(e), t.activatedPreviewToken && t.removeListener(e, "click"); }));
        },

        updatePreviewLinkHref: function (t) {
            t.attr("href", this.getTokenizedPreviewUrl(t.data("targetUrl"), null, !1));
        },

        activatePreviewToken: function () {
            this.settings.isLive || (this.activatedPreviewToken = !0, this.updatePreviewLinks());
        },

        getPreviewTokenParams: function () {
            var t = { elementType: this.settings.elementType, canonicalId: this.settings.canonicalId, siteId: this.settings.siteId, previewToken: this.settings.previewToken };

            return this.settings.draftId && (t.draftId = this.settings.draftId), t;
        },

        getPreviewToken: function () {
            var t = this;
            return this.previewTokenQueue.push((function () { return new Promise((function (e, i) { t.activatedPreviewToken ? e(t.settings.previewToken) : Craft.sendActionRequest("POST", "preview/create-token", { data: t.getPreviewTokenParams() }).then((function () { t.activatePreviewToken(), e(t.settings.previewToken); })).catch(i); })); }));
        },

        getTokenizedPreviewUrl: function (t, e, i) {
            var s = this;
            void 0 === i && (i = !0);
            var n = {};

            if (!e && this.settings.isLive || (n[e || "x-craft-preview"] = Craft.randomString(10)), this.settings.siteToken && (n[Craft.siteToken] = this.settings.siteToken), this.settings.isLive) {
                var a = Craft.getUrl(t, n);
                return i ? new Promise((function (t) { t(a); })) : a;
            }

            if (!this.settings.previewToken)
                throw "Missing preview token";

            n[Craft.tokenParam] = this.settings.previewToken;

            var r = Craft.getUrl(t, n);

            if (this.activatedPreviewToken)
                return i ? new Promise((function (t) { t(r); })) : r;

            if (i)
                return new Promise((function (t, e) { s.getPreviewToken().then((function () { t(r); })).catch(e); }));

            var o = this.getPreviewTokenParams();

            return o.redirect = r, Craft.getActionUrl("preview/create-token", o);
        },

        getPreview: function () {
            var t = this;

            return (this.preview = new Craft.Preview(this),
                this.preview.on("open", (function () {
                        $preview = $(document).find('div[aria-labelledby=lp-preview-heading]');

                        if ($preview.length) {
                            $preview.find('.lp-editor-container').addClass('hidden');
                            $button = $preview.find('div.lp-editor-container header button');
                            $button.addClass('margin-right-10');
                            $preview.find('.lp-preview-container').addClass('w-100');
                            $preview.find('.lp-preview-container header').prepend($button);
                        }
                    })),
                    this.preview.on("close", (function () {
                        t.scrollY && (window.scrollTo(0, t.scrollY), t.scrollY = null);

                        // Remove preview modal on close as we can have multipe preview files.
                        t.removePreview();
                    }))
                ), this.preview;
        },

        removePreview: function () {
            $preview = $(document).find('div[aria-labelledby=lp-preview-heading]');

            if ($preview.length >= 1) {
                $preview.remove();
                $(document).find('div.modal-shade.dark').remove();
                this.preview = null;
            }
        },

        openPreview: function () {
            var t = this;

            return new Promise((function (e, i) {
                t.openingPreview = !0,
                t.ensureIsDraftOrRevision(!0)
                    .then((function () {
                        t.scrollY = window.scrollY, t.getPreview().open(),
                        t.openingPreview = !1, e();
                    }))
                    .catch(i);
            }));
        },

        ensureIsDraftOrRevision: function (t) {
            var e = this;

            return new Promise((function (i, s) {
                if (e.settings.draftId)
                    i();
                else {
                    return void i();
                }
            }));
        },

        spinners: function () { return this.preview ? this.$spinner.add(this.preview.$spinner) : this.$spinner; },

        showSpinner: function () { this.spinners().removeClass("hidden"); },

        hideSpinner: function () { this.spinners().addClass("hidden"); },

        _createQueue: function () {
            var t = this,
            e = new Craft.Queue;
            return e.on("beforeRun", (function () {
                t.showSpinner();
            })), e.on("afterRun", (function () {
                t.hideSpinner();
            })), e;
        },
    },{
        defaults:
        {
            canonicalId: null, draftId: null, elementType: null, isLive: !1, previewTargets: [], previewToken: null, siteId: null, siteToken: null
        }
    });

})(jQuery);