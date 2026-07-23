/**
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

var config = {
    map: {
        '*': {
            'Magento_Checkout/js/proceed-to-checkout': 'Mageplaza_ExtraFee/js/proceed-to-checkout'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Mageplaza_ExtraFee/js/view/shipping-mixin': true
            },
            'Magento_Checkout/js/action/select-payment-method': {
                'Mageplaza_ExtraFee/js/action/select-payment-method-mixin': true
            },
        }
    }
};
