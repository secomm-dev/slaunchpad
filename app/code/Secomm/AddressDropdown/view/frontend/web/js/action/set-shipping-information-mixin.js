/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_DeliveryTime
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-save-processor'
], function ($, wrapper, quote, shippingSaveProcessor) {
    'use strict';

    return function (setShippingInformationAction) {
        if (!window.checkoutConfig) {
            return setShippingInformationAction;
        }

        return wrapper.wrap(setShippingInformationAction, function (originalAction) {
            var shippingAddress = quote.shippingAddress();

            if (!shippingAddress.hasOwnProperty('extension_attributes')) {
                shippingAddress.extension_attributes = {};
            }
            var subCitySelect = $('#custom-sub-city-select'); // Assuming this is your sub-city dropdown element

            const subCity = subCitySelect?.val() || shippingAddress?.customAttributes?.find(item => item.attribute_code === "sub_city")?.value;
            var extension = {
                sub_city: subCity,
            };

            shippingAddress.extension_attributes = $.extend(
                shippingAddress.extension_attributes,
                extension
            );

            return originalAction();
        });
    };
});
