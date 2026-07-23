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
        'Magento_Customer/js/model/authentication-popup',
        'Magento_Customer/js/customer-data',
        'mage/translate'
    ],
    function ($, authenticationPopup, customerData, $t) {
        'use strict';

        return function (config, element) {
            $(element).on('click', function (event) {
                var cart = customerData.get('cart'),
                    customer = customerData.get('customer');
                var isValid = true;

                event.preventDefault();

                if (!customer().firstname && cart().isGuestCheckoutAllowed === false) {
                    authenticationPopup.showModal();

                    return false;
                }

                $('#block-extrafee-summary .message.notice').remove();
                $('.mp-extra-fee-required').each(function () {
                    if (!$(this).find('input:checked').length) {
                        $(this).append('<div class="message notice">\n' +
                            '          <span>' + $t('This is a required field') + '</span>\n' +
                            '</div>');
                        isValid = false;
                    }
                });
                if (!isValid) {
                    return;
                }

                location.href = config.checkoutUrl;
            });

        };
    }
);
