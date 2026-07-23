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
    'Mageplaza_ExtraFee/js/model/extra-fee'
], function ($, ko, Component, extraFee) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mageplaza_ExtraFee/checkout/multishipping/extra-fee-billing'
        },
        ruleMultiShipping: extraFee.ruleMultiShipping,
        selectedOptionsMultiShipping: extraFee.selectedOptionsMultiShipping,
        ruleVirtualMultiShipping: extraFee.ruleVirtualMultiShipping,
        selectedOptionsVirtualMultiShipping: extraFee.selectedOptionsVirtualMultiShipping,

        initialize: function (config) {
            var self = this;


            if (!self.ruleMultiShipping().length) {
                self.setRuleMultiShipping(config.ruleMultiShipping);
            }

            if (!self.selectedOptionsMultiShipping().length) {
                extraFee.selectedOptionsMultiShipping(config.selectedOptionsMultiShipping);
            }

            if (!self.ruleVirtualMultiShipping().length) {
                self.setRuleVirtualMultiShipping(config.ruleVirtualMultiShipping);
            }

            if (!self.selectedOptionsVirtualMultiShipping().length) {
                extraFee.selectedOptionsVirtualMultiShipping(config.selectedOptionsVirtualMultiShipping);
            }

            this._super();
        },

        setRuleMultiShipping: function (ruleMultiShipping) {
            var self = this;

            self.ruleMultiShipping = ruleMultiShipping;
        },

        setRuleVirtualMultiShipping: function (ruleVirtualMultiShipping) {
            var self = this;

            self.ruleVirtualMultiShipping = ruleVirtualMultiShipping;
        },

        getRuleMultiShipping: function () {
            var self = this;

            return extraFee.ruleMultiShipping().length ? extraFee.ruleMultiShipping() : self.convertObjectToArray(self.ruleMultiShipping);
        },

        getRuleForVirtualItemsMultiShipping: function () {
            var self = this;

            return extraFee.ruleVirtualMultiShipping().length ? extraFee.ruleVirtualMultiShipping() : self.convertObjectToArray(self.ruleVirtualMultiShipping);
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

        afterRenderOptionsVirtual: function (option, optionVal) {
            if (extraFee.selectedOptionsVirtualMultiShipping()[optionVal.address_id] === undefined || !extraFee.selectedOptionsVirtualMultiShipping()[optionVal.address_id]['rule']) {
                return;
            }

            var data = extraFee.selectedOptionsVirtualMultiShipping()[optionVal.address_id]['rule'],
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

        getNoteMessage: function (addressId, ruleId, key) {
            var ele = '#' + key;
            if (typeof(this.ruleMultiShipping[addressId][ruleId]) === "undefined") {
                return;
            }

            var ruleData = this.ruleMultiShipping[addressId][ruleId];
            if (typeof(ruleData['note']) !== "undefined") {
                $(ele).val(ruleData['note'][key]);
            }
        },

        getVirtualNoteMessage: function(addressId, key) {
            var ele = '#' + key;

            if (typeof(this.ruleVirtualMultiShipping[addressId][0]) === 'undefined') {
                return;
            }

            var virtualRuleData = this.ruleVirtualMultiShipping[addressId][0];
            if (typeof(virtualRuleData['note']) !== 'undefined') {
                $(ele).val(virtualRuleData['note'][key]);
            }
        }
    });
});
