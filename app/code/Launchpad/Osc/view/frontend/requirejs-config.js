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
            },
            /* TASK-9ZEM73: drop the persisted shipping selection from localStorage
             * once no loaded rate offers it (stale method kept being re-applied and
             * rejected by the server as "Carrier with such method not found"). */
            'Magento_Checkout/js/action/select-shipping-method': {
                'Launchpad_Osc/js/action/select-shipping-method-mixin': true
            },
            /* TASK-9ZEM73: clear the global message list when a shipping-information
             * save succeeds — a transient failure must not pin its notice forever. */
            'Magento_Checkout/js/model/shipping-save-processor/default': {
                'Launchpad_Osc/js/model/shipping-save-processor-mixin': true
            }
        }
    }
};
