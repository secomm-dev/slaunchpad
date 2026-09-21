<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Fallback;

/**
 * TASK-XXBN5X (Phase E-SL0, SPIKE-WHHEZV §9) — optional provider-neutral fallback rate provider.
 *
 * Implemented by OPTIONAL third-party bridge modules and registered into
 * FallbackRateProviderPool via DI; ShippingCore contains zero knowledge of any provider
 * implementation and operates correctly with a zero-provider pool.
 *
 * The provider answers exactly ONE question — "given this service level and request, is there a
 * CONFIGURED fallback rate, and what is it?": null = no configured profile / no matching rule /
 * provider unavailable. It must NEVER decide shipping-service eligibility (coverage, cut-off,
 * distance, inventory…) or whether a fallback may be DISPLAYED — those belong to the future
 * ShippingCore service-level orchestration (SPIKE-WHHEZV §10: a rate match ≠ service eligibility).
 *
 * Never use a zero fee as an implicit no-match — return null when nothing applies. A zero
 * amount is a valid EXPLICIT rate (FallbackRate accepts non-negative amounts; r2 aligned with
 * CarrierRate).
 */
interface FallbackRateProviderInterface
{
    /**
     * @param string $serviceLevel a REGISTERED service-level machine code
     *        (ShippingServiceLevelRegistry — the taxonomy is dynamic, owned by composition)
     * @return FallbackRateInterface|null the configured fallback rate, or null when none applies
     */
    public function getRate(
        string $serviceLevel,
        FallbackRateRequestInterface $request
    ): ?FallbackRateInterface;
}
