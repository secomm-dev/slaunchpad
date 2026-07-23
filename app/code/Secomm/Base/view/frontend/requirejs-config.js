/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
var config = {
    map: {
        '*': {
            'Magento_Checkout/js/model/cart/totals-processor/default':
                'Secomm_Base/js/model/cart/totals-processor/default-mixins',
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/shipping-rates-validator': {
                'Secomm_Base/js/model/shipping-rates-validator-mixins': true
            }
        }
    }

};
