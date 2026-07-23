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
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/checkout-data',
], function ($, wrapper, quote, addressList, checkoutData) {
    'use strict';

    return function (setBillingInformationAction) {
        if (!window.checkoutConfig) {
            return setBillingInformationAction;
        }

        function addSubCity (array, value) {
            return array.extension_attributes = $.extend(
                array.extension_attributes,
                value
            );
        }

        return wrapper.wrap(setBillingInformationAction, function (originalAction) {
            var billingAddress = quote.billingAddress();
            if (!billingAddress) {
                return originalAction();
            }

            if (!billingAddress.hasOwnProperty('extension_attributes')) {
                billingAddress.extension_attributes = {};
            }
            var subCitySelect = $('#custom-sub-city-select-billing'); // Assuming this is your sub-city dropdown element
            const subCity = subCitySelect?.val() || billingAddress?.customAttributes?.find(item => item.attribute_code === "sub_city")?.value;
            var extension = {
                sub_city: subCity,
            };

            addSubCity(billingAddress, extension)

            var selectedBillingAddress = checkoutData.getSelectedBillingAddress();
            var newCustomerBillingAddressData = checkoutData.getNewCustomerBillingAddress();
            if (selectedBillingAddress === 'new-customer-billing-address' && newCustomerBillingAddressData) {
                addSubCity(newCustomerBillingAddressData, extension)
            } else {
                addressList.some(function (address) {
                    if (selectedBillingAddress === address.getKey()) {
                        addSubCity(address, extension)
                    }
                });
            }


            return originalAction();
        });
    };
});
