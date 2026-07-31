/**
 * MoMo payment method renderer (redirect-based wallet).
 *
 * On place order the customer is redirected to the MoMo wallet; on return the
 * order is confirmed by the server (Return action) and the IPN (Notify action).
 *
 * @author Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 */
define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'mage/url'
], function (Component, quote, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Secomm_MoMo/payment/momo'
        },
        redirectAfterPlaceOrder: true,

        /**
         * Payment method code.
         *
         * @returns {String}
         */
        getCode: function () {
            return 'momo_payment';
        },

        /**
         * Payment method is active (always selectable when configured).
         *
         * @returns {Boolean}
         */
        isActive: function () {
            return true;
        },

        /**
         * Show the payment legend / extra info block.
         *
         * @returns {Boolean}
         */
        isShowLegend: function () {
            return true;
        },

        /**
         * Redirect URL exposed by ConfigProvider (momo/payment/redirect).
         *
         * @returns {String}
         */
        getRedirectUrl: function () {
            return window.checkoutConfig.payment.momo_payment.redirectUrl;
        },

        /**
         * Place order: redirect the browser to the MoMo start endpoint, which
         * creates the MoMo order server-side and redirects to the wallet.
         */
        afterPlaceOrder: function () {
            window.location.replace(urlBuilder.build('momo/payment/redirect'));
        },

        /**
         * MoMo title from config.
         *
         * @returns {String}
         */
        getTitle: function () {
            return window.checkoutConfig.payment.momo_payment.title;
        }
    });
});
