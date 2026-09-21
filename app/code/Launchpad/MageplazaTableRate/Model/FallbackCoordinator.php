<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — per-method fallback evaluation + rate append.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model;

use Launchpad\MageplazaTableRate\Model\Exception\FallbackConfigurationException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityPolicyInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeCollectorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;

/**
 * Reads the outcomes Magento's carriers reported into the ShippingCore collector during THIS
 * rate-collection execution and, for every Mageplaza method flagged use_as_fallback, applies
 * the per-group gate (directive §9):
 *
 *   no participating member               → no fallback (an uninstalled/disabled member that
 *                                           Magento never called is NOT a failure);
 *   any participating member SUCCESS      → fallback suppressed;
 *   ≥1 approved-eligible outcome          → fallback price computed internally and appended;
 *   otherwise                             → no fallback.
 *
 * Eligibility is judged ONLY through the ShippingCore policy (status + structured reason — no
 * message parsing). A configured fallback price that matches no table row simply appends
 * nothing; misconfigured (deleted) methods fail-soft with a warning because a pricing source
 * must never break checkout. Membership changes visibility of NOBODY (directive §10).
 */
class FallbackCoordinator
{
    private const CARRIER_CODE = 'mptablerate';
    private const CARRIER_TITLE_CONFIG_PATH = 'carriers/mptablerate/title';

    public function __construct(
        private readonly MethodSettingsProvider $settingsProvider,
        private readonly CarrierRateOutcomeCollectorInterface $outcomeCollector,
        private readonly FallbackEligibilityPolicyInterface $eligibilityPolicy,
        private readonly MemberRatePolicy $memberRatePolicy,
        private readonly FallbackRateProvider $fallbackRateProvider,
        private readonly MethodFactory $rateMethodFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    public function appendFallbackRates(RateRequest $request, Result $result): void
    {
        // Cheap checks first — the collector read is in-memory; the settings map is one query
        // per request, memoized, but nothing below this guard should run when no carrier
        // reported anything.
        $outcomes = $this->outcomeCollector->getOutcomes();
        if ($outcomes === []) {
            return;
        }

        $fallbackMethodIds = $this->settingsProvider->getFallbackMethodIds();
        if ($fallbackMethodIds === []) {
            return;
        }

        $storeId = (int) ($request->getStoreId() ?? 0);
        $enabledMembers = $this->settingsProvider->getEnabledMembersMap();
        $nativeMethodIds = $this->nativeMethodIdsIn($result);

        foreach (array_keys($fallbackMethodIds) as $methodId) {
            // A method already present NATIVELY in this result (Mageplaza carrier ran and
            // priced it) IS the business option itself — appending a fallback copy would
            // duplicate the same shipping code. Native wins; the fallback copy only exists
            // for methods the native path did not produce (fallback-only, or carrier
            // suppressed by native gates).
            if (isset($nativeMethodIds[$methodId])) {
                continue;
            }

            // v10 §35: judge EACH member against its own frozen RateSourceMode /
            // AddressResolutionPolicy; any participating SUCCESS still suppresses the group.
            $suppressed = false;
            $hasEligibleMember = false;
            foreach ($enabledMembers[$methodId] ?? [] as $member) {
                $carrierCode = $member['carrier_code'];
                $outcome = $outcomes[$carrierCode][$member['method_code']] ?? null;
                if (!$outcome instanceof CarrierRateOutcomeInterface) {
                    // Not part of this Magento collection — never a synthetic failure.
                    continue;
                }

                if ($outcome->isSuccessful()) {
                    $suppressed = true;
                    break;
                }

                if ($this->isMemberEligible($carrierCode, $outcome, $storeId)) {
                    $hasEligibleMember = true;
                }
            }

            if ($suppressed || !$hasEligibleMember) {
                continue;
            }

            $rate = null;
            try {
                $rate = $this->fallbackRateProvider->calculate($methodId, $request);
            } catch (FallbackConfigurationException $exception) {
                $this->logger->warning(
                    'Launchpad TableRate fallback group misconfigured: ' . $exception->getMessage()
                );
                continue;
            }

            if ($rate === null) {
                continue;
            }

            $result->append($this->buildRateMethod($methodId, $rate, $storeId));
        }
    }

    /**
     * v10 §35 — the bridge owns NOTHING about eligibility semantics: it applies the member's
     * frozen RateSourceMode / AddressResolutionPolicy as a thin gate, then delegates the FACT
     * judgment to the frozen ShippingCore policy contract. No reason parsing beyond comparing
     * ShippingCore-owned constants, no candidate inspection, no carrier-specific rules.
     */
    private function isMemberEligible(string $carrierCode, CarrierRateOutcomeInterface $outcome, int $storeId): bool
    {
        $mode = $this->memberRatePolicy->rateSourceMode($carrierCode, $storeId);
        if ($mode === RateSourceMode::CARRIER_ONLY) {
            // Realtime-only member: its failures NEVER open a fallback.
            return false;
        }

        if ($mode === RateSourceMode::FALLBACK_ONLY) {
            // §35.6: fallback eligibility DIRECTLY — no synthetic TECHNICAL_FAILURE is required
            // (the carrier short-circuits before resolution/mapping/API and reports the skip).
            return true;
        }

        // CARRIER_WITH_FALLBACK (shared default): Address-policy gate for ambiguity —
        // STRICT means no ambiguity-driven fallback; the policy selection itself happened in
        // the shared handoff/selector, the bridge only reads the configuration value.
        if ($outcome->getFailureReason() === ShippingFailureReason::CANONICAL_AMBIGUOUS
            && $this->memberRatePolicy->addressResolutionPolicy($carrierCode, $storeId)
                === AddressResolutionPolicy::STRICT
        ) {
            return false;
        }

        return $this->eligibilityPolicy->isFallbackEligible($outcome->getStatus(), $outcome->getFailureReason());
    }

    /**
     * Mageplaza method ids already priced natively in this collection result.
     *
     * @return array<int, int> method_id => method_id
     */
    private function nativeMethodIdsIn(Result $result): array
    {
        $ids = [];
        foreach ($result->getAllRates() as $rate) {
            if ($rate instanceof Method
                && $rate->getCarrier() === self::CARRIER_CODE
                && (string) $rate->getMethod() !== ''
            ) {
                $ids[(int) $rate->getMethod()] = (int) $rate->getMethod();
            }
        }

        return $ids;
    }

    private function buildRateMethod(int $methodId, \Secomm\ShippingCore\Api\Fallback\FallbackRateInterface $rate, int $storeId): Method
    {
        /** @var Method $rateMethod */
        $rateMethod = $this->rateMethodFactory->create();
        $rateMethod->setCarrier(self::CARRIER_CODE);
        $rateMethod->setCarrierTitle(
            (string) $this->scopeConfig->getValue(
                self::CARRIER_TITLE_CONFIG_PATH,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        );
        $rateMethod->setMethod($methodId);
        $rateMethod->setMethodTitle($rate->getLabel());
        $rateMethod->setPrice($rate->getAmount());
        $rateMethod->setCost($rate->getAmount());

        return $rateMethod;
    }
}
