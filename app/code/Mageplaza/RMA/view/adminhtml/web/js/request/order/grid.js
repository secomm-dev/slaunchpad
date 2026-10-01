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

    $.widget('mageplaza.rmaOrderGrid', {
        options: {},

        /**
         * @inheritDoc
         */
        _create: function () {
            var orderPopup = $('#mp-rma-order-form'),
                incrementIdField = $('#request_order_fieldset #request_order_increment_id');

            this._on(this.element, {
                'click #requestOrdersGrid_table tbody tr': function (event) {
                    var tRow = $(event.target).parents('tr');
                    var incrementId = tRow.find('.col-increment_id').text();

                    orderPopup.trigger('closeModal');
                    incrementIdField.val(incrementId.trim());
                }
            });
        }
    });

    return $.mageplaza.rmaOrderGrid;
});