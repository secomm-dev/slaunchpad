define([
    'uiComponent',
    'Magento_Checkout/js/model/shipping-rates-validator',
    'Magento_Checkout/js/model/shipping-rates-validation-rules',
    '../../model/shipping-rates-validator/ahamove',
    '../../model/shipping-rates-validation-rules/ahamove'
], function (Component,
             defaultShippingRatesValidator,
             defaultShippingRatesValidationRules,
             customShippingRatesValidator,
             customShippingRatesValidationRules) {
    'use strict';
    defaultShippingRatesValidator.registerValidator('ahamove_express', customShippingRatesValidator);
    defaultShippingRatesValidationRules.registerRules('ahamove_express', customShippingRatesValidationRules);

    defaultShippingRatesValidator.registerValidator('ahamove_standard', customShippingRatesValidator);
    defaultShippingRatesValidationRules.registerRules('ahamove_standard', customShippingRatesValidationRules);
    return Component;
});
