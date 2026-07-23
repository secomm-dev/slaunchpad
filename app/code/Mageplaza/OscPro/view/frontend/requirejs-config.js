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
 * @package     Mageplaza_OscPro
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

var config = {};
if (typeof window.oscRoute !== 'undefined' && window.location.href.indexOf(window.oscRoute) !== -1
    && window.loadingSpeedConfig.refresh_page === '2') {
    config = {
        map: {
            '*': {
                'Magento_SalesRule/js/action/cancel-coupon': 'Mageplaza_OscPro/js/action/cancel-coupon',
                'Magento_SalesRule/js/action/set-coupon-code': 'Mageplaza_OscPro/js/action/set-coupon-code',

            }
        },
        config: {
            mixins: {
                'Magento_Checkout/js/model/checkout-data-resolver': {
                    'Mageplaza_OscPro/js/model/checkout-data-resolver-mixin': true
                },
                'Magento_Checkout/js/model/shipping-save-processor/default': {
                    'Mageplaza_OscPro/js/model/shipping-save-processor/default-mixin': true
                },
                'Magento_Checkout/js/model/shipping-rate-processor/customer-address' : {
                    'Mageplaza_OscPro/js/model/shipping-rate-processor/customer-address-mixin': true
                },
                'Magento_Checkout/js/model/shipping-rate-processor/new-address' : {
                    'Mageplaza_OscPro/js/model/shipping-rate-processor/new-address-mixin': true
                }
            }
        }
    };
}
