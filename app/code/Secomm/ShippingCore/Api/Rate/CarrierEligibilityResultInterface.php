<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

/**
 * TASK-8MQHJX (architecture v10 §35.1) — carrier eligibility result per destination evaluation.
 *
 * "Eligible" = the carrier may proceed to the realtime RATE path for this destination.
 * "Ineligible" = the carrier contributes NEITHER realtime rates NOR fallback eligibility.
 */
interface CarrierEligibilityResultInterface
{
    public const REASON_ELIGIBLE = 'ELIGIBLE';
    public const REASON_DESTINATION_NOT_IN_SCOPE = 'DESTINATION_NOT_IN_SCOPE';

    public function isEligible(): bool;

    /** REASON_ELIGIBLE | REASON_DESTINATION_NOT_IN_SCOPE. */
    public function getReasonCode(): ?string;

    /** Matched zone code when eligible via SELECTED_ZONES; null for ALL. */
    public function getMatchedZoneCode(): ?string;
}
