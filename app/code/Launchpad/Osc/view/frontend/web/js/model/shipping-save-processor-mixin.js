/**
 * Secomm Launchpad — Launchpad_Osc
 *
 * TASK-9ZEM73: don't leave stale error notices on the checkout page.
 *
 * A failed /V1/carts/mine/shipping-information POST reports through
 * errorProcessor into the global Magento_Ui message list — and NO success
 * path (shipping-save-processor/default, reload-order-summary) ever clears
 * that list; only the arrival of a NEXT message replaces it. One transient
 * failure (e.g. a stale persisted shipping selection the server rightly
 * rejects) therefore pins an error notice on the checkout forever, even
 * after rates load and every later save succeeds.
 *
 * Wraps the core save processor: when a save SUCCEEDS, clear the global
 * message list (same container the failure path wrote to). Genuine failures
 * still render normally — they are only cleared once the checkout state is
 * valid again. Payloads and the failure path itself are untouched, and the
 * mixin composes with the existing wrappers on this target (DeliveryTime,
 * Secomm_AddressDropdown, Mageplaza_OscPro).
 */
define([
    'Magento_Ui/js/model/messageList'
], function (globalMessageList) {
    'use strict';

    return function (shippingSaveProcessor) {
        var original = shippingSaveProcessor.saveShippingInformation;

        shippingSaveProcessor.saveShippingInformation = function () {
            var deferred = original.apply(this, arguments);

            // The OscPro loading-speed wrapper may short-circuit without a
            // promise (refresh_page === '2') — nothing to attach to then.
            if (deferred && typeof deferred.done === 'function') {
                deferred.done(function () {
                    globalMessageList.clear();
                });
            }

            return deferred;
        };

        return shippingSaveProcessor;
    };
});
