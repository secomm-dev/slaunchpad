define([
    'mage/utils/wrapper',
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/resource-url-manager',
    'mage/storage',
    'Magento_Checkout/js/model/payment-service',
    'Magento_Checkout/js/model/payment/method-converter',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/select-billing-address',
    'Magento_Checkout/js/model/shipping-rate-registry',
], function(
    wrapper,
    $,
    ko,
    quote,
    resourceUrlManager,
    storage,
    paymentService,
    methodConverter,
    errorProcessor,
    fullScreenLoader,
    selectBillingAddressAction,
    rateRegistry
) {
    'use strict';

    return function (shippingSaveProcessor) {
        shippingSaveProcessor.saveShippingInformation = wrapper.wrapSuper(shippingSaveProcessor.saveShippingInformation, function () {
            var payload;
            var subCity = $('[name="custom_attributes[sub_city]"]').val();

            if(!quote.billingAddress()) {
                selectBillingAddressAction(quote.shippingAddress());
            }

            var cache = rateRegistry.get(quote.shippingAddress().getKey());

            payload = {
                addressInformation: {
                    shipping_address: quote.shippingAddress(),
                    billing_address: quote.billingAddress(),
                    shipping_method_code: quote.shippingMethod() ? quote.shippingMethod()?.method_code : cache[0]?.method_code,
                    shipping_carrier_code: quote.shippingMethod() ? quote.shippingMethod()?.carrier_code : cache[0]?.carrier_code,
                    extension_attributes: {
                        sub_city: subCity
                    }
                }
            };

            fullScreenLoader.startLoader();

            return storage.post(
                resourceUrlManager.getUrlForSetShippingInformation(quote),
                JSON.stringify(payload)
            ).done(
                function(response) {
                    quote.setTotals(response.totals);
                    paymentService.setPaymentMethods(methodConverter(response.payment_methods));
                    fullScreenLoader.stopLoader();
                }
            ).fail(
                function(response) {
                    errorProcessor.process(response);
                    fullScreenLoader.stopLoader();
                }
            );
        });

        return shippingSaveProcessor;
    };
});
