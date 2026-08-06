define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/shipping-rates-validation-rules'
], function (wrapper, shippingRatesValidationRules) {
    'use strict';

    var postcodeElements = [],
        postcodeElementName = 'postcode';
    return function (
        shippingRatesValidator
    ) {
        shippingRatesValidator.doElementBinding = wrapper.wrapSuper(shippingRatesValidator.doElementBinding, function (element, force, delay) {
            var currentUrl = window.location.href;
            // Check if the URL contains "/cart/"
            if (currentUrl.includes("/cart/")) {
                shippingRatesValidator.validateFields();
            }
            var observableFields = shippingRatesValidationRules.getObservableFields();
            if (element && (observableFields.indexOf(element.index) !== -1 || force)) {
                if (element.index !== postcodeElementName) {
                    this.bindHandler(element, delay);
                }
            }

            if (element.index === postcodeElementName) {
                this.bindHandler(element, delay);
                postcodeElements.push(element);
            }
        });

        return shippingRatesValidator;
    };
});
