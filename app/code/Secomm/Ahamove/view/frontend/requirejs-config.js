/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

var config = {
    map: {
        '*': {
            'Magento_Checkout/js/view/shipping-address/address-renderer/default':
                'Secomm_Ahamove/js/view/shipping-address/address-renderer/default',
            'Magento_Checkout/js/action/select-shipping-method':
                'Secomm_Ahamove/js/action/select-shipping-address',
            'Magento_Checkout/js/model/cart/totals-processor/default':
                'Secomm_Ahamove/js/model/cart/totals-processor/default-mixins',
            'Magento_Checkout/js/model/shipping-rate-processor/new-address':
                'Secomm_Ahamove/js/model/shipping-rate-processor/new-address-mixins',
        },
        config: {
            mixins: {
                'Magento_Checkout/js/view/shipping': {
                    'Secomm_Ahamove/js/view/shipping-mixins': true,
                },
                'Magento_Checkout/js/model/shipping-rates-validator': {
                    'Secomm_Ahamove/js/model/shipping-rates-validator-mixins': true
                }
            }
        }
    }
};
