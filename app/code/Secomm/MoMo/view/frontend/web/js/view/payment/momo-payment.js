/**
 * MoMo payment renderer registration for checkout.
 *
 * @author Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'momo_payment',
        component: 'Secomm_MoMo/js/view/payment/method-renderer/momo-method'
    });

    return Component.extend({});
});
