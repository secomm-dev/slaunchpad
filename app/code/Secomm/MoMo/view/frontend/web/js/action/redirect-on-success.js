/**
 * redirect-on-success (MoMo payment-first start) — MOMO-01.
 *
 * Redirects the browser to the MoMo start endpoint (momo/payment/redirect),
 * which creates the attempt + provider transaction from the ACTIVE QUOTE
 * server-side. NO Magento placeOrder is ever fired (AC1).
 *
 * @author Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 */
define([
    'mage/url',
    'Magento_Checkout/js/model/full-screen-loader'
], function (url, fullScreenLoader) {
    'use strict';

    return {
        redirectUrl: window.checkoutConfig.payment.momo_payment.redirectUrl,

        /**
         * Provide redirect to page
         */
        execute: function () {
            fullScreenLoader.startLoader();
            window.location.replace(this.redirectUrl);
        }
    };
});
