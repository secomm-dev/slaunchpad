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

/*jshint browser:true jquery:true*/
/*global alert*/
define(
    [
        'ko',
        'underscore',
        'Magento_Checkout/js/view/summary/shipping',
        'Magento_Checkout/js/model/totals'
    ],
    function (ko, _, Component, totals) {
        "use strict";
        var displayMode = window.checkoutConfig.reviewShippingDisplayMode;

        return Component.extend({
            defaults: {
                displayMode: displayMode,
                template: 'Mageplaza_ExtraFee/cart/totals/extra-fee'
            },
            getTitle: function (data) {
                return data.extension_attributes.rule_label + ((data.title && !data.code.includes('auto')) ? ' - ' + data.title : '') + ' ';
            },
            getValue: function (value) {
                return this.getFormattedPrice(parseFloat(value));
            },
            getExtraFee: function () {
                var segments = totals.totals().total_segments;
                var extraFee = [];
                _.each(segments, function (obj) {
                    if (obj.code.indexOf('mp_extra_fee_rule') !== -1) {
                        extraFee.push(obj);
                    }
                });
                return extraFee;
            },
            isBothPricesDisplayed: function () {
                return 'both' === this.displayMode
            },
            isIncludingDisplayed: function () {
                return 'including' === this.displayMode;
            },
            isExcludingDisplayed: function () {
                return 'excluding' === this.displayMode;
            }
        });
    }
);
