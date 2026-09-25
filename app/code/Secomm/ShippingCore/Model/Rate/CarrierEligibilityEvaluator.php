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
use Secomm\ShippingCore\Api\Address\DestinationScope;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityEvaluatorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityResultInterface;
use Secomm\ShippingCore\Model\Rate\CarrierEligibilityResult;

/**
 * TASK-8MQHJX Phase B (architecture v10 §35.1) — evaluates carrier eligibility by matching
 * canonical destination against allowed zones via the zone matcher;
 * @see CarrierEligibilityEvaluatorInterface.
 *
 * ALL_EXCEPT_SELECTED_ZONES (TASK-R8WR1R) inverts the SELECTED_ZONES outcome: a valid enabled
 * matching zone EXCLUDES the carrier; otherwise (none matches, empty list, unknown/disabled
 * references only) the carrier is eligible — identical to ALL. Unknown/disabled references never
 * exclude on their own; stale-config diagnostics live in the config reader seam (§16/§22).
 *
 * Unknown scope (TASK-R8WR1R r2 enforcement point): an unrecognized value — e.g. an invalid
 * persisted scope passed through verbatim by CarrierDestinationScopeConfig — is INELIGIBLE for
 * every destination, deterministically, regardless of the zone list. It is never coerced and
 * never eligible: invalid coverage configuration fails closed.
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
        if ($destinationScope === DestinationScope::ALL) {
            return CarrierEligibilityResult::eligible();
        }

        if ($destinationScope === DestinationScope::SELECTED_ZONES) {
            return $this->evaluateSelectedZones($allowedZoneCodes, $destinationProvinceCode, $destinationWardCode);
        }

        if ($destinationScope === DestinationScope::ALL_EXCEPT_SELECTED_ZONES) {
            return $this->evaluateAllExceptSelectedZones(
                $allowedZoneCodes,
                $destinationProvinceCode,
                $destinationWardCode
            );
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

    /**
     * Empty list → eligible (equivalent to ALL). Unknown/disabled zone codes are skipped —
     * they must not exclude on their own. First valid enabled matching zone excludes.
     */
    private function evaluateAllExceptSelectedZones(
        array $excludedZoneCodes,
        string $destinationProvinceCode,
        ?string $destinationWardCode
    ): CarrierEligibilityResultInterface {
        foreach ($excludedZoneCodes as $zoneCode) {
            $zone = $this->zoneRegistry->getByCode($zoneCode);
            if ($zone === null || !$zone->isEnabled()) {
                continue;
            }

            if ($this->zoneMatcher->matches($zone, $destinationProvinceCode, $destinationWardCode)) {
                return CarrierEligibilityResult::ineligible(
                    CarrierEligibilityResultInterface::REASON_DESTINATION_NOT_IN_SCOPE
                );
            }
        }

        return CarrierEligibilityResult::eligible();
    }
}
