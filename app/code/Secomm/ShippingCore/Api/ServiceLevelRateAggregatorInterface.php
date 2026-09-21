<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateAggregateInterface;

/**
 * TASK-32ACTR (Phase E-SL1) — groups ALREADY-CLASSIFIED realtime carrier outcomes for ONE
 * dynamic service level into a small aggregate that answers two questions for the future
 * fallback-eligibility step (E-SL2): are there usable realtime rates, and did any carrier fail
 * technically?
 *
 * LEAN by charter — this is aggregation, not a routing engine: no carrier selection/ranking, no
 * fallback pricing or triggering, no reason-string inspection (top-level outcome status is the
 * only semantic), no cut-off/routing evaluation. The service-level code is validated
 * dynamically against ShippingServiceLevelRegistry — ShippingCore embeds no business taxonomy.
 *
 * Carrier membership (which outcomes belong to which service-level bucket) is the CALLER's
 * knowledge; the bucket is supplied per call.
 */
interface ServiceLevelRateAggregatorInterface
{
    /**
     * @param string $serviceLevelCode a REGISTERED service-level machine code
     * @param array<string, CarrierRateOutcomeInterface> $outcomes keyed by carrier code
     *        (identity must be preserved for later checkout/order handoff); input order is kept
     * @throws \Magento\Framework\Exception\LocalizedException unknown service-level code
     *         (explicit configuration error)
     * @throws \LogicException a non-string/empty carrier key or a non-outcome entry
     */
    public function aggregate(
        string $serviceLevelCode,
        array $outcomes,
        ?\Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface $fallbackEligibility = null
    ): ServiceLevelRateAggregateInterface;
}
