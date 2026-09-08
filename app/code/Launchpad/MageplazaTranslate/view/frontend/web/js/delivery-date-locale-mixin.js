/**
 * Copyright © Secomm DevTeam All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Localize the Delivery Date calendar (jQuery UI datepicker) of
 * Mageplaza_DeliveryTime on vi_VN storefronts (BUG-GJT6C1 / ext ticket SLP-146).
 *
 * The Knockout component registers its `mpdatepicker` binding during
 * initialize(), so applying the Vietnamese jQuery UI regional right before
 * `_super()` guarantees the calendar picks it up. Magento ships no
 * datepicker-vi locale and the checkout page runs in the Magento/luma scope,
 * so this cannot be done from the theme layer (LL-0011).
 *
 * The regional intentionally carries NO dateFormat: the module passes the
 * store-configured format explicitly, which always wins over the defaults.
 *
 * en_US storefronts keep the English default — the regional is only applied
 * when <html lang> starts with "vi" (per-page-store gate, same rule as the
 * flatpickr l10n of the original approach).
 */
define([
    'jquery'
], function ($) {
    'use strict';

    return function (Component) {
        return Component.extend({
            initialize: function () {
                if ((document.documentElement.lang || '').startsWith('vi')) {
                    registerVietnameseRegional();
                    if ($.datepicker && $.datepicker.regional.vi) {
                        $.datepicker.setDefaults($.datepicker.regional.vi);
                    }
                }

                return this._super();
            }
        });

        function registerVietnameseRegional() {
            if (!($.datepicker && !$.datepicker.regional.vi)) {
                return;
            }

            $.datepicker.regional.vi = {
                closeText: 'Đóng',
                prevText: 'Trước',
                nextText: 'Sau',
                currentText: 'Hôm nay',
                monthNames: [
                    'Tháng một',
                    'Tháng hai',
                    'Tháng ba',
                    'Tháng tư',
                    'Tháng năm',
                    'Tháng sáu',
                    'Tháng bảy',
                    'Tháng tám',
                    'Tháng chín',
                    'Tháng mười',
                    'Tháng mười một',
                    'Tháng mười hai'
                ],
                monthNamesShort: [
                    'Th1', 'Th2', 'Th3', 'Th4', 'Th5', 'Th6',
                    'Th7', 'Th8', 'Th9', 'Th10', 'Th11', 'Th12'
                ],
                dayNames: [
                    'Chủ nhật',
                    'Thứ hai',
                    'Thứ ba',
                    'Thứ tư',
                    'Thứ năm',
                    'Thứ sáu',
                    'Thứ bảy'
                ],
                dayNamesShort: ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
                dayNamesMin: ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
                weekHeader: 'Tu',
                firstDay: 1,
                isRTL: false,
                showMonthAfterYear: false,
                yearSuffix: ''
            };
        }
    };
});
