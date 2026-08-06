/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
define(
    [
        'jquery',
        'underscore',
        'ko',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/select-shipping-method',
        'Magento_Checkout/js/checkout-data',
    ], function (
        $,
        _,
        ko,
        quote,
        selectShippingMethodAction,
        checkoutData,
    ) {
        'use strict';

        var mixin = {
            /**
             * @param {Object} shippingMethod
             * @return {Boolean}
             */
            /**
             * @param {Object} shippingMethod
             * @return {Boolean}
             */
            selectShippingMethod: function (shippingMethod) {
                if(shippingMethod.error_message !== '' ){
                    return;
                }
                selectShippingMethodAction(shippingMethod);
                checkoutData.setSelectedShippingRate(shippingMethod['carrier_code'] + '_' + shippingMethod['method_code']);

                return true;
            },
        };

        return function (target) {
            return target.extend(mixin);
        }
    });
