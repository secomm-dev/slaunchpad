/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Paypal/js/action/set-payment-method',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Secomm_ZaloPay/js/action/redirect-on-success'
], function ($, Component, setPaymentMethodAction, additionalValidators, redirectOnSuccessAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Secomm_ZaloPay/payment/zalopay'
        },
        placeOrderHandler: null,
        validateHandler: null,

        /**
         * @param {Function} handler
         */
        setPlaceOrderHandler: function (handler) {
            this.placeOrderHandler = handler;
        },

        /**
         * @param {Function} handler
         */
        setValidateHandler: function (handler) {
            this.validateHandler = handler;
        },

        /**
         * @returns {Object}
         */
        context: function () {
            return this;
        },

        /**
         * @returns {Boolean}
         */
        isShowLegend: function () {
            return true;
        },

        /**
         * @returns {String}
         */
        getCode: function () {
            return 'zalopay';
        },

        /**
         * @returns {Boolean}
         */
        isActive: function () {
            return true;
        },

        /**
         * Logo Src
         * @returns {*}
         */
        getPaymentAcceptanceMarkSrc: function () {
            return window.checkoutConfig.payment.zalopay.logoSrc;
        },

        /**
         * Place order: ZaloPay is payment-first — there is NO Magento order
         * placement before the provider redirect. Any caller of the renderer's
         * placeOrder (e.g. Mageplaza OSC's Place Order button, which clicks the
         * active payment renderer's button) goes through the same payment-first
         * path below; the Magento order is created only after the payment is
         * verified server-side (IPN/Return -> OrderFinalizer).
         */
        placeOrder: function (data, event) {
            if (event) {
                event.preventDefault();
            }

            return this.continueToZaloPay();
        },

        /** Save the payment method, then redirect to the ZaloPay gateway. */
        continueToZaloPay: function () {
            var self = this;

            if (this.validate() && additionalValidators.validate()) {
                this.isPlaceOrderActionAllowed(false);
                //update payment method information if additional data was changed
                this.selectPaymentMethod();
                setPaymentMethodAction(this.messageContainer).done(
                    function () {
                        // Payment-first (PayPal Express pattern): the ZaloPay
                        // transaction is created server-side from the ACTIVE
                        // QUOTE — no placeOrder() here.
                        redirectOnSuccessAction.execute();
                    }
                ).always(
                    function () {
                        self.isPlaceOrderActionAllowed(true);
                    }
                );

                return true;
            }

            return false;
        }
    });
});
