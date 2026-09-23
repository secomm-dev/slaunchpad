<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Secomm\ShippingCore\Api\Rate\CarrierEligibilityResultInterface;

/**
 * TASK-8MQHJX (Phase B) — immutable carrier eligibility result VO;
 * @see CarrierEligibilityResultInterface.
 */
final class CarrierEligibilityResult implements CarrierEligibilityResultInterface
{
    /**
     * @param bool $eligible
     * @param string|null $reasonCode REASON_ELIGIBLE | REASON_DESTINATION_NOT_IN_SCOPE
     * @param string|null $matchedZoneCode matched zone code when eligible via SELECTED_ZONES
     */
    public function __construct(
        private readonly bool $eligible,
        private readonly ?string $reasonCode = null,
        private readonly ?string $matchedZoneCode = null
    ) {
    }

    public static function eligible(?string $matchedZoneCode = null): self
    {
        return new self(true, CarrierEligibilityResultInterface::REASON_ELIGIBLE, $matchedZoneCode);
    }

    public static function ineligible(string $reasonCode): self
    {
        return new self(false, $reasonCode, null);
    }

    public function isEligible(): bool
    {
        return $this->eligible;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public function getMatchedZoneCode(): ?string
    {
        return $this->matchedZoneCode;
    }
}
