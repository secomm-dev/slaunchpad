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
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

define(
    [
    'jquery',
    'Mageplaza_Osc/js/action/set-checkout-information',
    'Mageplaza_Osc/js/model/braintree-paypal',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'underscore',
    'uiRegistry',
    'braintreeCheckoutPayPalAdapter',
    'mage/translate'
    ], function ($,
        setCheckoutInformationAction,
        braintreePaypalModel,
        additionalValidators,
        quote,
        _,
        registry,
        Braintree,
        $t
    ) {
        'use strict';
        return function (BraintreePaypalComponent) {
            return BraintreePaypalComponent.extend(
                {
                    defaults: {
                        template: 'Mageplaza_Osc/payment/braintree_paypal_map',

                        clientConfig: {
                            buttonPayPalId: 'osc_braintree_paypal_placeholder',
                            buttonId: 'osc_braintree_paypal_placeholder',
                        }
                    },

                    /**
                     * Set list of observable attributes
                     *
                     * @returns {exports.initObservable}
                     */
                    initObservable: function () {
                        this._super();
                        // For each component initialization need update property
                        this.isReviewRequired = braintreePaypalModel.isReviewRequired;
                        this.customerEmail = braintreePaypalModel.customerEmail;
                        this.active = braintreePaypalModel.active;

                        return this;
                    },

                    /**
                     * Get shipping address
                     *
                     * @returns {Object}
                     */
                    getShippingAddress: function () {
                        var address = quote.shippingAddress();

                        if (!address) {
                            address = {};
                        }
                        if (!address.street) {
                            address.street = ['', ''];
                        }
                        if (address.postcode === null) {
                            return {};
                        }

                        return this._super();
                    },

                    loadPayPalButton: function (paypalCheckoutInstance, funding) {
                        let paypalPayment = Braintree.config.paypal,
                        onPaymentMethodReceived = Braintree.config.onPaymentMethodReceived,
                        style = {
                            label: Braintree.getLabel(funding),
                            color: Braintree.getColor(funding),
                            shape: Braintree.getShape(funding)
                        },
                        button,
                        events = Braintree.events,
                        payPalButtonId,
                        payPalButtonElement;

                        if (funding === 'credit') {
                            Braintree.config.buttonId = this.getCreditButtonId();
                        } else if (funding === 'paylater') {
                            Braintree.config.buttonId = this.getPayLaterButtonId();
                        } else {
                            Braintree.config.buttonId = this.getPayPalButtonId();
                        }

                        payPalButtonId = Braintree.config.buttonId;
                        payPalButtonElement = $('#' + Braintree.config.buttonId);
                        payPalButtonElement.html('');

                        // Render
                        Braintree.config.paypalInstance = paypalCheckoutInstance;

                        button = window.paypal.Buttons(
                            {
                                fundingSource: funding,
                                env: Braintree.getEnvironment(),
                                style: style,
                                commit: true,
                                locale: Braintree.config.paypal.locale,

                                onInit: function (data, actions) {
                                    let agreements = checkoutAgreements().agreements,
                                    shouldDisableActions = false;

                                    actions.disable();

                                    _.each(
                                        agreements, function (item) {
                                            if (checkoutAgreements().isAgreementRequired(item)) {
                                                let paymentMethodCode = quote.paymentMethod().method,
                                                inputId = '#agreement_' + paymentMethodCode + '_' + item.agreementId,
                                                inputEl = document.querySelector(inputId);

                                                if (!inputEl.checked) {
                                                    shouldDisableActions = true;
                                                }

                                                inputEl.addEventListener(
                                                    'change', function () {
                                                        if (additionalValidators.validate()) {
                                                            actions.enable();
                                                        } else {
                                                            actions.disable();
                                                        }
                                                    }
                                                );
                                            }
                                        }
                                    );

                                    if (!shouldDisableActions) {
                                        actions.enable();
                                    }
                                },

                                createOrder: function () {
                                    return paypalCheckoutInstance.createPayment(paypalPayment).catch(
                                        function (err) {
                                            throw err.details.originalError.details.originalError.paymentResource;
                                        }
                                    );
                                },

                                onCancel: function (data) {
                                    console.log('checkout.js payment cancelled', JSON.stringify(data, 0, 2));

                                    if (typeof events.onCancel === 'function') {
                                        events.onCancel();
                                    }
                                },

                                onError: function (err) {
                                    if (err.errorName === 'VALIDATION_ERROR' && err.errorMessage.indexOf('Value is invalid') !== -1) {
                                        Braintree.showError(
                                            $t(
                                                'Address failed validation. Please check and confirm your City, State, and Postal Code'
                                            )
                                        );
                                    } else {
                                        Braintree.showError(
                                            $t('PayPal Checkout could not be initialized. Please contact the store owner.')
                                        );
                                    }
                                    Braintree.config.paypalInstance = null;
                                    console.error('Paypal checkout.js error', err);

                                    if (typeof events.onError === 'function') {
                                        events.onError(err);
                                    }
                                },

                                onClick: function (data) {
                                    if (!quote.isVirtual()) {
                                        this.clientConfig.paypal.enableShippingAddress = true;
                                        this.clientConfig.paypal.shippingAddressEditable = false;
                                        this.clientConfig.paypal.shippingAddressOverride = this.getShippingAddress();
                                    }

                                    // To check term & conditions input checked - validate additional validators.
                                    if (!additionalValidators.validate()) {
                                        return false;
                                    }

                                    if (typeof events.onClick === 'function') {
                                        events.onClick(data);
                                    }
                                }.bind(this),

                                onApprove: function (data) {
                                    return paypalCheckoutInstance.tokenizePayment(data)
                                    .then(
                                        function (payload) {
                                            onPaymentMethodReceived(payload);
                                        }
                                    );
                                }
                            }
                        );

                        if (button.isEligible() && payPalButtonElement.length) {
                            button.render('#' + payPalButtonId).then(
                                function () {
                                    Braintree.enableButton();
                                    if (typeof Braintree.config.onPaymentMethodError === 'function') {
                                        Braintree.config.onPaymentMethodError();
                                    }
                                }
                            ).then(
                                function (data) {
                                    if (typeof events.onRender === 'function') {
                                        events.onRender(data);
                                    }
                                }
                            );
                        }
                    },

                    // Compatible with PayPal Through Braintree on M231
                    reInitPayPal: function () {
                        var placeOrder = registry.get('checkout.sidebar.place-order-information-right.place-order-button');

                        if (!placeOrder.isPaypalThroughBraintree) {
                            this._super();
                        }
                    }
                }
            );
        };
    }
);
