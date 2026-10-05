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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'jquery',
    'mpRMA/order'
], function ($, rmaOrder) {
    'use strict';

    $.widget('mageplaza.rmaOrderItemType', rmaOrder, {
        options: {
            eachItem: $('fieldset.mp-items-type .mp-field-items'),
            allItem: $('fieldset.mp-items-type .mp-field-all-items'),
            canReturnAllProducts: {},
            submitBtn: {},
            selectInputs: {}
        },

        /**
         * @inheritDoc
         */
        _create: function () {
            this.options.submitBtn    = $('button[data-role=request-submit]');
            this.options.eachItem     = $('fieldset.mp-items-type .mp-field-items');
            this.options.allItem      = $('fieldset.mp-items-type .mp-field-all-items');
            this.options.selectInputs = $('table#mp-RMA-return-items input[data-role=enable-item]');
            this.options.submitBtn.prop('disabled', !this.options.canReturnAllProducts);
            this._on(this.element, {
                'change select[data-role=return-type]': function (event) {
                    this._changeReturnType($(event.target).val());
                }
            });
        },

        /**
         * Change request return type
         */
        _changeReturnType: function (type) {
            var isSelected = false;

            if (type === '2') {
                this.options.eachItem.show();
                this.options.allItem.hide();
                this.options.allItem.find('select,input,textarea').prop('disabled', true);
                this.options.selectInputs.each(function () {
                    if ($(this).prop('checked') === true) {
                        isSelected = true;
                        return false;
                    }
                });
                if (!isSelected) {
                    this.options.submitBtn.prop('disabled', true);
                }
            } else {
                this.options.eachItem.hide();
                this.options.allItem.show();
                this.options.allItem.find('select,input,textarea').prop('disabled', false);
                this.options.submitBtn.prop('disabled', false);
            }
        }
    });

    return $.mageplaza.rmaOrderItemType;
});