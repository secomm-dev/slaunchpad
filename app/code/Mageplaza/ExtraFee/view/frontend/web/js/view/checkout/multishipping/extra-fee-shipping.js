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
    'uiComponent',
    'Mageplaza_ExtraFee/js/model/extra-fee',
    'mage/storage'
], function ($, ko, Component, extraFee, storage) {
    'use strict';

    var store_id, code;

    return Component.extend({
        defaults: {
            template: 'Mageplaza_ExtraFee/checkout/multishipping/extra-fee-shipping'
        },
        ruleMultiShipping: extraFee.ruleMultiShipping,
        selectedOptionsMultiShipping: extraFee.selectedOptionsMultiShipping,

        initialize: function (config) {
            var self = this;

            self.store_id = config.storeId;
            self.code     = config.code;
            if (!self.ruleMultiShipping().length) {
                self.setRuleMultiShipping(config.ruleMultiShipping);
            }

            if (!self.selectedOptionsMultiShipping().length) {
                extraFee.selectedOptionsMultiShipping(config.selectedOptionsMultiShipping);
            }

            this._super();
        },

        getTitle: function (rule) {
            var self   = this,
                labels = JSON.parse(rule.labels);

            return labels[self.store_id] || rule.name;
        },

        getDescription: function (rule) {
            return rule.description;
        },

        getOptions: function (rule) {
            var options = JSON.parse(rule.options),
                result  = [];

            _.each(options.option.value, function (object, key) {
                var title = (object[store_id] || object[0]) + ' ' + object['calculated_amount_format'];

                result.push({
                    rule_id: rule.rule_id,
                    value: key,
                    title: title,
                    type: rule.display_type
                });
            });

            return result;
        },

        setRuleMultiShipping: function (ruleMultiShipping) {
            var self = this;

            self.ruleMultiShipping = ruleMultiShipping;
        },

        getRuleMultiShipping: function () {
            var self = this;

            return extraFee.ruleMultiShipping().length ? extraFee.ruleMultiShipping() : self.convertObjectToArray(self.ruleMultiShipping);
        },

        convertObjectToArray: function (obj) {
            return Object.keys(obj).map(function (key) {
                return obj[key];
            });
        },

        afterRenderOptions: function (option, optionVal) {
            if (!extraFee.selectedOptionsMultiShipping()[optionVal.address_id]['rule']) {
                return;
            }

            var data = extraFee.selectedOptionsMultiShipping()[optionVal.address_id]['rule'],
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

        loadJsAfterKoRender: function () {
            var self  = this,
                rules = self.getRuleMultiShipping();

            $.each(rules, function (index, value) {
                $('.mp-extra-fee-shipping-' + index + ':not(:first)').remove();
                $('.mp-extra-fee-shipping-' + index).insertAfter($('.block.block-shipping:nth-child(' + (index + 1) + ') .box.box-shipping-method .box-content'));
            });

            return this;
        },

        initObservable: function () {
            var self = this;

            $('.items.methods-shipping input').each(function () {
                $(this).click(function () {
                    var payload         = {cartId: self.quoteId, area: 2},
                        url             = 'rest/' + self.code + '/V1/carts/mine/mpextrafeemultishipping',
                        extraLoader     = $('#extra-fee-loader'),
                        shippingMethods = {};

                    extraLoader.show();
                    $('.items.methods-shipping input:checked').each(function () {
                        var name = $(this).attr('name').match(/\d+/)[0];

                        shippingMethods[name] = $(this).val();
                    });

                    payload.shippingMethods = JSON.stringify(shippingMethods);

                    return storage.post(
                        url, JSON.stringify(payload), true, 'application/json', {}
                    )
                    .done(function (response) {
                        var selectOptions = [];

                        $.each(response[1], function (i, value) {
                            selectOptions[i] = value;
                        });

                        $('.box.box-shipping-method .mp-extra-fee-shipping').remove();
                        extraFee.selectedOptionsMultiShipping(selectOptions);
                        extraFee.ruleMultiShipping(self.convertObjectToArray(response[0]));
                        $.each(self.convertObjectToArray(response[0]), function (index, value) {
                            $('.mp-extra-fee-shipping-' + index + ':not(:first)').remove();
                            $('.mp-extra-fee-shipping-' + index).insertAfter($('.block.block-shipping:nth-child(' + (index + 1) + ') .box.box-shipping-method .box-content'));
                        });

                        extraLoader.hide();
                    });
                });

            });

            return this;
        },

        changeOption: function (option, event) {
            if (event.type !== 'change') {
                return;
            }

            var target  = event.currentTarget,
                note    = $(target).val(),
                url     = BASE_URL + 'mpextrafee/update/note',
                payload = {
                    note: note,
                    key: $(target).attr('id'),
                    address_id: option.address_id
                };

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: payload
            });
        },

        getNoteMessage: function (addressId, key) {
            var ele = '#' + key;
            if (typeof(extraFee.selectedOptionsMultiShipping()[addressId]['note']) !== "undefined") {
                $(ele).val(extraFee.selectedOptionsMultiShipping()[addressId]['note'][key]);
            }
        }
    });
});
