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
    'underscore',
    'Magento_Ui/js/modal/alert',
    'jquery/ui',
    'jquery/validate',
    'mage/translate',
    'mage/validation'
], function ($, _, alert) {
    'use strict';

    $.extend(true, $.validator.prototype, {
        checkForm: function () {
            $('.admin__page-nav-item-message._error').hide();
            this.prepareForm();
            for (var i = 0, elements = (this.currentElements = this.elements()); elements[i]; i++) {
                this.check(elements[i]);
            }
            return this.valid();
        },
    });
});
