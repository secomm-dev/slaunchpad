/**
 * MoMo payment method renderer (payment-first wallet redirect) — MOMO-01.
 *
 * placeOrder() is overridden: NO Magento order is placed before the provider
 * redirect (AC1). Any caller of the renderer's placeOrder (e.g. Mageplaza
 * OSC's Place Order button, which clicks the active payment renderer's
 * button) goes through the same payment-first path: validate → save the
 * payment method on the quote (set-payment-information) → redirect to
 * momo/payment/redirect. The Magento order is created only after the payment
 * is verified server-side (IPN/Return -> OrderFinalizer).
 *
 * @author Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 */
define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Paypal/js/action/set-payment-method',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Secomm_MoMo/js/action/redirect-on-success'
], function ($, Component, setPaymentMethodAction, additionalValidators, redirectOnSuccessAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Secomm_MoMo/payment/momo'
        },
        placeOrderHandler: null,
        validateHandler: null,

        /**
         * @param {Function} handler
         */
        setPlaceOrderHandler: function (handler) {
            this.placeOrderHandler = handler;
        },

        redirectAfterPlaceOrder: false,

        /**
         * @param {Function} provider
         */
        setValidateHandler: function (handler) {
            this.validateHandler = handler;
        },

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
         * MoMo title from config.
         *
         * @returns {String}
         */
        getTitle: function () {
            return window.checkoutConfig.payment.momo_payment.title;
        },

        /**
         * Place order: MoMo is payment-first — there is NO Magento order
         * placement before the provider redirect. Any caller of the
         * renderer's placeOrder goes through the same payment-first path
         * below; the Magento order is created only after the payment is
         * verified server-side (IPN/Return -> OrderFinalizer).
         */
        placeOrder: function (data, event) {
            if (event) {
                event.preventDefault();
            }

            return this.continueToMoMo();
        },

        /** Save the payment method, then redirect to the MoMo gateway. */
        continueToMoMo: function () {
            var self = this;

            if (this.validate() && additionalValidators.validate()) {
                this.isPlaceOrderActionAllowed(false);
                // Update payment method information if additional data was changed.
                this.selectPaymentMethod();
                setPaymentMethodAction(this.messageContainer).done(
                    function () {
                        // Payment-first (PayPal Express pattern): the MoMo
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
