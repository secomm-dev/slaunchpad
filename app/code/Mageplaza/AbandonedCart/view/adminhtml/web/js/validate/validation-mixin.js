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
    'jquery'
], function ($) {
    'use strict';

    return function (target) {
        $.validator.addMethod(
            'mp-validate-phone-number', function (data) {
                var filter = /^[0-9+]+$/;

                return data.match(filter);
            },
            $.mage.__('Not a valid Phone Number')
        );

        return target;
    }
});
