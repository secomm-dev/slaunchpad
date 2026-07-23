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
 * @copyright Copyright (c) 2018 Mageplaza (http://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

define(
    [
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/translate',
    'Mageplaza_Core/js/jquery.magnific-popup.min'
    ], function ($, customerData, $t) {
        'use strict';

        return function (widget) {

            $.widget(
                'mageplaza.socialpopup', widget, {

                    /**
                     * getEffect
                     */
                    getEffect: function () {
                        return this.options.popupEffect;
                    }
                }
            );

            return $.mageplaza.socialpopup;
        }
    }
);