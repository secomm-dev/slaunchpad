/**
 * VietQR offline payment method renderer.
 */
define([
    'Magento_Checkout/js/view/payment/default'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Secomm_VietQr/payment/vietqr'
        },

        /**
         * Returns the payment instructions configured in admin.
         *
         * @return {String}
         */
        getInstructions: function () {
            return window.checkoutConfig.payment.instructions[this.item.method] || '';
        },

        /**
         * Logo Src
         * @returns {String}
         */
        getPaymentAcceptanceMarkSrc: function () {
            return window.checkoutConfig.payment.secomm_vietqr.logoSrc || '';
        }
    });
});
