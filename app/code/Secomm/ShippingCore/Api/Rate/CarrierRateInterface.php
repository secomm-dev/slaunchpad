<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

/**
 * TASK-NAT3YV (Phase E-C1) — a realtime carrier rate as reported into the common rate outcome.
 *
 * Amount rule is deliberately ASYMMETRIC with FallbackRateInterface: a realtime carrier may
 * legitimately quote a zero fee (provider promotion/free-shipping campaign), so zero is
 * domain-valid here — negative is the only invalid amount. (Fallback rates are strictly
 * positive because "no fallback" is expressed as null, never a zero-fee sentinel.)
 *
 * Deliberately minimal and Magento-free: no RateResult/Method dependency — carrier adapters
 * translate between Magento/provider structures and this domain value. No service level and no
 * carrier identity: the collector already knows which carrier and which service-level bucket
 * produced the rate (SPIKE-YH439T — carrier ≠ service level ≠ rate source).
 */
interface CarrierRateInterface
{
    /** Quoted amount in the store currency (>= 0; zero is a valid promotional rate). */
    public function getAmount(): float;

    /** ISO currency code when the carrier quoted a specific one; null = store default. */
    public function getCurrency(): ?string;
}
