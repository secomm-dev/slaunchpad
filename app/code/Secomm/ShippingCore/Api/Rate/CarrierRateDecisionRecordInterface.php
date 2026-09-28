<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;

/**
 * TASK-SEC-D-transport — ONE atomic decision record per (carrier_code, method_code):
 * the reported outcome AND the fallback-eligibility decision of the SAME execution, with an
 * EXPLICIT transport-presence flag. Three states are cleanly distinguishable:
 *
 *   transport absent  (hasEligibilityTransport() === false) → legacy compatibility policy
 *   transport present + NONE (eligible sources empty)       → fail closed, no legacy re-judge
 *   transport present + sources                              → consume the transported state
 */
interface CarrierRateDecisionRecordInterface
{
    public function getCarrierCode(): string;

    public function getMethodCode(): string;

    public function getOutcome(): CarrierRateOutcomeInterface;

    public function hasEligibilityTransport(): bool;

    /**
     * The transported eligibility when hasEligibilityTransport(); null otherwise. An empty
     * transported state is FallbackEligibility with zero sources — NEVER null.
     */
    public function getFallbackEligibility(): ?FallbackEligibilityInterface;
}
