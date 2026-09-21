<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Fallback;

use Secomm\ShippingCore\Api\Fallback\FallbackRateInterface;

/**
 * TASK-XXBN5X r2 — immutable, self-guarding fallback rate VO; @see FallbackRateInterface.
 *
 * Amount rule (aligned with CarrierRate by TL/SA review): negative is invalid, ZERO is a valid
 * explicit rate (e.g. a merchant-configured free fallback), and "no fallback rate" is expressed
 * EXCLUSIVELY as null from the provider — never as a zero-fee sentinel. Forbidding zero-valued
 * emergency fallback, if a project ever needs that, is fallback POLICY owned by project
 * composition/orchestration — not a core VO invariant.
 */
final class FallbackRate implements FallbackRateInterface
{
    /**
     * @param float $amount non-negative fallback price in the store currency (zero = valid explicit rate)
     * @param string $label service-level display label (non-empty)
     * @param string|null $deliveryEstimate human-readable estimate; null when unavailable
     * @throws \LogicException on a negative amount or an empty label
     */
    public function __construct(
        private readonly float $amount,
        private readonly string $label,
        private readonly ?string $deliveryEstimate = null
    ) {
        if ($amount < 0) {
            throw new \LogicException('Fallback rate amount must not be negative.');
        }
        if (trim($label) === '') {
            throw new \LogicException('Fallback rate requires a non-empty service-level label.');
        }
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDeliveryEstimate(): ?string
    {
        return $this->deliveryEstimate;
    }
}
