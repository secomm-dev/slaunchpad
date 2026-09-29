/**
 * Secomm_CodRisk — COD Risk telephone sync (TASK-YPWH9B, Bug 1 UI part, 2026-09-22)
 *
 * OSC reloads shipping/payment only on rate-affecting address fields — the
 * telephone field alone never triggers a reload, so a blocked phone keeps a stale
 * COD tile until the customer edits another address field.
 *
 * Fix: when the telephone input changes (debounced, after blur), invoke the CORE
 * action set-shipping-information — the same flow an address edit triggers: the
 * address (with the new phone) is saved to the quote server-side and the payment
 * methods are re-fetched, so the availability plugin re-evaluates with fresh data.
 * Uses the core action only — no Mageplaza internals — safe across OSC upgrades.
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/set-shipping-information'
], function ($, quote, setShippingInformation) {
    'use strict';

    return function () {
        var timer = null;

        $(document).on('change', 'input[name="telephone"]', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                try {
                    // Needs a chosen shipping rate — otherwise the request would
                    // fail validation and show a confusing message.
                    if (!quote.shippingAddress() || !quote.shippingMethod()) {
                        return;
                    }
                    setShippingInformation();
                } catch (e) {
                    // Never break checkout over the sync — the place-order guard
                    // still blocks invalid COD orders server-side.
                }
            }, 600);
        });
    };
});