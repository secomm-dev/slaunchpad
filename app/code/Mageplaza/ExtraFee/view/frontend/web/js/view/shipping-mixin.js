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
    'mage/translate',
    'Mageplaza_ExtraFee/js/action/update-extra-fee-rule'
], function ($, _, $t, updateRule) {
    'use strict';
    return function (shippingComponent) {
        return shippingComponent.extend({

            validateShippingInformation: function () {
                var self = this;
                var result = this._super();
                $('#mp-extra-fee-shipping .message.notice').remove();
                var isValid = true;
                $('#mp-extra-fee-shipping .mp-extra-fee-required').each(function () {
                    if (!$(this).find('input:checked').length) {
                        isValid = false;
                    }
                });
                if (isValid) {
                    if (result) {
                        updateRule('1');
                        updateRule('2');
                    }
                    return result;
                } else {
                    self.errorValidationMessage($t('Please choose at least one option for each require extra fee'));
                    return false;
                }
            }
        })
    }
});