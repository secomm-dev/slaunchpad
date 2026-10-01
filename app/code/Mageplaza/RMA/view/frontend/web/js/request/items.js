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
    'jquery'
], function ($) {
    'use strict';

    $.widget('mageplaza.rmaItems', {
        options: {
            selectInputs: {}
        },

        /**
         * @inheritDoc
         */
        _create: function () {
            this.options.selectInputs = $('table#mp-RMA-return-items input[data-role=enable-item]');
            this._bind();
        },

        /**
         * Bind handler to elements
         * @protected
         */
        _bind: function () {
            var el = this;

            el._on(this.element, {
                'change input[data-role=enable-item]': function (event) {
                    el._enableItem(event);
                },

                'keyup input[data-role=change-qty]': function (event) {
                    el._changeQty(event);
                }
            });
        },

        /**
         *
         * @param event
         * @private
         */
        _enableItem: function (event) {
            var itemId        = $(event.target).attr('data-item-id'),
                selectedRow   = $(event.target).parents('tr'),
                additionalRow = $(event.target).parents('tbody').find('tr#mp-items-' + itemId + '-additional'),
                submitBtn     = $('button[data-role=request-submit]'),
                isSelected    = false;

            selectedRow.find('input,textarea,select').not(':input[type=checkbox]')
            .prop('disabled', $(event.target).val() !== '1');
            additionalRow.find('input,textarea,select').prop('disabled', $(event.target).val() !== '1');
            additionalRow.toggle();
            this.options.selectInputs.each(function () {
                if ($(this).prop('checked') === true) {
                    isSelected = true;
                    return false;
                }
            });
            submitBtn.prop('disabled', !isSelected);
        },

        _changeQty: function (event) {
            var selectedRow = $(event.target).parents('tr'),
                qty         = parseFloat($(event.target).val()),
                price       = parseFloat(selectedRow.find('.col-price input').val());

            selectedRow.find('.mp-amount-return-value').val(qty * price);
        }
    });

    return $.mageplaza.rmaItems;
});