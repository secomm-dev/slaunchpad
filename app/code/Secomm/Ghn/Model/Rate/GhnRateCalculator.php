<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidAddressException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRateUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Address\Mapping\GhnLocation;
use Secomm\Ghn\Model\Address\Mapping\GhnMappingResolver;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\Ghn\Model\Capability\GhnRateCapabilityAdapter;
use Secomm\Ghn\Model\Client\GhnEndpoints;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Exception\GhnMappingNotFoundException;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRate;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-FMBBSD — THE GHN RATE operation (per-operation contract v5: requiredScheme(RATE) =
 * VN_ADMIN_PRE_2025, representation UNIT_ID → Stage-2 GHN district_id + ward_code → Calculate
 * Fee → CarrierRateOutcome).
 *
 * Stage 1 (canonical 2025→PRE_2025) belongs to ShippingCore/VietNamAddress — consumed here via
 * the runtime context builder + the PER-OPERATION handoff entry `handoffContextForOperation`
 * (the handoff's resolved address carries the canonical scheme_code + unit_code ONLY — the
 * runtime realization of the CanonicalResolutionSnapshot boundary; provider identity starts
 * below this line and never enters it). Stage 2 (canonical unit_code → GHN provider IDs) is
 * the APPROVED-mapping-only GhnMappingResolver: no fuzzy matching, no candidate guessing,
 * fail-closed (rate hidden, never approximated).
 *
 * Current official contract (developer.ghn.vn/en/docs, re-fetched 2026-09-18): Calculate Fee
 * is keyed by `service_type_id` ({2 total <20kg, 5 ≥20kg OR multi-parcel}) + required non-zero
 * gram `weight`; NO `service_id` anywhere (leadtime docs explicitly mark it no-effect) —
 * Available Services is therefore an OPTIONAL availability probe, deliberately not spent on the
 * checkout critical path (TASK-WAWNDS §27 decision: fee rejection suffices).
 * TASK-WAWNDS: type-5 quotes are RATE-SUPPORTED — the quote-time estimator serializes
 * `items[]` per-unit rows (sandbox-proven: type-5 fee REJECTS a root-weight-only payload and
 * `quantity=2` is NOT equivalent to two rows). Aggregate >50kg is NOT a fee rejection
 * (2×35kg = 200) and the documented 50kg/200cm caps are NOT fee-enforced (60kg single = 200,
 * 210cm = 200) — so NO local hard-limit pre-rejection exists at RATE: the provider remains the
 * final authority. The CREATE-side caps keep their own enforcement (unchanged).
 *
 * Outcome semantics (E-C1): auth/config/no-route/invalid parcel → UNAVAILABLE (never technical,
 * never fallback-triggering); timeout/5xx/429/malformed → TECHNICAL_FAILURE. Status decides
 * orchestration; the failure reason is diagnostic metadata only.
 */
class GhnRateCalculator
{
    private const OPERATION = 'calculate_fee';

    /** GHN quotes VND — constant provider fact of the fee API. */
    private const CURRENCY_VND = 'VND';

    public function __construct(
        private readonly Config $config,
        private readonly RuntimeAddressContextBuilderInterface $contextBuilder,
        private readonly CarrierAddressHandoffServiceInterface $handoffService,
        private readonly GhnAddressCapability $capability,
        private readonly GhnMappingResolver $mappingResolver,
        private readonly GhnApiClientInterface $apiClient,
        private readonly GhnLogger $logger
    ) {
    }

    /**
     * Quote one parcel for one destination. Exactly one outcome — GHN's current fee contract
     * has no speed tiers, so there is nothing to fan out over.
     *
     * @throws \LogicException never returned for handled provider failures — every typed
     *         provider failure is translated to UNAVAILABLE / TECHNICAL_FAILURE below
     */
    public function calculate(GhnRateQuery $query): CarrierRateOutcomeInterface
    {
        try {
            return $this->resolveAndQuote($query);
        } catch (GhnMappingNotFoundException) {
            // Stage-2 domain: canonical unit exists, no APPROVED GHN mapping (never CANONICAL_UNRESOLVED).
            return $this->unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING);
        } catch (
            ProviderTimeoutException
            | ProviderRemoteException
            | ProviderServiceUnavailableException $technicalException // 429/5xx = transport-level
        ) {
            $this->logger->call('GHN rate technical failure', ['reason' => $technicalException->getMessage()]);

            return CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR);
        } catch (
            ProviderAuthenticationException
            | ProviderInvalidAddressException
            | ProviderInvalidRequestException
            | ProviderRateUnavailableException $businessException
        ) {
            $this->logger->call('GHN rate unavailable', ['reason' => $businessException->getMessage()]);

            return $this->unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE);
        }
    }

    private function resolveAndQuote(GhnRateQuery $query): CarrierRateOutcomeInterface
    {
        // TASK-WAWNDS — SANDBOX-verified hard limits (150cm/dimension) gate BEFORE any
        // resolution or provider call on the standalone path too (duplicated with
        // quoteWithHandoff for the v10 contributor path — cheap, pure, idempotent).
        $violation = $query->getEstimate()->findHardLimitViolation();
        if ($violation !== null) {
            [$reason, $packageIndex, $dimension, $value, $limit] = $violation;
            $this->logger->call('GHN rate unavailable; package hard limit exceeded', [
                'reason' => $reason,
                'package_index' => $packageIndex,
                'dimension' => $dimension,
                'value_cm' => $value,
                'limit_cm' => $limit,
            ]);

            return $this->unavailable($reason);
        }

        $handoff = $this->handoffService->handoffContextForOperation(
            $this->contextBuilder->build(
                $query->getCountryId(),
                $query->getRegionId(),
                $query->getCityId(),
                $query->getLocalityName(),
                new GhnRateCapabilityAdapter($this->capability)
            ),
            $this->capability,
            ShippingAddressOperation::RATE,
            // TASK-MD2BD3 (v10) — AddressResolutionPolicy pass-through (thin adapter read):
            // selection/ranking happens in the shared handoff/selector — never in GHN.
            $this->config->getAddressResolutionPolicy(null)
        );

        return $this->quoteWithHandoff($query, $handoff);
    }

    /**
     * TASK-WAWNDS freeze (v10 §35) — Stage-2 + fee for an ALREADY-RESOLVED carrier-facing
     * handoff (the shape `CarrierRateExecutionService` supplies after eligibility/mode/policy).
     * GHN never repeats shared canonical resolution on this path — the handoff IS the resolved
     * PRE-2025 destination (PICK_PRIMARY results are indistinguishable from natural ones).
     *
     * $handoff may still be unresolved ONLY on the defensive/standalone path (this method is
     * also the tail of {@see resolveAndQuote}); unresolved defensive handling mirrors the
     * pre-v10 branch so the v10 contributor and the standalone path cannot diverge.
     */
    public function quoteWithHandoff(GhnRateQuery $query, CarrierAddressHandoffInterface $handoff): CarrierRateOutcomeInterface
    {
        // TASK-WAWNDS — SANDBOX-verified hard limits (150cm/dimension) gate BEFORE any
        // resolution or provider call (brief §12); weight caps are UNSETTLED and NOT enforced.
        $violation = $query->getEstimate()->findHardLimitViolation();
        if ($violation !== null) {
            [$reason, $packageIndex, $dimension, $value, $limit] = $violation;
            $this->logger->call('GHN rate unavailable; package hard limit exceeded', [
                'reason' => $reason,
                'package_index' => $packageIndex,
                'dimension' => $dimension,
                'value_cm' => $value,
                'limit_cm' => $limit,
            ]);

            return $this->unavailable($reason);
        }

        if ($query->getEstimate()->isEmpty() || $query->getEstimate()->getTotalWeightGrams() <= 0.0) {
            // Invalid parcel is a business rejection (architecture §26), decided without an API call.
            return $this->unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE);
        }

        if (!$handoff->isApplicable()) {
            return $this->unavailable($handoff->getFailureReason() ?? ShippingFailureReason::UNSUPPORTED_DESTINATION);
        }

        $resolved = $handoff->getResolvedAddress();
        if ($resolved === null) {
            // TASK-5XQXZK: distinguish the two structured unresolved states so the fallback
            // eligibility policy can judge them separately (AMBIGUOUS eligible, UNMAPPED not) —
            // the outcome STATUS stays UNAVAILABLE either way, never reclassified.
            $candidateCount = count($handoff->getCandidateCodes());
            if ($candidateCount > 1) {
                return $this->unavailable(ShippingFailureReason::CANONICAL_AMBIGUOUS);
            }
            if ($candidateCount === 0 && $handoff->getFailureReason() === null) {
                return $this->unavailable(ShippingFailureReason::CANONICAL_UNMAPPED);
            }

            return $this->unavailable($handoff->getFailureReason() ?? ShippingFailureReason::CANONICAL_UNRESOLVED);
        }

        return $this->quote($query, $resolved);
    }

    private function quote(GhnRateQuery $query, ResolvedShippingAddressInterface $resolved): CarrierRateOutcomeInterface
    {
        // RATE is contractually PRE_2025 (capability-required scheme); resolving against the
        // canonical scheme constant keeps a wrong-scheme handoff a clean mapping miss.
        $location = $this->mappingResolver->resolve(
            VnSchemes::VN_ADMIN_PRE_2025,
            (string) $resolved->getUnitCode()
        );

        if ($location->getDistrictId() === null || $location->getWardCode() === null) {
            return $this->unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING);
        }

        return CarrierRateOutcome::success(
            new CarrierRate($this->fetchFeeTotal($query, $location), self::CURRENCY_VND)
        );
    }

    /**
     * @return float quoted total fee in VND (>= 0 — zero is a valid promotional rate)
     * @throws ProviderAuthenticationException|ProviderInvalidAddressException|ProviderInvalidRequestException
     *         |ProviderRateUnavailableException|ProviderRemoteException|ProviderServiceUnavailableException
     *         |ProviderTimeoutException translated by the centralized client
     */
    private function fetchFeeTotal(GhnRateQuery $query, GhnLocation $location): float
    {
        $estimate = $query->getEstimate();
        $serviceTypeId = $estimate->getServiceTypeId();
        $payload = [
            'service_type_id' => $serviceTypeId,
            'weight' => (int) round($estimate->getTotalWeightGrams()),
            'to_district_id' => (int) $location->getDistrictId(),
            'to_ward_code' => (string) $location->getWardCode(),
        ];

        $originDistrictId = $this->config->getOriginDistrictId();
        if ($originDistrictId > 0) {
            $payload['from_district_id'] = $originDistrictId;
        }
        if ($serviceTypeId === QuoteParcelEstimate::SERVICE_TYPE_HEAVY_GOODS) {
            // TASK-WAWNDS — the type-5 fee REQUIRES the items[] payload (sandbox: a
            // root-weight-only payload is rejected with "Cân nặng không hợp lệ"). Serialize
            // PER-UNIT rows (quantity: 1 each): the sandbox proves quantity=N is NOT
            // equivalent to N rows (C2 ≠ C in the evidence matrix). Dimensions stay omitted
            // (the fee prices weight-only; per-item dims are optional — probe I3).
            $items = [];
            foreach ($estimate->getPackages() as $package) {
                $items[] = [
                    'name' => $package->getSourceSku(),
                    'quantity' => 1,
                    'weight' => (int) round($package->getWeightGrams()),
                ];
            }
            $payload['items'] = $items;
        }
        if (($query->getCollectionAmount() ?? 0) > 0) {
            $payload['cod_value'] = (int) $query->getCollectionAmount();
        }

        $this->logger->call('GHN rate estimate', [
            'service_type_id' => $serviceTypeId,
            'package_count' => $estimate->getPackageCount(),
            'total_weight_g' => (int) round($estimate->getTotalWeightGrams()),
        ]);

        $response = $this->apiClient->post(self::OPERATION, GhnEndpoints::CALCULATE_FEE, $payload);
        $total = $response['total'] ?? null;
        if (!is_numeric($total)) {
            // A fee response without a numeric total is a malformed technical answer — never
            // silently quoted as a zero-price rate.
            throw new ProviderRemoteException(__('GHN %1 response has no numeric total.', self::OPERATION));
        }

        return (float) $total;
    }

    private function unavailable(string $reason): CarrierRateOutcomeInterface
    {
        return CarrierRateOutcome::unavailable($reason);
    }
}
