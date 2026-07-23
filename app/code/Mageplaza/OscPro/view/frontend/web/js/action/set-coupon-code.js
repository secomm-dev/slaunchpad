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
    'ko',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/resource-url-manager',
    'Magento_Checkout/js/model/error-processor',
    'Magento_SalesRule/js/model/payment/discount-messages',
    'mage/storage',
    'mage/translate',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/recollect-shipping-rates'
], function (ko, $, quote, urlManager, errorProcessor, messageContainer, storage, $t, getPaymentInformationAction,
             totals, fullScreenLoader, recollectShippingRates
) {
    'use strict';

    var dataModifiers    = [],
        successCallbacks = [],
        failCallbacks    = [],
        action;

    /**
     * Apply provided coupon.
     *
     * @param {String} couponCode
     * @param {Boolean}isApplied
     * @returns {Deferred}
     */
    action = function (couponCode, isApplied) {
        var quoteId = quote.getQuoteId(),
            url     = urlManager.getApplyCouponUrl(couponCode, quoteId),
            message = $t('Your coupon was successfully applied.'),
            data    = {},
            headers = {};

        //Allowing to modify coupon-apply request
        dataModifiers.forEach(function (modifier) {
            modifier(headers, data);
        });
        // fullScreenLoader.startLoader();

        return storage.put(
            url,
            data,
            false,
            null,
            headers
        ).done(function (response) {
            var deferred;

            if (window.loadingSpeedConfig.apply_coupon.includes("2")) {
                if (response) {
                    deferred = $.Deferred();
                    isApplied(true);
                    totals.isLoading(true);
                    recollectShippingRates();
                    getPaymentInformationAction(deferred);
                    $.when(deferred).done(function () {
                        // fullScreenLoader.stopLoader();
                        totals.isLoading(false);
                    });
                    messageContainer.addSuccessMessage({
                        'message': message
                    });
                    //Allowing to tap into apply-coupon process.
                    successCallbacks.forEach(function (callback) {
                        callback(response);
                    });
                }
            } else {
                isApplied(true);
            }

        }).fail(function (response) {
            // fullScreenLoader.stopLoader();
            totals.isLoading(false);
            errorProcessor.process(response, messageContainer);
            //Allowing to tap into apply-coupon process.
            failCallbacks.forEach(function (callback) {
                callback(response);
            });
        });
    };

    /**
     * Modifying data to be sent.
     *
     * @param {Function} modifier
     */
    action.registerDataModifier = function (modifier) {
        dataModifiers.push(modifier);
    };

    /**
     * When successfully added a coupon.
     *
     * @param {Function} callback
     */
    action.registerSuccessCallback = function (callback) {
        successCallbacks.push(callback);
    };

    /**
     * When failed to add a coupon.
     *
     * @param {Function} callback
     */
    action.registerFailCallback = function (callback) {
        failCallbacks.push(callback);
    };

    return action;
});