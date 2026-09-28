/**
 * Secomm Launchpad — Launchpad_Osc
 *
 * TASK-9ZEM73: self-heal a stale persisted shipping selection.
 *
 * Magento keeps the chosen shipping rate in the browser localStorage
 * (checkout-data.selectedShippingRate). The native resolver re-applies that
 * stored code on every rate resolution, and when nothing matches it unselects
 * via select-shipping-method(null) — which writes ONLY quote.shippingMethod
 * and never clears the stored code. If a method disappears from every rate
 * list after it was selected (e.g. a Mageplaza TableRate method switched to
 * fallback-only through the Launchpad visibility filter), the dead code stays
 * in storage forever and is re-applied on each page load / rate reload, then
 * POSTed to /V1/carts/mine/shipping-information and (correctly) rejected by
 * the server with "Carrier with such method not found".
 *
 * select-shipping-method is the single funnel every resolver path uses to
 * write quote.shippingMethod, so healing here is independent of resolver
 * mixin order. After an unselect: if the stored code is offered by none of
 * the currently loaded rates, drop it. A still-offered stored code (or an
 * explicit selection, or the transient empty rate list during init) is left
 * untouched.
 */
define([
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/shipping-service'
], function (checkoutData, shippingService) {
    'use strict';

    return function (selectShippingMethodAction) {
        return function (shippingMethod) {
            selectShippingMethodAction(shippingMethod);

            if (shippingMethod) {
                return;
            }

            var storedCode = checkoutData.getSelectedShippingRate();

            if (!storedCode) {
                return;
            }

            var rates = shippingService.getShippingRates()() || [],
                stillOffered = rates.some(function (rate) {
                    return rate['carrier_code'] + '_' + rate['method_code'] === storedCode;
                });

            if (!stillOffered) {
                // Nothing in the loaded rates matches the stored selection —
                // drop it so the stale code cannot be re-applied later.
                checkoutData.setSelectedShippingRate(null);
            }
        };
    };
});
