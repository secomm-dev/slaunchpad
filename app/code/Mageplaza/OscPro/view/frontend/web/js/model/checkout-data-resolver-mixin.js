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
 * @package     Mageplaza_OscPro
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-method',
    'Mageplaza_OscPro/js/action/reload-order-summary'
], function ($, wrapper, checkoutData, quote, selectShippingMethodAction, reloadOrderSummary) {
    'use strict';

    return function (CheckoutDataResolver) {
        CheckoutDataResolver.resolveShippingRates = wrapper.wrapSuper(CheckoutDataResolver.resolveShippingRates, function (ratesData) {
            var selectedShippingRate = checkoutData.getSelectedShippingRate(),
                availableRate        = false;
            if (quote.shippingMethod()) {
                availableRate = _.find(ratesData, function (rate) {
                    return rate['carrier_code'] === quote.shippingMethod()['carrier_code'] && //eslint-disable-line
                        rate['method_code'] === quote.shippingMethod()['method_code']; //eslint-disable-line eqeqeq
                });
            }
            if (ratesData.length === 1 && (!quote.shippingMethod() || !availableRate)) {
                //set shipping rate if we have only one available shipping rate
                selectShippingMethodAction(ratesData[0]);

                return;
            }

            if (!availableRate && selectedShippingRate) {
                availableRate = _.find(ratesData, function (rate) {
                    return rate['carrier_code'] + '_' + rate['method_code'] === selectedShippingRate;
                });
            }

            if (!availableRate && window.checkoutConfig.selectedShippingMethod) {
                availableRate = _.find(ratesData, function (rate) {
                    var selectedShippingMethod = window.checkoutConfig.selectedShippingMethod;

                    return rate['carrier_code'] === selectedShippingMethod['carrier_code'] && //eslint-disable-line
                        rate['method_code'] === selectedShippingMethod['method_code']; //eslint-disable-line eqeqeq
                });
            }
            //Unset selected shipping method if not available
            if (!availableRate) {
                selectShippingMethodAction(null);
            } else {
                if (!$('#opc-shipping_method').length) {
                    selectShippingMethodAction(ratesData[0]);
                } else if (window.loadingSpeedConfig.shipping_address_change.includes('2')) {
                    if (window.loadingSpeedConfig.checkLoadingOrderSummary === '2') {
                        reloadOrderSummary()
                    }
                }
            }
        });

        return CheckoutDataResolver;
    };
});
