/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

var config = {
    map: {
        '*': {
            mfFbPixelData: 'Magefan_FacebookPixelPlus/js/pixel'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/action/select-payment-method': {
                'Magefan_FacebookPixelPlus/js/action/select-payment-method-mixin': true
            }
        }
    }
};
