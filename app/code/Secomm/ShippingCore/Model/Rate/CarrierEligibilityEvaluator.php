<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Address\CanonicalZoneMatcherInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityEvaluatorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityResultInterface;
use Secomm\ShippingCore\Model\Rate\CarrierEligibilityResult;

/**
 * TASK-8MQHJX Phase B (architecture v10 §35.1) — evaluates carrier eligibility by matching
 * canonical destination against allowed zones via the zone matcher;
 * @see CarrierEligibilityEvaluatorInterface.
 *
 * Eligibility is destination-scope-only: KHÔNG quyết RateSourceMode, AddressResolutionPolicy,
 * fallback dispatch, provider mapping, carrier API call. Ineligible carrier contribution = 0
 * (cả realtime lẫn fallback).
 */
final class CarrierEligibilityEvaluator implements CarrierEligibilityEvaluatorInterface
{
    public function __construct(
        private readonly CanonicalZoneMatcherInterface $zoneMatcher,
        private readonly CanonicalZoneRegistryInterface $zoneRegistry
    ) {
    }

    /**
     * @inheritDoc
     */
    public function evaluate(
        string $destinationScope,
        array $allowedZoneCodes,
        string $destinationProvinceCode,
        ?string $destinationWardCode
    ): CarrierEligibilityResultInterface {
        if ($destinationScope === \Secomm\ShippingCore\Api\Address\DestinationScope::ALL) {
            return CarrierEligibilityResult::eligible();
        }

        if ($destinationScope === \Secomm\ShippingCore\Api\Address\DestinationScope::SELECTED_ZONES) {
            return $this->evaluateSelectedZones($allowedZoneCodes, $destinationProvinceCode, $destinationWardCode);
        }

        return CarrierEligibilityResult::ineligible(CarrierEligibilityResultInterface::REASON_DESTINATION_NOT_IN_SCOPE);
    }

    private function evaluateSelectedZones(
        array $allowedZoneCodes,
        string $destinationProvinceCode,
        ?string $destinationWardCode
    ): CarrierEligibilityResultInterface {
        foreach ($allowedZoneCodes as $zoneCode) {
            $zone = $this->zoneRegistry->getByCode($zoneCode);
            if ($zone === null || !$zone->isEnabled()) {
                continue;
            }

            if ($this->zoneMatcher->matches($zone, $destinationProvinceCode, $destinationWardCode)) {
                return CarrierEligibilityResult::eligible($zoneCode);
            }
        }

        return CarrierEligibilityResult::ineligible(CarrierEligibilityResultInterface::REASON_DESTINATION_NOT_IN_SCOPE);
    }
}
