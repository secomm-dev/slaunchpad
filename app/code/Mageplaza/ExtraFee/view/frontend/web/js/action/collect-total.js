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

define(
    [
        'jquery',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/totals',
        'Mageplaza_ExtraFee/js/model/resource-url-manager',
        'Magento_Checkout/js/model/quote',
        'Mageplaza_ExtraFee/js/model/extra-fee'
    ],
    function ($, storage, errorProcessor, totals, resourceUrlManager, quote, extraFee) {
        'use strict';

        return function (area, event) {
            var formData,
                billingEl  = $('#mp-extra-fee-billing'),
                shippingEl = $('#mp-extra-fee-shipping'),
                extraFeeEl = $('#mp-extra-fee');
            if (event) {
                var checked   = $(event.currentTarget),
                    isChecked = !!checked.prop('checked');
            }

            switch (area){
                case '1':
                    formData = billingEl.length ? billingEl.serialize() : '';
                    break;
                case '2':
                    formData = shippingEl.length ? shippingEl.serialize() : '';
                    break;
                case '3':
                    formData = extraFeeEl.length ? extraFeeEl.serialize() : '';
                    break;
                case '1,2,3':
                    formData = (billingEl.length ? billingEl.serialize() : '') + ',' +
                        (shippingEl.length ? shippingEl.serialize() : '') + ',' +
                        (extraFeeEl.length ? extraFeeEl.serialize() : '');
                    break;
                case '1,3':
                    formData = (billingEl.length ? billingEl.serialize() : '') + ',' +
                        (extraFeeEl.length ? extraFeeEl.serialize() : '');
                    break;
                case '2,3':
                    formData = (shippingEl.length ? shippingEl.serialize() : '') + ',' +
                        (extraFeeEl.length ? extraFeeEl.serialize() : '');
                    break;
            }

            if ((area === '1' || area === '2' || area === '3') && !formData) {
                return $.Deferred().resolve();
            }

            if (area.indexOf(',') !== -1) {
                var hasValidData = false;
                var areaParts    = area.split(',');
                for (var i = 0; i < areaParts.length; i++){
                    var checkArea = areaParts[i];
                    if ((checkArea === '1' && billingEl.length && billingEl.serialize()) ||
                        (checkArea === '2' && shippingEl.length && shippingEl.serialize()) ||
                        (checkArea === '3' && extraFeeEl.length && extraFeeEl.serialize())) {
                        hasValidData = true;
                        break;
                    }
                }
                if (!hasValidData) {
                    return $.Deferred().resolve();
                }
            }

            totals.isLoading(true);

            var payload = {
                formData: formData,
                area: area
            };
            return storage.post(
                resourceUrlManager.getUrlForCollectTotal(quote),
                JSON.stringify(payload)
            ).done(function (totals) {
                extraFee.isDuplicate = true;
                quote.setTotals(totals);
                if (event) {
                    checked.prop('checked', isChecked);
                }
            }).fail(function (response) {
                errorProcessor.process(response);
            }).always(function () {
                totals.isLoading(false);
            });
        };
    }
);
