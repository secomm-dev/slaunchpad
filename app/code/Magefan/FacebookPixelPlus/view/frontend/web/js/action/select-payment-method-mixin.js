/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

define([
    'mage/utils/wrapper'
], function (wrapper) {
    'use strict';

    /**
     * ------------------------------------------------------------------------
     * Constants
     * ------------------------------------------------------------------------
     */
    const ADD_PAYMENT_INFO_EVENT = 'AddPaymentInfo';

    /**
     * Track FB pixel data
     */
    return function (selectPaymentMethodAction) {
        let lastTitle;

        return wrapper.wrap(selectPaymentMethodAction, function (originalSelectPaymentMethodAction, paymentMethod) {
            originalSelectPaymentMethodAction(paymentMethod);

            if (!window.fbq || !window.mfFbPixelCheckout || paymentMethod === null || !paymentMethod.method) {
                return;
            }

            let data = mfFbPixelCheckout;

            if (data && lastTitle !== paymentMethod.method) {
                lastTitle = paymentMethod.method;
                var eventName = ADD_PAYMENT_INFO_EVENT;
                fbq('track', eventName, data, {'eventID': eventName + '.' + Math.floor(Math.random() * 1000000) + '.' + Date.now(), "event_source_url": window.location.href, "referrer_url": document.referrer });

            }
        });
    }
});

