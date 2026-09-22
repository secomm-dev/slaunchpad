<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * TASK-WAWNDS — quote-time parcel estimation could not produce a safe GHN rate estimate.
 * This is an ADAPTER/DATA limitation, NOT proof that GHN cannot transport the shipment
 * (distinct from a carrier hard-limit rejection — the sandbox Fee contract enforces no
 * weight/dimension caps at RATE; see the TASK-WAWNDS evidence matrix).
 *
 * Carries the structured failure reason the calculator maps into the CarrierRateOutcome
 * (e.g. `GHN_RATE_ESTIMATION_UNAVAILABLE`, `GHN_RATE_INVALID_PARCEL_DATA`).
 */
class GhnRateEstimationException extends LocalizedException
{
    public function __construct(
        private readonly string $reasonCode,
        \Magento\Framework\Phrase $phrase
    ) {
        parent::__construct($phrase);
    }

    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }
}
