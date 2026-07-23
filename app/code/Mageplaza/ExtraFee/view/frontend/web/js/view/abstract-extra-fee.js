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
    'underscore',
    'uiComponent',
    'Mageplaza_ExtraFee/js/action/update-extra-fee-rule',
    'Mageplaza_ExtraFee/js/model/extra-fee',
    'Mageplaza_ExtraFee/js/action/collect-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils'
], function ($, _, Component, updateRule, extraFee, collectTotal, quote, priceUtils) {
    'use strict';

    var store_id = window.checkoutConfig.quoteData.store_id;

    return Component.extend({
        defaults: {
            template: 'Mageplaza_ExtraFee/cart/extra-fee'
        },
        area: '3',

        afterRenderOptions: function (option, optionVal) {
            if (!extraFee.selectedOptions()['rule']) {
                return;
            }

            var data = extraFee.selectedOptions()['rule'],
                type = optionVal.display_type || optionVal.type;

            if (type === '3') {
                if (data[optionVal.rule_id] === optionVal.value) {
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

        getTitle: function (rule) {
            var labels = JSON.parse(rule.labels);

            return labels[store_id] || rule.name;
        },

        getDescription: function (rule) {
            return rule.description;
        },

        getFormattedPrice: function (price) {
            return priceUtils.formatPrice(price, quote.getPriceFormat());
        },

        getOptions: function (rule) {
            var self    = this,
                options = JSON.parse(rule.options),
                result  = [];

            _.each(options.option.value, function (object, key) {
                var title = (object[store_id] || object[0]) + ' ' + self.getFormattedPrice(object['calculated_amount']);

                result.push({
                    rule_id: rule.rule_id,
                    value: key,
                    title: title,
                    type: rule.display_type
                });
            });

            return result;
        },

        selectOption: function (option, event) {
            if (event.type !== 'click') {
                return;
            }

            collectTotal(this.area, event);
        },

        changeOption: function (option, event) {
            if (event.type !== 'change') {
                return;
            }

            collectTotal(this.area);
        },

        isAllowNote: function (rule) {
            return rule.allow_note_message > 0;
        },

        getNoteTitle: function (rule) {
            return rule.message_title;
        },

        getNoteMessage: function (key) {
            var ele = '#' + key;
            if (extraFee.ruleConfig() && extraFee.selectedOptions()[key]) {
                $(ele).val(extraFee.selectedOptions()[key]);
            }
            if (extraFee.shippingRuleConfig() && extraFee.shippingSelectedOptions()[key]) {
                $(ele).val(extraFee.shippingSelectedOptions()[key]);
            }
            if (extraFee.billingRuleConfig() && extraFee.billingSelectedOptions()[key]) {
                $(ele).val(extraFee.billingSelectedOptions()[key]);
            }
        }
    });
});
