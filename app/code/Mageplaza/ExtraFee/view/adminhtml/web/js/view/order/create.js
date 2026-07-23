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
    "Magento_Sales/order/create/form",
    'mpIonRangeSlider'
], function ($) {
    "use strict";

    order.selectMpExtraFee = function (area) {
        var extraFeeFormData;
        var billingExtraFee = $('#order-billing-extra-fee :input').serialize();
        var shippingExtraFee = $('#order-shipping-extra-fee :input').serialize();
        var extraFee = $('#order-mp_extra_fee :input').serialize();

        switch (area) {
            case '1':
                extraFeeFormData = billingExtraFee;
                break;
            case '2':
                extraFeeFormData = shippingExtraFee;
                break;
            case '3':
                extraFeeFormData = extraFee;
                break;
            case '1,2,3':
                extraFeeFormData = billingExtraFee + ',' + shippingExtraFee + ',' + extraFee;
        }

        this.loadArea(['totals', 'billing_method', 'shipping_method', 'mp_extra_fee'], true, {
            'mp_extra_fee': extraFeeFormData,
            'mp_extra_fee_area': area,
        });
    };
    (function (parent) {
        AdminOrder.prototype.loadArea = function (area, indicator, params) {
            if ($.inArray('card_validation', area) !== -1) {
                window.order.selectMpExtraFee('1,2,3');
                area.push('mp_extra_fee')
            }

            parent.call(this, area, indicator, params);
        };
    }(AdminOrder.prototype.loadArea));
    $('body').on('change', '.select.mp-extra-fee', function () {
        var area = $(this).attr('area');
        window.order.selectMpExtraFee(area);
    });
});
