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

define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Magento_SalesRule/js/model/payment/discount-messages',
    'Magento_Checkout/js/action/set-payment-information-extended',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader',
    'Mageplaza_ExtraFee/js/model/extra-fee'
], function ($, wrapper, quote, storage, urlBuilder, messageContainer, setPaymentInformationExtended, errorProcessor, fullScreenLoader, extraFee) {
    'use strict';

    return function (selectPaymentMethodAction) {

        return wrapper.wrap(selectPaymentMethodAction, function (originalSelectPaymentMethodAction, paymentMethod) {

            originalSelectPaymentMethodAction(paymentMethod);

            if (paymentMethod === null) {
                return;
            }

            if ($('#mp-extra-fee-multishipping-billing').length) {
                $.when(
                    setPaymentInformationExtended(
                        messageContainer,
                        {
                            method: paymentMethod.method
                        },
                        true
                    )
                ).done(function () {
                    var payload = { cartId: quote.getQuoteId(), area: 1, shippingMethods: JSON.stringify({}) },
                        serviceUrl = urlBuilder.createUrl('/carts/mine/mpextrafeemultishipping', {});

                    return storage.post(
                        serviceUrl, JSON.stringify(payload), true, 'application/json', {}
                    ).done(function (response) {
                        var selectOptions = [],
                            selectOptionsVirtual = [],
                            ruleMultiShipping,
                            ruleVirtualMultiShipping;

                        ruleMultiShipping = Object.keys(response[0]).map(function (key) {
                            return response[0][key];
                        });

                        ruleVirtualMultiShipping = Object.keys(response[2]).map(function (key) {
                            return response[2][key];
                        });

                        $.each(response[1], function (i, value) {
                            selectOptions[i] = value;
                        });

                        $.each(response[3], function (i, value) {
                            selectOptionsVirtual[i] = value;
                        });

                        extraFee.selectedOptionsMultiShipping(selectOptions);
                        extraFee.selectedOptionsVirtualMultiShipping(selectOptionsVirtual);
                        extraFee.ruleMultiShipping(ruleMultiShipping);
                        extraFee.ruleVirtualMultiShipping(ruleVirtualMultiShipping);
                    }).fail(function (response) {
                        errorProcessor.process(response);
                    }).always(function () {
                        fullScreenLoader.stopLoader();
                    });
                }
                );
            }
        });
    };
});
