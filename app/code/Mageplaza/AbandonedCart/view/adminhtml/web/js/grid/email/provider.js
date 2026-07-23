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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'jquery',
    'Mageplaza_AbandonedCart/js/grid/abstract-provider'
], function ($, Element) {
    'use strict';

    return Element.extend({
        reload: function () {
            var dateRangeData = $('#daterange').data();
            var params = {};
            if (typeof this.params.mpFilter !== 'undefined') {
                params = this.params.mpFilter;
            } else {
                params.mpFilter = {};
                params.mpFilter['customer_group_id'] = $('.customer-group select').val();
                params.mpFilter.store = $('#store_switcher').val();
                params.mpFilter.period = $('.period select').val();
                params.mpFilter.startDate = dateRangeData.startDate.format('Y-MM-DD');
                params.mpFilter.endDate = dateRangeData.endDate.format('Y-MM-DD');
            }
            if (typeof this.params.filters !== 'undefined') {
                params.filters = this.params.filters;
            }
            this._super();
        }
    });
});
