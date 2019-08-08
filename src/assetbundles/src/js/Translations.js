(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }
    
    /**
     * Base Translations class
     */
    Craft.Translations = {
        trackJobProgressTimeout: null,

        init: function() {
        },
        trackJobProgressById: function(delay, force, params) {
            if (force && this.trackJobProgressTimeout) {
                clearTimeout(this.trackJobProgressTimeout);
                this.trackJobProgressTimeout = null;
            }

            if (delay === true) {
                // Determine the delay based on how long the displayed job info has remained unchanged
                var timeout = Math.min(60000, Craft.cp.displayedJobInfoUnchanged * 500);
                this.trackJobProgressTimeout = setTimeout($.proxy(this, '_trackJobProgressInternal', params), timeout);
            } else {
                this._trackJobProgressInternal(params);
            }
        },
        
        /**
         * 
         * @param {id, notice, url} params 
         */
        _trackJobProgressInternal: function(params) {
            if (Craft.cp.jobInfo.length) {
                var result;
                for (i = 0; i < Craft.cp.jobInfo.length; i++) {
                    var matches = true;
                    if (params.id !== Craft.cp.jobInfo[i].id){
                        matches = false;
                    }
                    if (matches){
                        result = Craft.cp.jobInfo[i];
                        this.trackJobProgressById(true, false, params);
                    }
                }

                // Job completed
                if(!result) {
                    Craft.cp.displayNotice(Craft.t('app', `${params.notice}`));

                    if (params.url && window.location.pathname.includes('translations/orders')) {
                        window.location.href=Craft.getUrl(params.url);
                    }
                } else {
                    // console.log(`Job progress: ${result.progress}`);
                }
            } else {
                // No jobs running or job completed
                Craft.cp.displayNotice(Craft.t('app', `${params.notice}`));

                if (params.url && window.location.pathname.includes('translations/orders')) {
                    window.location.href=Craft.getUrl(params.url);
                }
            }
        },
    };
    
    })(jQuery);