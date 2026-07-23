/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'uiComponent',
    'underscore',
    'Magento_Customer/js/customer-data'
], function (Component, _, customerData) {
    'use strict';


    var mixin = {
        defaults: {
            template: 'Secomm_AddressDropdown/shipping-information/address-renderer/default'
        },
        cityData: customerData.get('city-data'), // Assuming you have city data in customerData

        /**
         * @param {String} cityId
         * @return {String}
         */
        /**
         * @param {String} cityId
         * @return {String}
         */
        getCityName: function (address,cityId) {
            try {
                return this.cityData()[address.regionId].city[cityId]['name'] !== undefined ? this.cityData()[address.regionId].city[cityId]['name'] : cityId;
            }catch (e) {
                return cityId;
            }
        },

        /**
         * @param {String} subCityId
         * @return {String}
         */
        getSubCityName: function (parent, subCityId) {
            try {
                let address = parent.address();
                let cityId = address.city;
                let regionId = address.regionId;
                return this.cityData()[regionId].city[cityId].sub_city[subCityId].name ?? subCityId;
            } catch (e) {
                return subCityId;
            }
        },
    }

    return function (target) { // target == Result that Magento_Ui/.../columns returns.
        return target.extend(mixin); // new result that all other modules receive
    };
});
