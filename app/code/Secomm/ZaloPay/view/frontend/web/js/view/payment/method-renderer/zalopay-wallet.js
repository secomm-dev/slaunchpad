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
        redirectAfterPlaceOrder: true,
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
         * Place order.
         */
        placeOrder: function (data, event) {
            var self = this;

            if (event) {
                event.preventDefault();
            }

            if (this.validate() &&
                additionalValidators.validate() &&
                this.isPlaceOrderActionAllowed() === true
            ) {
                this.isPlaceOrderActionAllowed(false);
                this.getPlaceOrderDeferredObject()
                    .done(
                        function () {
                            self.afterPlaceOrder();
                            if (self.redirectAfterPlaceOrder) {
                                redirectOnSuccessAction.execute();
                            }
                        }
                    ).always(
                    function () {
                        self.isPlaceOrderActionAllowed(true);
                    }
                );

                return true;
            }

            return false;
        },

        /** Redirect to ZaloPay */
        continueToZaloPay: function () {
            var self = this;

            if (this.validate() && additionalValidators.validate()) {
                //update payment method information if additional data was changed
                this.selectPaymentMethod();
                setPaymentMethodAction(this.messageContainer).done(
                    function () {
                        if (self.isPaymentFirst()) {
                            // Payment-first (PayPal Express pattern): the ZaloPay
                            // transaction is created from the ACTIVE QUOTE server-side.
                            // No placeOrder() here — the order is placed only after
                            // the payment is verified on return.
                            redirectOnSuccessAction.execute();

                            return;
                        }
                        self.placeOrder();
                    }
                );

                return false;
            }
        },

        /**
         * Whether the payment-first flow is enabled (payment/zalopay/payment_first).
         *
         * @returns {Boolean}
         */
        isPaymentFirst: function () {
            return window.checkoutConfig.payment.zalopay.paymentFirst === true;
        }
    });
});
