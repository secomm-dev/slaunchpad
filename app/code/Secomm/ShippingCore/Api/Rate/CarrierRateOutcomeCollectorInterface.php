<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — carrier-neutral normalized outcome collector.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;

/**
 * Contract for carriers to REPORT normalized rate outcomes back into ShippingCore while Magento
 * Shipping Framework runs the carrier — and for a composition (e.g. Launchpad_MageplazaTableRate)
 * to READ them after a rate collection finished. ShippingCore owns outcome SEMANTICS only:
 * the collector knows nothing about concrete carriers, holds no Mageplaza concepts and never
 * persists state (request-scoped memory, no DB/session/cache).
 *
 * Magento's rate collection is NOT "once per HTTP request": `RateCollectorInterface::collectRates()`
 * runs once per `Quote\Address::requestShippingRates()` call (per-address in multi-address
 * checkout, again after address changes, once per REST/GraphQL estimate call). Therefore state
 * is bracketed per EXECUTION via {@see beginCollection()} / {@see endCollection()} — never per
 * request. A carrier records while the bracket is open; outcomes recorded outside any bracket
 * are dropped safely (a carrier may legitimately run outside a bracketed collection, e.g. admin
 * flows, without corrupting or triggering anything).
 *
 * Identity is the stable Magento shipping-method PAIR (carrier_code, method_code) — never an
 * underscore-concatenated composite string.
 */
interface CarrierRateOutcomeCollectorInterface
{
    /**
     * Open a new isolated execution: discards any outcomes still buffered from a previous
     * bracket and makes record() buffer into this execution until endCollection().
     */
    public function beginCollection(): void;

    /**
     * Report one normalized outcome for one Magento shipping method, keyed by the
     * (carrier_code, method_code) pair. Recording the same pair again within the same
     * execution overwrites (last attempt wins). Safe no-op when no execution is open —
     * an orphan report is dropped, never persisted, never thrown to the carrier.
     */
    public function record(
        string $carrierCode,
        string $methodCode,
        CarrierRateOutcomeInterface $outcome
    ): void;

    /**
     * Outcomes of the CURRENT open execution only.
     *
     * @return array<string, array<string, CarrierRateOutcomeInterface>>
     *         map: carrier_code ⇒ method_code ⇒ outcome
     */
    public function getOutcomes(): array;

    /**
     * TASK-SEC-D-transport — record the ATOMIC decision of one execution: the outcome and the
     * fallback-eligibility decision OF THE SAME execution, with explicit transport presence.
     * An explicit NONE is `FallbackEligibility` with zero sources — never null.
     *
     * Deterministic duplicate/merge rules (never last-wins):
     *   - identical duplicate → idempotent no-op;
     *   - any SUCCESS for a member is terminal: a later non-success cannot override it;
     *   - SUCCESS arriving after a non-success overrides it (success wins);
     *   - same decision twice (status AND reason identical) → first kept, sources merged;
     *   - conflicting non-success decisions → the FIRST record wins WHOLE (outcome AND
     *     eligibility stay of the same execution — never synthesize a mixed record).
     *
     * Safe no-op when no collection bracket is open.
     */
    public function recordDecision(
        string $carrierCode,
        string $methodCode,
        CarrierRateOutcomeInterface $outcome,
        FallbackEligibilityInterface $fallbackEligibility
    ): void;

    /**
     * Atomic decision records of the CURRENT open execution only (legacy `record()` calls
     * appear here WITHOUT eligibility transport).
     *
     * @return array<string, array<string, CarrierRateDecisionRecordInterface>>
     *         map: carrier_code ⇒ method_code ⇒ record
     */
    public function getDecisionRecords(): array;

    /** Close the current execution: subsequent getOutcomes() is empty until beginCollection(). */
    public function endCollection(): void;
}
