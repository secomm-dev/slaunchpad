/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license sliderConfig is
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
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'Magento_Ui/js/grid/columns/select'
], function (Column) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'ui/grid/cells/html'
        },
        getLabel: function (record) {
            var label = this._super(record);

            if (label !== '') {
                if (record.status === "1") {
                    label = '<div class="ace-grid-status severity-recover"><span>' + label + '</span></div>';
                } else if (record.status === "0") {
                    label = '<div class="ace-grid-status severity-sent"><span>' + label + '</span></div>';
                } else {
                    label = '<div class="ace-grid-status severity-error"><span>' + label + '</span></div>';
                }
            }
            return label;
        }
    });
});

