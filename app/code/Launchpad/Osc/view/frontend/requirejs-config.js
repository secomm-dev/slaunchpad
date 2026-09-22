/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

var config = {
    config: {
        mixins: {
            /* SLP-214 (BUG-F8R4E8): collapse duplicate init-time shipping-rate
             * estimations (double POST /V1/carts/mine/estimate-shipping-methods). */
            'Mageplaza_Osc/js/model/shipping-rate-service': {
                'Launchpad_Osc/js/model/shipping-rate-service-mixin': true
            }
        }
    }
};
