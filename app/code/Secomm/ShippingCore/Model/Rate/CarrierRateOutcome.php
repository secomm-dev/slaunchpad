<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;

/**
 * TASK-NAT3YV — immutable, self-guarding rate outcome VO; @see CarrierRateOutcomeInterface.
 *
 * Impossible states are unconstructible (fail-fast, mirror of ResolvedShippingAddress): SUCCESS
 * always carries a rate and never a reason; UNAVAILABLE/TECHNICAL_FAILURE never carry a rate.
 * The named factories are the ergonomic construction path — the public constructor remains for
 * parity with the module's other VOs. Domain state only: no logger, no Magento rate objects,
 * no metadata bag.
 */
final class CarrierRateOutcome implements CarrierRateOutcomeInterface
{
    private readonly ?CarrierRateInterface $rate;

    private readonly ?string $failureReason;

    /**
     * @param string $status STATUS_*
     * @param CarrierRateInterface|null $rate required for SUCCESS, forbidden otherwise
     * @param string|null $failureReason forbidden for SUCCESS; optional otherwise (empty → null)
     * @throws \LogicException on any impossible combination or an unknown status
     */
    public function __construct(
        private readonly string $status,
        ?CarrierRateInterface $rate,
        ?string $failureReason
    ) {
        $reason = $failureReason !== null && trim($failureReason) === '' ? null : $failureReason;

        switch ($status) {
            case self::STATUS_SUCCESS:
                if ($rate === null) {
                    throw new \LogicException('A successful rate outcome must carry a rate.');
                }
                if ($reason !== null) {
                    throw new \LogicException('A successful rate outcome must not carry a failure reason.');
                }
                break;
            case self::STATUS_UNAVAILABLE:
            case self::STATUS_TECHNICAL_FAILURE:
                if ($rate !== null) {
                    throw new \LogicException(
                        sprintf('A %s outcome must not carry a rate.', strtolower($status))
                    );
                }
                break;
            default:
                throw new \LogicException(sprintf('Unknown carrier rate outcome status "%s".', $status));
        }

        $this->rate = $rate;
        $this->failureReason = $reason;
    }

    public static function success(CarrierRateInterface $rate): self
    {
        return new self(self::STATUS_SUCCESS, $rate, null);
    }

    /**
     * Carrier/business cannot serve this request for a non-transient reason — NEVER converted
     * from a temporary technical problem; see STATUS_UNAVAILABLE semantics.
     */
    public static function unavailable(?string $failureReason = null): self
    {
        return new self(self::STATUS_UNAVAILABLE, null, $failureReason);
    }

    /**
     * Temporary technical/provider failure (timeout, HTTP 5xx, outage…) — the only status that
     * may later contribute to service-level fallback eligibility.
     */
    public static function technicalFailure(?string $failureReason = null): self
    {
        return new self(self::STATUS_TECHNICAL_FAILURE, null, $failureReason);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRate(): ?CarrierRateInterface
    {
        return $this->rate;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
