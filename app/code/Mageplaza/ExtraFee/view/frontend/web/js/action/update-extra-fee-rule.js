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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define(
    [
        'underscore',
        'jquery',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/totals',
        'Mageplaza_ExtraFee/js/model/resource-url-manager',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/action/get-payment-information',
        'Magento_Checkout/js/model/quote',
        'Mageplaza_ExtraFee/js/model/extra-fee',
        'Mageplaza_ExtraFee/js/action/collect-total',
        'Magento_Customer/js/model/customer'
    ],
    function (_,
              $,
              storage,
              errorProcessor,
              totals,
              resourceUrlManager,
              customerData,
              getPaymentInformationAction,
              quote,
              extraFee,
              collectTotal
    ) {
        'use strict';

        return function (area) {
            if (extraFee.isDuplicate === true) {
                extraFee.isDuplicate = false;
                return;
            }
            totals.isLoading(true);
            $('#extra-fee-loader').show();
            $('.action.primary.checkout').addClass('mpef-disabled');
            var payload,
                addressInformation = {};
            if (!quote.isVirtual()) {
                var shippingAddress = _.extend({}, quote.shippingAddress());
                var billingAddress  = _.extend({}, quote.billingAddress());
                if (_.isEmpty(shippingAddress.street)) {
                    shippingAddress.street = [""];
                }
                if (_.isEmpty(billingAddress.street)) {
                    billingAddress.street = [""];
                }

                addressInformation = {
                    shipping_address: shippingAddress,
                    billing_address: billingAddress,
                    shipping_method_code: quote.shippingMethod() !== null ? quote.shippingMethod().method_code : '',
                    shipping_carrier_code: quote.shippingMethod() !== null ? quote.shippingMethod().carrier_code : '',
                    extension_attributes: {
                        mp_ef_payment_method: quote.paymentMethod() !== null ? quote.paymentMethod().method : ''
                    }
                };
            }
            payload = {
                area: area,
                addressInformation: addressInformation
            };
            return storage.post(
                resourceUrlManager.getUrlForRuleInformation(quote),
                JSON.stringify(payload)
            ).done(function (response) {
                if (!response[0].length) {
                    $('#block-extrafee').hide();
                } else {
                    $('#block-extrafee').show();
                }
                response.sort(function (a, b) {
                    return a.sort_order - b.sort_order;
                });
                switch (area){
                    case '1':
                        extraFee.billingSelectedOptions(response[1][0]);
                        extraFee.billingRuleConfig(response[0][0]);
                        break;
                    case '2':
                        extraFee.shippingSelectedOptions(response[1][0]);
                        extraFee.shippingRuleConfig(response[0][0]);
                        break;
                    case '3':
                        extraFee.selectedOptions(response[1][0]);
                        extraFee.ruleConfig(response[0][0]);
                        break;
                    case '1,3':
                        extraFee.billingSelectedOptions(response[1][0]);
                        extraFee.billingRuleConfig(response[0][0]);
                        extraFee.selectedOptions(response[1][1]);
                        extraFee.ruleConfig(response[0][1]);
                        break;
                    case '1,2,3':
                        extraFee.billingSelectedOptions(response[1][0]);
                        extraFee.billingRuleConfig(response[0][0]);
                        extraFee.shippingSelectedOptions(response[1][1]);
                        extraFee.shippingRuleConfig(response[0][1]);
                        extraFee.selectedOptions(response[1][2]);
                        extraFee.ruleConfig(response[0][2]);
                        break;
                    case '2,3':
                        extraFee.shippingSelectedOptions(response[1][0]);
                        extraFee.shippingRuleConfig(response[0][0]);
                        extraFee.selectedOptions(response[1][1]);
                        extraFee.ruleConfig(response[0][1]);
                        break;
                }
                collectTotal(area);

            }).fail(function (response) {
                errorProcessor.process(response);
            }).always(function () {
                $('#extra-fee-loader').hide();
                $('.action.primary.checkout').removeClass('mpef-disabled');
                totals.isLoading(false);
            });
        };
    }
);
