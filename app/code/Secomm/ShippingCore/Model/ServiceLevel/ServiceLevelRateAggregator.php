<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ServiceLevel;

use Magento\Framework\Exception\LocalizedException;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateAggregateInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateAggregatorInterface;

/**
 * TASK-32ACTR (Phase E-SL1) — @see ServiceLevelRateAggregatorInterface.
 *
 * Status is the ONLY semantic read here: SUCCESS contributes its rate (under the caller's
 * carrier-code key), TECHNICAL_FAILURE raises the technical-failure signal, UNAVAILABLE is
 * skipped. Failure-reason strings are never inspected (diagnostic only — r1 rule); the external
 * fallback provider contract is not referenced anywhere in this service.
 */
final class ServiceLevelRateAggregator implements ServiceLevelRateAggregatorInterface
{
    public function __construct(
        private readonly ShippingServiceLevelRegistry $serviceLevelRegistry
    ) {
    }

    /**
     * @inheritDoc
     */
    public function aggregate(
        string $serviceLevelCode,
        array $outcomes,
        ?FallbackEligibilityInterface $fallbackEligibility = null
    ): ServiceLevelRateAggregateInterface {
        // Dynamic taxonomy validation — an unknown code is a configuration/programming error.
        $this->serviceLevelRegistry->assertKnown($serviceLevelCode);

        $successfulRates = [];
        $hasTechnicalFailure = false;
        foreach ($outcomes as $carrierCode => $outcome) {
            if (!is_string($carrierCode) || trim($carrierCode) === '') {
                throw new \LogicException(
                    'Rate outcomes must be keyed by a non-empty carrier code (identity is preserved into the aggregate).'
                );
            }
            if (!$outcome instanceof CarrierRateOutcomeInterface) {
                throw new \LogicException(
                    sprintf('Outcome for carrier "%s" must implement %s, got %s.', $carrierCode, CarrierRateOutcomeInterface::class, get_debug_type($outcome))
                );
            }

            switch ($outcome->getStatus()) {
                case CarrierRateOutcomeInterface::STATUS_SUCCESS:
                    $successfulRates[$carrierCode] = $outcome->getRate();
                    break;
                case CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE:
                    $hasTechnicalFailure = true;
                    break;
                case CarrierRateOutcomeInterface::STATUS_UNAVAILABLE:
                    break;
            }
        }

        return new ServiceLevelRateAggregate(
            $serviceLevelCode,
            $successfulRates,
            $hasTechnicalFailure,
            count($outcomes),
            $fallbackEligibility
        );
    }
}
