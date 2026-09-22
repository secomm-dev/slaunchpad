/**
 * Secomm Launchpad — Launchpad_Osc
 *
 * SLP-214 / BUG-F8R4E8: in-flight dedupe for shipping-rate estimation.
 *
 * Mageplaza OSC fires two init-time estimations (shipping.js afterResolveDocument
 * + shipping-rates-validator's 200ms field timer) and the rate processor only
 * caches into rateRegistry after the first response lands, so the duplicate
 * re-POSTed the same payload to /V1/carts/mine/estimate-shipping-methods.
 *
 * Wraps Mageplaza_Osc/js/model/shipping-rate-service.estimateShippingMethod:
 * skip while an identical-address request is in flight; keyed by getCacheKey(),
 * so a genuine address change still refetches. The processor has no promise
 * surface (getRates returns undefined), so the guard releases when
 * shippingService.isLoading flips false — the processor sets it false in the
 * request's .always(), after rateRegistry is already populated, meaning any
 * later same-key call takes the cache-hit path anyway.
 *
 * Best-effort by design: isLoading is a shared flag, so a different-address
 * request completing first can release the guard early — worst case is one
 * extra request (today's behaviour), never a missed estimation.
 */
define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service'
], function (quote, shippingService) {
    'use strict';

    var inFlight = {};

    return function (shippingRateService) {
        var original = shippingRateService.estimateShippingMethod;

        shippingService.isLoading.subscribe(function (isLoading) {
            if (!isLoading) {
                inFlight = {};
            }
        });

        shippingRateService.estimateShippingMethod = function () {
            var address = quote.shippingAddress(),
                cacheKey = address && typeof address.getCacheKey === 'function'
                    ? address.getCacheKey()
                    : null;

            if (cacheKey && inFlight[cacheKey]) {
                return;
            }

            if (cacheKey) {
                inFlight[cacheKey] = true;
            }

            try {
                return original.apply(this, arguments);
            } catch (e) {
                if (cacheKey) {
                    delete inFlight[cacheKey];
                }
                throw e;
            }
        };

        return shippingRateService;
    };
});
