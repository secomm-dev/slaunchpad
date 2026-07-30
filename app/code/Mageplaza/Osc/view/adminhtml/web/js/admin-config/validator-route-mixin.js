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
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */
define(
    [
        'jquery'
    ], function ($) {
        'use strict';
        return function (target) {
            $.validator.addMethod(
                'validate-unicode-letters',
                function (value) {
                    if (value.length === 0) {
                        return true;
                    }
                    return /^[\p{L}\u4E00-\u9FFF]+$/u.test(value);
                },
                $.mage.__('Please use letters only (Unicode, including Chinese characters).')
            );
            return target;
        };
    }
);
