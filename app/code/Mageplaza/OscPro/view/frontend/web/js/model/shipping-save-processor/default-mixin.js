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
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/resource-url-manager',
    'mage/storage',
    'Magento_Checkout/js/model/payment-service',
    'Magento_Checkout/js/model/payment/method-converter',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/select-billing-address',
    'Magento_Checkout/js/model/shipping-save-processor/payload-extender',
    'Mageplaza_OscPro/js/action/reload-payment-method',
    'Magento_Checkout/js/model/totals'
], function ($, wrapper,
             quote,
             resourceUrlManager,
             storage,
             paymentService,
             methodConverter,
             errorProcessor,
             fullScreenLoader,
             selectBillingAddressAction,
             payloadExtender,
             reloadPaymentMethod,
             totals) {
    'use strict';
    var loadingSpeedConfig = window.loadingSpeedConfig;
    return function (ShippingSave) {
        ShippingSave.saveShippingInformation = wrapper.wrapSuper(ShippingSave.saveShippingInformation, function () {
            if (loadingSpeedConfig.shipping_method_change.includes('1')){
                 reloadPaymentMethod();
            }
            if (!loadingSpeedConfig.shipping_method_change.includes('2')){
                return;
            }
            var payload;

            if (!quote.billingAddress() && quote.shippingAddress().canUseForBilling()) {
                selectBillingAddressAction(quote.shippingAddress());
            }

            payload = {
                addressInformation: {
                    'shipping_address': quote.shippingAddress(),
                    'billing_address': quote.billingAddress(),
                    'shipping_method_code': quote.shippingMethod()['method_code'],
                    'shipping_carrier_code': quote.shippingMethod()['carrier_code']
                }
            };

            payloadExtender(payload);

            totals.isLoading(true);

            return storage.post(
                resourceUrlManager.getUrlForSetShippingInformation(quote),
                JSON.stringify(payload)
            ).done(
                function (response) {
                    quote.setTotals(response.totals);
                    paymentService.setPaymentMethods(methodConverter(response['payment_methods']));
                    totals.isLoading(false);
                }
            ).fail(
                function (response) {
                    errorProcessor.process(response);
                    totals.isLoading(false);
                }
            );
        });

        return ShippingSave;
    };
});