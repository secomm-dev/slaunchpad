<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — THE outer rate-collection seam.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Plugin\Shipping;

use Launchpad\MageplazaTableRate\Model\FallbackCoordinator;
use Launchpad\MageplazaTableRate\Model\MethodVisibilityFilter;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Shipping;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeCollectorInterface;

/**
 * Why THIS seam (documented per directive §6): `Magento\Shipping\Model\Shipping` is the sole
 * preference of `Quote\Address\RateCollectorInterface` (vendor module-shipping/etc/di.xml), so
 * EVERY channel — storefront cart/checkout, REST and GraphQL estimates, admin order creation —
 * funnels through exactly this one call. One around-plugin therefore:
 *
 *   1. opens the ShippingCore outcome-collection bracket (per EXECUTION — Magento runs this
 *      once per Quote\Address::requestShippingRates() call, so state must never be assumed
 *      request-wide),
 *   2. lets proceed() run the normal carrier collection,
 *   3. filters per-method customer visibility from the completed result — customer-facing
 *      collects only (TASK-1WKX9C: a carrier-limited collect is Magento's checkout
 *      save/validation re-collect; hidden methods must stay visible there or the
 *      fallback-presented rate the customer selected fails core's validation),
 *   4. appends eligible fallback rates, and
 *   5. closes the bracket.
 *
 * Carriers are never called here, no carrier is hidden by membership, and Mageplaza's own
 * `carriers/mptablerate/active` gate stays exactly where Mageplaza put it — the fallback
 * append does not depend on that flag (it composes, not collects).
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class CollectRatesPlugin
{
    public function __construct(
        private readonly CarrierRateOutcomeCollectorInterface $outcomeCollector,
        private readonly MethodVisibilityFilter $visibilityFilter,
        private readonly FallbackCoordinator $fallbackCoordinator
    ) {
    }

    /**
     * @return mixed the Shipping collector itself (proceed() returns $this; result lives in
     *         $subject->getResult())
     */
    public function aroundCollectRates(Shipping $subject, callable $proceed, RateRequest $request)
    {
        $this->outcomeCollector->beginCollection();

        try {
            $proceed($request);

            $result = $subject->getResult();
            if ($result instanceof Result) {
                // TASK-1WKX9C — hidden-method stripping is a customer-facing PRESENTATION
                // rule; a carrier-limited collect is not customer-facing. Magento's checkout
                // save/validation flows (ShippingInformationManagement, PaymentInformation-
                // Management, GuestPaymentInformationManagement) set limitCarrier on the
                // address and re-collect ONLY the requested carrier, and their result is
                // never rendered. When the primary carrier failed on the customer-facing
                // estimate, the offered rate may be a fallback copy of a hidden method
                // (show_to_customer=0, e.g. mptablerate Standard) — but a limited collect
                // never runs the primary carrier, so the outcome collector stays empty and
                // appendFallbackRates() cannot re-append the copy. Stripping the hidden
                // method here as well would make core's rate check reject exactly the rate
                // we offered → HTTP 404 "Carrier with such method not found" on
                // shipping-information / payment-information (reproduced 2026-09-25,
                // outer-district address, mptablerate_8 → 404 vs mptablerate_7 → 200).
                if (!$request->getLimitCarrier()) {
                    $this->visibilityFilter->filter($result);
                }
                $this->fallbackCoordinator->appendFallbackRates($request, $result);
            }
        } finally {
            // The bracket MUST close even when a carrier (or any third-party around-plugin
            // chained into the same call) throws — otherwise the in-memory outcomes of a
            // failed collection could survive into the next collection of the same HTTP
            // request. No path leaves the bracket open.
            $this->outcomeCollector->endCollection();
        }

        return $subject;
    }
}
