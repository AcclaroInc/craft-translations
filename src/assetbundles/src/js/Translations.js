(function($) {

    if (typeof Craft.Translations === 'undefined') {
        Craft.Translations = {};
    }

    /**
     * Base Translations class
     */
    Craft.Translations = {
        trackJobProgressTimeout: null,
        job: null,

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
        _trackJobProgressInternal: function (params) {
            Craft.queue.push(
                () => new Promise((resolve, reject) => {
                    Craft.sendActionRequest('POST', 'queue/get-job-info?dontExtendSession=1')
                        .then(() => {
                            this.trackJobProgressTimeout = null;

                            if (Craft.cp.jobInfo.length) {
                                let inQueue = false;
                                // Check to see if it matches our jobId
                                for (i = 0; i < Craft.cp.jobInfo.length; i++) {
                                    var matches = false;
                                    if (params.id == Craft.cp.jobInfo[i].id) {
                                        matches = true;
                                        inQueue = true;
                                    }

                                    if ((i + 1) == Craft.cp.jobInfo.length && !inQueue) {
                                        // Job is not in queue
                                        if (this.job) {
                                            Craft.cp.displayNotice(Craft.t('app', `${params.notice}`));

                                            if (params.url && window.location.pathname.includes('translations/orders')) {
                                                window.location.href=Craft.getUrl(params.url);
                                            }
                                        }
                                    }

                                    if (matches) {
                                        this.job = Craft.cp.jobInfo[i];

                                        if (!this.job.error) {
                                            console.log(`Translation job progress: ${Craft.cp.jobInfo[i].progress}`);
                                            // Check again after a delay
                                            this.trackJobProgressById(true, false, params);
                                        } else {
                                            console.log('Job failed');
                                            Craft.cp.displayError(Craft.t('app', `${this.job.error}`));
                                        }
                                    }
                                }
                            } else {
                                // Job is either completed or no jobs running
                                Craft.cp.displaySuccess(Craft.t('app', `${params.notice}`));

                                if (params.url && window.location.pathname.includes('translations/orders')) {
                                    window.location.href=Craft.getUrl(params.url);
                                }
                            }
                            resolve();
                        })
                        .catch(reject);
                })
            );
        },

        trackJobCompletion(jobId, { onComplete, onError }) {
            Craft.cp.trackJobProgress();
            this.jobPollInterval = setInterval(() => {
                Craft.sendActionRequest('POST', 'queue/get-job-info?dontExtendSession=1')
                .then(({ data }) => {
                    const job = data.jobs.find(j => j.id === jobId);

                    if (!job) {
                        clearInterval(this.jobPollInterval);
                        onComplete?.();
                    } else if (job.status === Craft.CP.JOB_STATUS_FAILED) {
                        clearInterval(this.jobPollInterval);
                        onError?.(job.error || 'Unknown error');
                    }
                })
                .catch((err) => {
                    console.warn('Job polling failed', err);
                    clearInterval(this.jobPollInterval);
                });
            }, 2000); // check every 2 seconds
        }
    };

    })(jQuery);