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
    'Magento_Catalog/js/price-utils'
], function ($, priceUtils) {
    'use strict';

    $.widget('mageplaza.rmaItems', {
        options: {
            priceFormat: {}
        },

        /**
         * @inheritDoc
         */
        _create: function () {
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
         * Enable/disable edit request items
         */
        _enableItem: function (event) {
            var selectedRow = $(event.target).parents('tr');

            selectedRow.find('input,textarea,select').not(':input[type=checkbox]').prop('disabled', $(event.target).val() !== '1');
        },

        _changeQty: function (event) {
            var selectedRow = $(event.target).parents('tr'),
                qty         = parseFloat($(event.target).val()),
                price       = parseFloat(selectedRow.find('.col-price input').val());

            selectedRow.find('.col-amount-return span.mp-amount-return-text').text(priceUtils.formatPrice(qty * price, this.options.priceFormat));
            selectedRow.find('.col-amount-return input').val(qty * price);
        }
    });

    return $.mageplaza.rmaItems;
});