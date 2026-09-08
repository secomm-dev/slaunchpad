/**
 * Copyright © Secomm DevTeam All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Fix: jQuery UI datepicker opens at the wrong position in the Magento admin
 * (floating to the top of the page instead of anchoring to its input) whenever
 * the input sits below the top of the document — e.g. the "Date Off" rows of
 * Stores → Configuration → Mageplaza → Delivery Time (SLP-147 / BUG-SRF024).
 *
 * Root cause: mage/calendar::_overwriteFindPos (Magento 2.4.8-p1+, upstream
 * magento/magento2#40083) replaces jQuery UI's _findPos with a version that
 * returns viewport coordinates from getBoundingClientRect(), while
 * _showDatepicker assigns those values to #ui-datepicker-div which is
 * position:absolute against the document — every scroll offset is lost.
 *
 * Fix: re-register the widgets with an _overwriteFindPos that converts to
 * document coordinates (rect + window scroll offsets) — the same semantics as
 * the $.offset() call stock jQuery UI uses before 2.4.8-p1.
 *
 * Both 'mage.calendar' and 'mage.dateRange' are re-registered: dateRange
 * extends the original calendar constructor, so re-registering the calendar
 * widget alone would leave the broken override in place for dateRange
 * (admin date-range filters). Patching the datepicker prototype directly is
 * not enough either — every widget _create() re-applies Magento's override.
 */
define([
    'jquery'
], function ($) {
    'use strict';

    // The mixins plugin injects the target module ('mage/calendar') into the
    // factory below — declaring it as a dependency here would create a
    // requirejs circular dependency and hang the module loader.
    return function (calendar) {
        var calendarPositionFix = {
            /**
             * Re-applies the _findPos override with document-relative
             * coordinates so #ui-datepicker-div lands next to its input
             * regardless of scroll offsets.
             */
            _overwriteFindPos: function () {
                $.datepicker.constructor.prototype._findPos = function (obj) {
                    var domPosition = obj.getBoundingClientRect();

                    return [
                        domPosition.left + window.pageXOffset,
                        domPosition.top + window.pageYOffset
                    ];
                };
            }
        };

        $.widget('mage.calendar', calendar.calendar, calendarPositionFix);
        $.widget('mage.dateRange', $.mage.dateRange, calendarPositionFix);

        return {
            dateRange: $.mage.dateRange,
            calendar: $.mage.calendar
        };
    };
});
