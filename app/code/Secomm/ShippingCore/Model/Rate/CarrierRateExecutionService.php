<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityPolicyInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackEligibility;
use Secomm\ShippingCore\Api\OriginInterface;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityEvaluatorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateExecutionDecisionInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateExecutionRequestInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateExecutionServiceInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;

/**
 * TASK-8MQHJX (Phase C) — @see CarrierRateExecutionServiceInterface.
 *
 * ONE runtime path, hard-ordered (v10 §35): eligibility → mode → [origin readiness] →
 * address policy (via the shared handoff service — address resolution is NEVER duplicated
 * here) → realtime contributor → contribution emission. Every delegation below reuses an
 * existing frozen contract; this class only sequences them.
 *
 * Fallback eligibility sources (existing frozen taxonomy, amended in this task):
 * TECHNICAL_FAILURE → TECHNICAL_FALLBACK; address-policy AMBIGUOUS blocks under
 * CARRIER_WITH_FALLBACK and FALLBACK_ONLY → LEGACY_ADDRESS_FALLBACK; realtime outcomes are
 * judged by the shared FallbackEligibilityPolicy — UNAVAILABLE+PROVIDER_MAPPING_MISSING maps
 * to INTEGRATION_LIMITATION (Phase C amendment), INVALID_CONFIGURATION (merchant-side) always
 * fails closed, real business/capability rejections never emit eligibility.
 */
final class CarrierRateExecutionService implements CarrierRateExecutionServiceInterface
{
    public function __construct(
        private readonly CarrierEligibilityEvaluatorInterface $eligibilityEvaluator,
        private readonly OriginProviderInterface $originProvider,
        private readonly CarrierAddressHandoffServiceInterface $handoffService,
        private readonly FallbackEligibilityPolicyInterface $fallbackEligibilityPolicy
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(
        CarrierRateExecutionRequestInterface $request
    ): CarrierRateExecutionDecisionInterface {
        // 1. CarrierEligibility FIRST — an ineligible carrier never reaches mode evaluation,
        //    policy evaluation, handoff, realtime, or fallback eligibility (v10 hard order).
        $eligibility = $this->eligibilityEvaluator->evaluate(
            $request->getDestinationScope(),
            $request->getAllowedZoneCodes(),
            $request->getDestinationProvinceCode(),
            $request->getDestinationWardCode()
        );
        if (!$eligibility->isEligible()) {
            return new CarrierRateExecutionDecision(
                shouldInvokeRealtime: false,
                reason: $eligibility->getReasonCode()
            );
        }

        // 2. RateSourceMode — only read AFTER eligibility passed. FALLBACK_ONLY skips origin
        //    readiness, address policy, provider mapping and realtime entirely; the eligible
        //    carrier keeps normal fallback orchestration (LEGACY_ADDRESS_FALLBACK source).
        if ($request->getRateSourceMode() === RateSourceMode::FALLBACK_ONLY) {
            return new CarrierRateExecutionDecision(
                shouldInvokeRealtime: false,
                fallbackEligibility: FallbackEligibility::legacyAddress()
            );
        }

        // 3. Origin readiness for realtime modes — ShippingCore never selects origin; the
        //    provider is a data snapshot and usability here is only the minimal shared gate.
        //    A realtime mode with an unresolved origin is NOT executable and is NEVER silently
        //    re-moded: CARRIER_ONLY fails closed; CARRIER_WITH_FALLBACK fails closed unless the
        //    shared policy (composition-owned) opts UNAVAILABLE+INVALID_CONFIGURATION in.
        $origin = $this->originProvider->resolve($request->getShippingContext());
        if (!$this->isOriginResolved($origin)) {
            return $this->originUnresolvedDecision($request);
        }

        // 4. AddressResolutionPolicy — realtime modes only, through the ONE shared handoff
        //    path (existing manager, request cache, PICK_PRIMARY selector, exception
        //    translation). This service never resolves addresses itself.
        $handoff = $this->handoffService->handoffContextForOperation(
            $request->getResolutionContext(),
            $request->getCapability(),
            ShippingAddressOperation::RATE,
            $request->getAddressResolutionPolicy()
        );

        if (!$handoff->isApplicable()) {
            // Non-VN destination (translated by the handoff service): no realtime, and the
            // shared policy judges UNSUPPORTED_DESTINATION not eligible (business rule).
            return new CarrierRateExecutionDecision(
                shouldInvokeRealtime: false,
                carrierFacingHandoff: $handoff,
                fallbackEligibility: $this->outcomeDrivenEligibility(
                    $request,
                    CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                    ShippingFailureReason::UNSUPPORTED_DESTINATION
                ),
                reason: ShippingFailureReason::UNSUPPORTED_DESTINATION
            );
        }

        if ($handoff->getResolvedAddress() === null && $this->isPolicyBlocked($handoff, $request)) {
            // STRICT / PICK_PRIMARY failures drop candidates inside the handoff service; the
            // default FALLBACK policy blocks the AMBIGUOUS shape (candidates retained).
            // CARRIER_ONLY: no realtime, no fallback. CARRIER_WITH_FALLBACK: no realtime, and
            // fallback eligibility per the frozen v10 map — STRICT emits NONE (§21), the
            // address-related blocks emit LEGACY_ADDRESS_FALLBACK (merchant legacy strategy).
            return new CarrierRateExecutionDecision(
                shouldInvokeRealtime: false,
                carrierFacingHandoff: $handoff,
                fallbackEligibility: $this->policyBlockedEligibility($request),
                reason: ShippingFailureReason::CANONICAL_UNRESOLVED
            );
        }

        // 5. Realtime contributor — the carrier receives ONLY the final carrier-facing
        //    handoff (PICK_PRIMARY results look identical to naturally-resolved ones; no
        //    candidates, no order, no ranking ever cross this boundary).
        $outcome = $request->getRealtimeContributor()->contribute(
            $request->getCarrierCode(),
            $handoff
        );

        if ($outcome->isSuccessful()) {
            // SUCCESS: realtime rate is the contribution; fallback eligibility is none
            // (suppression itself remains downstream ownership — nothing is dispatched here).
            return new CarrierRateExecutionDecision(
                shouldInvokeRealtime: true,
                realtimeOutcome: $outcome,
                carrierFacingHandoff: $handoff
            );
        }

        return new CarrierRateExecutionDecision(
            shouldInvokeRealtime: true,
            realtimeOutcome: $outcome,
            carrierFacingHandoff: $handoff,
            fallbackEligibility: $this->outcomeDrivenEligibility(
                $request,
                $outcome->getStatus(),
                $outcome->getFailureReason()
            ),
            reason: $outcome->getStatus()
        );
    }

    /**
     * Minimal shared origin gate: a data snapshot without a country is not a resolvable
     * origin. Richer usability rules (GHTK strict pickup, MSI source routing…) stay
     * carrier/composition-owned — ShippingCore never infers a new RateSourceMode here.
     */
    private function isOriginResolved(OriginInterface $origin): bool
    {
        return $origin->getCountryId() !== null && trim($origin->getCountryId()) !== '';
    }

    private function originUnresolvedDecision(
        CarrierRateExecutionRequestInterface $request
    ): CarrierRateExecutionDecisionInterface {
        // A missing shipping origin is merchant/store configuration, not an integration
        // limitation — the shared policy judges UNAVAILABLE+INVALID_CONFIGURATION (default
        // NOT eligible, and the source mapping keeps it fail-closed even under opt-in).
        return new CarrierRateExecutionDecision(
            shouldInvokeRealtime: false,
            fallbackEligibility: $this->outcomeDrivenEligibility(
                $request,
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                ShippingFailureReason::INVALID_CONFIGURATION
            ),
            reason: ShippingFailureReason::INVALID_CONFIGURATION
        );
    }

    /**
     * True when the address policy (not the carrier) blocks the realtime path for an
     * unresolved handoff. STRICT and PICK_PRIMARY never return an unresolved handoff for a
     * non-AMBIGUOUS state (their blocks only fire on AMBIGUOUS outcomes inside the handoff
     * service), so an unresolved handoff under those policies IS the block. The default
     * FALLBACK policy blocks exactly the AMBIGUOUS shape — candidates retained on the handoff.
     */
    private function isPolicyBlocked(
        CarrierAddressHandoffInterface $handoff,
        CarrierRateExecutionRequestInterface $request
    ): bool {
        $policy = $request->getAddressResolutionPolicy();

        return $policy === AddressResolutionPolicy::STRICT
            || $policy === AddressResolutionPolicy::PICK_PRIMARY
            || $handoff->getCandidateCodes() !== [];
    }

    private function policyBlockedEligibility(
        CarrierRateExecutionRequestInterface $request
    ): FallbackEligibilityInterface {
        if ($request->getRateSourceMode() !== RateSourceMode::CARRIER_WITH_FALLBACK) {
            return FallbackEligibility::none();
        }

        if ($request->getAddressResolutionPolicy() === AddressResolutionPolicy::STRICT) {
            // STRICT: ambiguity must never drive fallback — no contribution either way (§21).
            return FallbackEligibility::none();
        }

        return FallbackEligibility::legacyAddress();
    }

    /**
     * Judge one outcome-shaped fact through the SHARED eligibility policy and map the result
     * into the existing source taxonomy (TASK-8MQHJX Phase C amendment added the third
     * source):
     *  - TECHNICAL_FAILURE → TECHNICAL_FALLBACK;
     *  - UNAVAILABLE + CANONICAL_AMBIGUOUS → LEGACY_ADDRESS_FALLBACK (address-policy fallback);
     *  - UNAVAILABLE + PROVIDER_MAPPING_MISSING → INTEGRATION_LIMITATION (v10 §35.5 frozen
     *    case; the outcome STAYS UNAVAILABLE — never reclassified to TECHNICAL_FAILURE);
     *  - INVALID_CONFIGURATION → NONE, deliberately: the frozen reason contract defines it as
     *    merchant-side carrier configuration, which must fail closed. §35.5's configurable
     *    auth/config seam needs its own provider-auth reason + mandatory high-severity warning
     *    seam before any configuration failure may degrade safely — the distinction is
     *    preserved, never broadened to make tests pass.
     *  - everything else (SERVICE_UNAVAILABLE, CANONICAL_UNMAPPED, UNSUPPORTED_DESTINATION,
     *    carrier-owned codes) → NONE: real business/capability rejections are never masked.
     */
    private function outcomeDrivenEligibility(
        CarrierRateExecutionRequestInterface $request,
        string $status,
        ?string $failureReason
    ): FallbackEligibilityInterface {
        if ($request->getRateSourceMode() !== RateSourceMode::CARRIER_WITH_FALLBACK) {
            return FallbackEligibility::none();
        }

        if (!$this->fallbackEligibilityPolicy->isFallbackEligible($status, $failureReason)) {
            return FallbackEligibility::none();
        }

        if ($status === CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE) {
            return FallbackEligibility::technical();
        }

        if ($failureReason === ShippingFailureReason::CANONICAL_AMBIGUOUS) {
            return FallbackEligibility::legacyAddress();
        }

        if ($failureReason === ShippingFailureReason::PROVIDER_MAPPING_MISSING) {
            return FallbackEligibility::integrationLimitation();
        }

        // Policy-eligible but merchant-side configuration (or any non-representable reason):
        // fail closed — the eligibility decision never broadens beyond the frozen taxonomy.
        return FallbackEligibility::none();
    }
}
