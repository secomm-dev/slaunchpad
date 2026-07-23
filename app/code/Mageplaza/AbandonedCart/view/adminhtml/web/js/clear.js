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
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'Magento_Ui/js/modal/confirm',
], function ($, modal, $t, confirmation) {
    "use strict";

    $.widget('mageplaza.abandonedcart', {


        /**
         * This method constructs a new widget.
         * @private
         */
        _create: function () {
            this.clearLogs();
        },

        /**
         * clearLogs
         */
        clearLogs: function () {
            var self = this;

            $('.page-actions-buttons').click(function () {
                confirmation({
                    title: 'Confirmation',
                    content: 'Are you sure you want to clear all logs?',
                    actions: {
                        confirm: function () {
                            $('#mp-loader').show();
                            window.location.href = self.options.clearUrl;
                        },

                        cancel: function () {
                            return false;
                        }
                    }
                });
            });
        }
    });

    return $.mageplaza.abandonedcart;
});
