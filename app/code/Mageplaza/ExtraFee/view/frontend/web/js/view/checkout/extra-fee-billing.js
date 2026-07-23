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
    'Mageplaza_ExtraFee/js/view/abstract-extra-fee',
    'Mageplaza_ExtraFee/js/action/update-extra-fee-rule',
    'Mageplaza_ExtraFee/js/model/extra-fee',
    'Magento_Checkout/js/model/quote',
    'mage/translate',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/step-navigator'
], function ($, ko, Component, updateRule, extraFee, quote, $t, additionalValidators, stepNav) {
    'use strict';

    let updateTimeout       = null;
    let isApiCallInProgress = false;
    let lastQuoteState      = null;

    return Component.extend({
        defaults: {
            template: 'Mageplaza_ExtraFee/checkout/extra-fee-billing'
        },
        billingRuleConfig: extraFee.billingRuleConfig,
        errorValidationMessage: ko.observable(false),
        area: '1',

        initialize: function () {
            this._super();
            additionalValidators.registerValidator(this);

            var self = this;

            if (window.checkoutConfig.oscConfig) {
                updateRule('1,2,3');
            } else {
                if (this.isPayment()) {
                    updateRule('2,3');
                } else {
                    updateRule('1,2,3');
                }
            }

            // DOM listeners removed - using quote.paymentMethod.subscribe() observer instead for better reliability
        },

        /**
         * DOM event listeners removed to prevent duplicate API calls
         * Now using quote.paymentMethod.subscribe() observer for payment method changes
         */

        /**
         * Immediate rule update for payment method changes
         */
        immediateUpdateRule: function () {
            var self = this;

            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }

            if (isApiCallInProgress) {
                setTimeout(function () {
                    self.immediateUpdateRule();
                }, 100);
                return;
            }

            isApiCallInProgress = true;

            // Update both payment method (1) and cart summary (3) areas since both can depend on payment method
            var promise = updateRule('1,3');
            if (promise && typeof promise.then === 'function') {
                promise.then(function (response) {
                    if (self.billingRuleConfig().length > 0 && !$('#mp-extra-fee-billing').is(':visible')) {
                        $('#mp-extra-fee-billing').show();
                    }
                    lastQuoteState = self.getQuoteState();
                }).fail(function (error) {
                    if ($('#mp-extra-fee-billing').is(':visible')) {
                        $('#mp-extra-fee-billing').hide();
                    }
                }).always(function () {
                    isApiCallInProgress = false;
                });
            } else {
                isApiCallInProgress = false;
            }
        },

        isPayment: function () {
            var steps       = stepNav.steps(),
                paymentStep = _.where(steps, {'code': 'payment'});

            return paymentStep.length && paymentStep[0].isVisible();
        },
        updateRule: function () {
            if (extraFee.isDuplicate !== true) {
                if (stepNav.getActiveItemIndex() === 1 || window.checkoutConfig.oscConfig) {
                    updateRule('1,2,3');
                } else {
                    updateRule('2,3');
                }
            }
            extraFee.isDuplicate = false;
        },

        /**
         * Get current quote state for comparison
         */
        getQuoteState: function () {
            return {
                totalsTimestamp: quote.totals() ? quote.totals().updated_at || Date.now() : null,
                shippingAddressId: quote.shippingAddress() ? quote.shippingAddress().getKey() : null,
                paymentMethodCode: quote.paymentMethod() ? quote.paymentMethod().method : null,
                grandTotal: quote.totals() ? quote.totals().grand_total : null
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

            var consolidatedObserver = function () {
                var currentState = self.getQuoteState();

                if (!self.hasQuoteStateChanged(currentState) || isApiCallInProgress) {
                    return;
                }

                lastQuoteState = currentState;
                self.scheduleUpdateRule('1,2,3');
            };

            var paymentMethodObserver = function () {
                var currentState = self.getQuoteState();

                if (!self.hasQuoteStateChanged(currentState) || isApiCallInProgress) {
                    return;
                }

                lastQuoteState = currentState;
                // Use immediate update for payment method changes to ensure cart summary updates
                self.immediateUpdateRule();
            };

            quote.totals.subscribe(consolidatedObserver);
            quote.shippingAddress.subscribe(consolidatedObserver);
            quote.paymentMethod.subscribe(paymentMethodObserver);

            return this;
        },
        validate: function () {
            var isValid = true;
            $('#mp-extra-fee-billing .mp-extra-fee-required').each(function () {
                if (!$(this).find('input:checked').length) {
                    isValid = false;
                }
            });
            if (!isValid) {
                this.errorValidationMessage($t('Please choose at least one option for each require extra fee'));
            }

            return isValid;
        },
        afterRenderOptions: function (option, optionVal) {
            if (!extraFee.billingSelectedOptions()['rule']) {
                return;
            }
            var data = extraFee.billingSelectedOptions()['rule'];
            var type = optionVal.display_type || optionVal.type;
            if (type === '3') {
                if (data[optionVal.rule_id] == optionVal.value) {
                    $(option).attr('selected', true);
                }
            } else {
                var input = $(option).find('input');
                input.each(function () {
                    if (data[$(this).attr('rule_id')] !== undefined) {
                        if (data[$(this).attr('rule_id')] === $(this).val()) {
                            $(this).attr('checked', true);
                        }
                        if (data[$(this).attr('rule_id')][$(this).val()] !== undefined) {
                            $(this).attr('checked', data[$(this).attr('rule_id')][$(this).val()]);
                        }
                    }
                });
            }
        },
        scheduleUpdateRule: function (area) {
            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }

            updateTimeout = setTimeout(() => {
                if (isApiCallInProgress) {
                    return;
                }

                isApiCallInProgress = true;

                var promise = updateRule(area);
                if (promise && typeof promise.always === 'function') {
                    promise.always(function () {
                        isApiCallInProgress = false;
                    });
                } else {
                    isApiCallInProgress = false;
                }
            }, 250);
        }
    });
});
