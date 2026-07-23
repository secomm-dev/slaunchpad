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
    'ko',
    'underscore',
    'Mageplaza_ExtraFee/js/view/abstract-extra-fee',
    'Mageplaza_ExtraFee/js/action/update-extra-fee-rule',
    'Mageplaza_ExtraFee/js/model/extra-fee',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/payment/additional-validators',
    'mage/translate'
], function ($, ko, _, Component, updateRule, extraFee, quote, additionalValidators, $t) {
    'use strict';

    let updateTimeout       = null;
    let isApiCallInProgress = false;
    let lastQuoteState      = null;

    return Component.extend({
        defaults: {
            template: 'Mageplaza_ExtraFee/cart/extra-fee'
        },
        ruleConfig: extraFee.ruleConfig,
        area: '3',
        isCheckoutCart: $('body').hasClass('checkout-cart-index'),
        errorValidationMessage: ko.observable(false),

        initialize: function () {
            this._super();
            additionalValidators.registerValidator(this);
            if (this.isCheckoutCart) {
                updateRule(this.area);
                extraFee.isDuplicate = true;
            }
        },

        /**
         * Get current quote state for comparison
         */
        getQuoteState: function () {
            return {
                totalsTimestamp: quote.totals() ? quote.totals().updated_at || Date.now() : null,
                paymentMethodCode: quote.paymentMethod() ? quote.paymentMethod().method : null,
                grandTotal: quote.totals() ? quote.totals().grand_total : null,
                subtotal: quote.totals() ? quote.totals().subtotal : null,
                subtotalWithDiscount: quote.totals() ? quote.totals().subtotal_with_discount : null,
                itemCount: quote.getItems() ? quote.getItems().length : 0,
                totalSegmentsLength: quote.totals() && quote.totals().total_segments ? quote.totals().total_segments.length : 0
            };
        },

        /**
         * Check if quote state has actually changed
         */
        hasQuoteStateChanged: function (newState) {
            if (!lastQuoteState) {
                return true;
            }

            return JSON.stringify(lastQuoteState) !== JSON.stringify(newState);
        },

        /**
         * Init observer event
         * @return {exports}
         */
        initObservable: function () {
            this._super();
            var self = this;
            if (!this.isCheckoutCart) {
                return this;
            }

            quote.totals.subscribe(function () {
                var currentState = self.getQuoteState();

                if (!self.hasQuoteStateChanged(currentState) || isApiCallInProgress || extraFee.isDuplicate === true) {
                    extraFee.isDuplicate = false;
                    return;
                }

                lastQuoteState = currentState;
                self.scheduleUpdateRule();
                extraFee.isDuplicate = false;
            });

            return this;
        },

        /**
         * Schedule API call with debouncing and duplicate prevention
         */
        scheduleUpdateRule: function () {
            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }

            updateTimeout = setTimeout(() => {
                if (isApiCallInProgress) {
                    return;
                }

                isApiCallInProgress = true;

                updateRule(this.area).always(function () {
                    isApiCallInProgress = false;
                });
            }, 750);
        },

        validate: function () {
            var isValid = true;
            $('#mp-extra-fee .mp-extra-fee-required').each(function () {
                if (!$(this).find('input:checked').length) {
                    isValid = false;
                }
            });
            if (!isValid) {
                this.errorValidationMessage($t('Please choose at least one option for each require extra fee'));
            }
            return isValid;
        }
    });
});
