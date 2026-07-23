/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'mage/translate',
    'mage/backend/validation'
], function ($) {
    'use strict';

    return function () {
        $.validator.addMethod('mp-required-entry',
            function (value, element) {
                var inputs = $(element)
                        .closest('table')
                        .find('.mp-required-entry'),
                    applyType = $('#rule_apply_type'),
                    isValid = true;

                if (applyType.val() === '1') {
                    isValid = true;
                } else {
                    inputs.each(function () {
                        if ($(this).attr('type') === 'text' && this.value === "") {
                            isValid = false;
                        }
                    });
                }

                return isValid;
            },

            $.mage.__('This is an not null field.')
        );
    };
});