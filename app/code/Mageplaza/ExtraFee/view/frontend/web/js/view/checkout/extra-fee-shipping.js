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
    'Magento_Checkout/js/model/payment/additional-validators'
], function ($, ko, Component, updateRule, extraFee, quote, $t, additionalValidators) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mageplaza_ExtraFee/checkout/extra-fee-shipping'
        },
        shippingRuleConfig: extraFee.shippingRuleConfig,
        errorValidationMessage: ko.observable(false),
        area: '2',

        initialize: function () {
            additionalValidators.registerValidator(this);
            this._super();
            if (!window.checkoutConfig.oscConfig) {
                updateRule(this.area);
            }
        },
        initObservable: function () {
            this._super();

            return this;
        },
        validate: function () {
            var isValid = true;
            $('#mp-extra-fee-shipping .mp-extra-fee-required').each(function () {
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
            if (!extraFee.shippingSelectedOptions()['rule']) {
                return;
            }
            var data = extraFee.shippingSelectedOptions()['rule'];
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
        }
    });
});