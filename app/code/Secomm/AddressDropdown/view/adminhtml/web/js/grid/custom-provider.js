define([
    'Magento_Ui/js/grid/provider',
    'mage/storage',
    'underscore'
], function (Provider, storage, _) {
    'use strict';

    return Provider.extend({
        /**
         * Enhanced reload method
         * @param {Object} options - The options for the reload
         * @returns {Promise} - A promise that resolves with the reload result
         */
        reload: function (options) {
            options = options || {};
            options.data = _.extend({}, options.data || {});

            if (this.params.namespace == 'region_listing') {
                const countryId = this.getIdFromUrl('country_id');
                if (countryId) {
                    this.params.country_id = countryId;
                }
            }

            if (this.params.namespace == 'city_listing') {
                const regionId = this.getIdFromUrl('region_id');
                if (regionId) {
                    this.params.region_id = regionId;
                }
            }

            if (this.params.namespace == 'sub_city_listing') {
                const cityId = this.getIdFromUrl('city_id');
                if (cityId) {
                    this.params.city_id = cityId;
                }
            }

            var request = this.storage().getData(this.params, options);

            this.trigger('reload');

            request
                .done(this.onReload)
                .fail(this.onError.bind(this));

            return request;
        },

        /**
         * Extract id from the current URL
         * @param {string} field - The options field for get value
         * @returns {string|null} - The id if found, null otherwise
         */
        getIdFromUrl: function (field) {
            const pathname = window.location.pathname;
            const parts = pathname.split('/');
            const countryIdIndex = parts.indexOf(field);
            if (countryIdIndex !== -1 && countryIdIndex < parts.length - 1) {
                return parts[countryIdIndex + 1];
            }
            return null;
        },

    });
});
