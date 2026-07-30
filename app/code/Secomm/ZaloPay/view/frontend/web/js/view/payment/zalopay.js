/**********************************************************************
 * Zalo Pay payment
 *
 * @copyright Copyright © Secomm. All rights reserved.
 * @author    Secomm Teams
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push(
        {
            type: 'zalopay',
            component: 'Secomm_ZaloPay/js/view/payment/method-renderer/zalopay-wallet'
        }
    );

    /**
     * Add view logic here if needed
     */

    return Component.extend({});
});
