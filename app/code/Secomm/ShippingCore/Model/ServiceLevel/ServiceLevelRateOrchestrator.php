<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ServiceLevel;

use Magento\Framework\Exception\LocalizedException;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackPolicyInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackRateRequestInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateAggregateInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateDecisionInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateOrchestratorInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackRateProviderPool;

/**
 * TASK-M3ME32 (Phase E-SL2) — @see ServiceLevelRateOrchestratorInterface.
 *
 * Reads ONLY aggregate semantics (hasSuccessfulRate/hasTechnicalFailure), the explicit fallback
 * eligibility state (TECHNICAL_FALLBACK | LEGACY_ADDRESS_FALLBACK | INTEGRATION_LIMITATION —
 * caller-supplied) and the service-level enabled/policy state — failure-reason strings are never
 * inspected (diagnostic only). The
 * fallback provider is invoked at most once, and ONLY when the level is enabled, no realtime
 * success exists, an eligible failure occurred, the policy allows it, and exactly one provider
 * is registered (multiple providers are a configuration ambiguity that fails fast instead of
 * being silently routed).
 */
final class ServiceLevelRateOrchestrator implements ServiceLevelRateOrchestratorInterface
{
    public function __construct(
        private readonly ShippingServiceLevelRegistry $serviceLevelRegistry,
        private readonly FallbackPolicyInterface $fallbackPolicy,
        private readonly FallbackRateProviderPool $fallbackRateProviderPool
    ) {
    }

    /**
     * @inheritDoc
     */
    public function decide(
        string $serviceLevelCode,
        ServiceLevelRateAggregateInterface $aggregate,
        FallbackRateRequestInterface $fallbackRequest,
        ?FallbackEligibilityInterface $fallbackEligibility = null
    ): ServiceLevelRateDecisionInterface {
        $definition = $this->serviceLevelRegistry->getByCode($serviceLevelCode);
        if ($definition === null) {
            // Unknown taxonomy is an explicit configuration error, never silently aggregated.
            throw new LocalizedException(
                __('Unknown shipping service level code "%1" — it is not registered by any composition module.', $serviceLevelCode)
            );
        }
        if ($aggregate->getServiceLevelCode() !== $serviceLevelCode) {
            throw new \LogicException(
                sprintf(
                    'Aggregate belongs to service level "%s", cannot decide for "%s".',
                    $aggregate->getServiceLevelCode(),
                    $serviceLevelCode
                )
            );
        }

        // `enabled` is the service-level exposure policy: a disabled level is unavailable even
        // when the aggregate carries successful rates (the aggregate itself is not modified).
        if (!$definition->isEnabled()) {
            return ServiceLevelRateDecision::unavailable($serviceLevelCode);
        }

        // Any realtime SUCCESS wins — fallback is never requested, never even considered.
        if ($aggregate->hasSuccessfulRate()) {
            return ServiceLevelRateDecision::realtime($serviceLevelCode, $aggregate->getSuccessfulRates());
        }

        // No realtime rate and no eligible failure: plain business unavailability — UNAVAILABLE
        // alone must never produce emergency pricing.
        // Fallback eligibility (architecture v5 + v10 §35.5 amendment) — EXACTLY three explicit
        // sources: TECHNICAL_FALLBACK (aggregate technical failure), LEGACY_ADDRESS_FALLBACK
        // (merchant legacy RATE strategy, caller-supplied) and INTEGRATION_LIMITATION
        // (TASK-8MQHJX Phase C amendment: provider mapping missing etc., caller-supplied).
        // Reason strings are never parsed; UNAVAILABLE stays UNAVAILABLE.
        $technicalEligible = $aggregate->hasTechnicalFailure();
        $legacyEligible = $fallbackEligibility?->hasLegacyAddressFallbackEligibility() ?? false;
        $integrationEligible = $fallbackEligibility?->hasIntegrationLimitationEligibility() ?? false;
        if (!$technicalEligible && !$legacyEligible && !$integrationEligible) {
            // No realtime rate and no eligibility: plain business unavailability - UNAVAILABLE
            // alone must never produce emergency pricing.
            return ServiceLevelRateDecision::unavailable($serviceLevelCode);
        }

        if (!$this->fallbackPolicy->isEnabled($serviceLevelCode)) {
            return ServiceLevelRateDecision::unavailable($serviceLevelCode);
        }

        $providers = $this->fallbackRateProviderPool->getProviders();
        if ($providers === []) {
            // Zero providers is a normal configuration; fallback is simply not possible.
            return ServiceLevelRateDecision::unavailable($serviceLevelCode);
        }
        if (count($providers) > 1) {
            // Configuration ambiguity — fail fast instead of inventing a provider-routing rule.
            throw new \LogicException(
                'More than one fallback rate provider is registered; ShippingCore does not route between providers.'
            );
        }

        $fallbackRate = $providers[0]->getRate($serviceLevelCode, $fallbackRequest);

        return $fallbackRate !== null
            ? ServiceLevelRateDecision::fallback($serviceLevelCode, $fallbackRate)
            : ServiceLevelRateDecision::unavailable($serviceLevelCode);
    }
}
