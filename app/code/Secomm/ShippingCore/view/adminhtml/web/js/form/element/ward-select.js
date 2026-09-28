/**
 * TASK-WY6WP5 — Included Wards selector for the Shipping Zone form: a core ui-select
 * (searchable chips multiselect — the template and filtering stay 100% core) plus ONLY
 * the cascading ward behavior: when the linked `provincesValue` import changes, options
 * reload through the ACL-guarded AJAX endpoint (secomm_shippingcore/zone/wardOptions,
 * optionsUrl injected by ZoneFormDataProvider::getMeta()) and selections that no longer
 * belong to the reloaded option set are REMOVED deterministically (directive §17 —
 * stale wards cleared; save-time validation stays the correctness boundary).
 *
 * ui-select caches its option list in `cacheOptions.{plain,tree}` and its own
 * `checkOptionsList` listener only ever GROWS the cache, so a reload must replace both
 * the `options` observable (what the dropdown renders) and the cache (what the search
 * filter and quantities use).
 *
 * Admin (Luma) component — RequireJS is the admin runtime; Hyva rules do not apply here.
 */
define([
    'Magento_Ui/js/form/element/ui-select',
    'jquery'
], function (UiSelect, $) {
    'use strict';

    return UiSelect.extend({
        defaults: {
            optionsUrl: '',
            provincesValue: [],
            listens: {
                provincesValue: 'onProvincesChange'
            }
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();

            if (this.optionsUrl) {
                this.onProvincesChange(this.provincesValue);
            }

            return this;
        },

        /**
         * Reload the ward options for the given canonical province codes and
         * deterministically drop selections that are no longer offered (stale ward after
         * a province change). A ward code that survives stays untouched. Responses are
         * race-guarded: only the newest province selection may apply.
         *
         * @param {Array|String} provinces
         */
        onProvincesChange: function (provinces) {
            if (!this.optionsUrl) {
                return;
            }
            var codes = this.normalizeCodes(provinces);

            if (codes.length === 0) {
                this.requestTag = '';
                this.replaceOptions([]);

                return;
            }
            var separator = this.optionsUrl.indexOf('?') === -1 ? '?' : '&',
                tag = codes.join(','),
                url = this.optionsUrl + separator + 'provinces=' + encodeURIComponent(tag);

            this.requestTag = tag;
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                showLoader: false,
                context: this,
                success: function (response) {
                    if (this.requestTag !== tag) {
                        return; // a newer province selection superseded this response
                    }
                    this.replaceOptions((response && response.options) || []);
                }
            });
        },

        /**
         * Swap the whole option set (dropdown list + ui-select search cache) and prune
         * the selected values against it.
         *
         * @param {Array} options flat [{value, label}]
         */
        replaceOptions: function (options) {
            this.cacheOptions.plain = options;
            this.cacheOptions.tree = options;
            this.cacheOptions.lastOptions = [];
            this.options(options);
            this.pruneToOptions();

            if (typeof this.setCaption === 'function') {
                this.setCaption();
            }
        },

        /**
         * Remove selected values missing from the current option set.
         */
        pruneToOptions: function () {
            var known = {},
                kept;

            (this.options() || []).forEach(function (option) {
                known[option.value] = true;
            });
            kept = (this.value() || []).filter(function (code) {
                return known[code] === true;
            });

            if (kept.length !== (this.value() || []).length) {
                this.value(kept);
            }
        },

        /**
         * @param {Array|String|null} value
         * @returns {Array}
         */
        normalizeCodes: function (value) {
            if (!value) {
                return [];
            }
            if (typeof value === 'string') {
                return value.split(',').map(function (part) {
                    return part.trim();
                }).filter(Boolean);
            }

            return value.map(String);
        }
    });
});
