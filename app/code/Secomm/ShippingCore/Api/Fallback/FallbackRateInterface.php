<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Fallback;

/**
 * TASK-XXBN5X (Phase E-SL0, SPIKE-WHHEZV §9/§12; amount rule aligned r2) — a service-level
 * FALLBACK price.
 *
 * Represents the service level ("Standard Delivery — 40,000"), never a carrier: no carrierCode /
 * provider carrier code exists on this contract, and no generic metadata dump either — backend
 * rate-source/provenance bookkeeping belongs to the future ShippingCore orchestration, not to
 * this customer-outcome value.
 *
 * Amount is NON-NEGATIVE: zero is a valid explicit rate; negative is invalid. A provider with
 * no fallback rate returns NULL from FallbackRateProviderInterface::getRate() — null is the
 * ONLY representation of "no fallback rate" (a zero fee is never an implicit no-match).
 * Forbidding zero-valued emergency fallback is project-level policy, not a core invariant.
 */
interface FallbackRateInterface
{
    /** Non-negative fallback price in the store currency (zero = valid explicit rate). */
    public function getAmount(): float;

    /** Service-level display label (config concern, e.g. "Standard Delivery"). */
    public function getLabel(): string;

    /** Human-readable delivery estimate (e.g. "2 - 4 day(s)"); null when the provider has none. */
    public function getDeliveryEstimate(): ?string;
}
