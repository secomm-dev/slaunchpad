/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Magento_Ui/js/form/element/abstract',
    'mage/url'
], function ($, Abstract, urlBuilder) {
    'use strict';

    return Abstract.extend({
        initialize: function () {
            this._super();
            this.initCityChangeEvent();
            return this;
        },

        initCityChangeEvent: function () {
            var self = this;

            this.on('value', function (value) {
                self.cityChanged(value);
            });
        },

        cityChanged: function (city) {
            console.log(city);
            $.ajax({
                url: urlBuilder.build('module/controller/action'),
                type: 'POST',
                data: { city: city },
                success: function (response) {
                    // Handle the response
                    console.log(response);
                },
                error: function () {
                    console.error('An error occurred.');
                }
            });
        }
    });
});
