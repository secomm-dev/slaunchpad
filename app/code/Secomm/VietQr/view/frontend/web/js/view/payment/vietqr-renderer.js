/**
 * Registers the VietQR payment method renderer for the checkout.
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push(
        {
            type: 'secomm_vietqr',
            component: 'Secomm_VietQr/js/view/payment/method-renderer/vietqr-method'
        }
    );

    return Component.extend({});
});
