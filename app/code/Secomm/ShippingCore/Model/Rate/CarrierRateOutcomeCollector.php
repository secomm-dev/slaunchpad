<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — request-scoped outcome collector implementation.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateDecisionRecordInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeCollectorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;

/**
 * Shared-per-request in-memory buffer (no shared="false", no session, no DB, no cache — state
 * lives exactly as long as the PHP request and is bracketed per rate-collection execution).
 *
 * Isolation contract: beginCollection() discards leftovers, so two sequential collections in the
 * same HTTP request (multi-address checkout, address re-estimate) never see each other's
 * outcomes. Orphan record() calls (no open bracket) are dropped with a debug log — safe, never
 * thrown into the carrier path.
 */
class CarrierRateOutcomeCollector implements CarrierRateOutcomeCollectorInterface
{
    /** @var array<string, array<string, CarrierRateOutcomeInterface>> */
    private array $outcomes = [];

    /** @var array<string, array<string, CarrierRateDecisionRecordInterface>> */
    private array $decisions = [];

    private bool $active = false;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function beginCollection(): void
    {
        $this->outcomes = [];
        $this->decisions = [];
        $this->active = true;
    }

    public function record(
        string $carrierCode,
        string $methodCode,
        CarrierRateOutcomeInterface $outcome
    ): void {
        if ($carrierCode === '' || $methodCode === '') {
            throw new \InvalidArgumentException(
                'Carrier outcome identity requires non-empty carrier_code and method_code.'
            );
        }

        if (!$this->active) {
            // Orphan report: carrier ran outside a bracketed collection. Drop silently for the
            // caller, keep a breadcrumb for diagnostics.
            $this->logger->debug(
                'ShippingCore outcome collector dropped orphan report (no open collection): '
                . '{carrier}/{method} {status}',
                ['carrier' => $carrierCode, 'method' => $methodCode, 'status' => $outcome->getStatus()]
            );

            return;
        }

        $this->outcomes[$carrierCode][$methodCode] = $outcome;

        // TASK-SEC-D-transport: legacy reports appear in the decision view WITHOUT transport —
        // but a TRANSPORTED record is authoritative and is never downgraded by a legacy
        // overwrite of the same pair.
        $existing = $this->decisions[$carrierCode][$methodCode] ?? null;
        if ($existing !== null && $existing->hasEligibilityTransport()) {
            $this->logger->debug(
                'ShippingCore decision collector: legacy overwrite ignored for transported record {carrier}/{method}.',
                ['carrier' => $carrierCode, 'method' => $methodCode]
            );

            return;
        }
        $this->decisions[$carrierCode][$methodCode] = CarrierRateDecisionRecord::legacyOutcomeOnly(
            $carrierCode,
            $methodCode,
            $outcome
        );
    }

    public function recordDecision(
        string $carrierCode,
        string $methodCode,
        CarrierRateOutcomeInterface $outcome,
        FallbackEligibilityInterface $fallbackEligibility
    ): void {
        if ($carrierCode === '' || $methodCode === '') {
            throw new \InvalidArgumentException(
                'Carrier outcome identity requires non-empty carrier_code and method_code.'
            );
        }
        if (!$this->active) {
            $this->logger->debug(
                'ShippingCore decision collector dropped orphan report (no open collection): {carrier}/{method} {status}',
                ['carrier' => $carrierCode, 'method' => $methodCode, 'status' => $outcome->getStatus()]
            );

            return;
        }

        $existing = $this->decisions[$carrierCode][$methodCode] ?? null;
        if ($existing === null) {
            $this->decisions[$carrierCode][$methodCode] = CarrierRateDecisionRecord::withTransport(
                $carrierCode,
                $methodCode,
                $outcome,
                $fallbackEligibility
            );
            $this->outcomes[$carrierCode][$methodCode] = $outcome;

            return;
        }

        // Deterministic merge (Phase 2 contract — never last-wins):
        //  1. SUCCESS is terminal — a later non-success cannot override it.
        //  2. SUCCESS after a non-success overrides (success wins).
        //  3. Two non-success of the same status → first wins (idempotent).
        //  4. Conflicting non-success statuses → first status wins + diagnostic.
        //  Eligibility sources of transported records are MERGED (union of flags).
        $prev = $existing->getOutcome();
        if ($existing->hasEligibilityTransport() === false) {
            // First transported record upgrades the legacy record.
            $this->decisions[$carrierCode][$methodCode] = CarrierRateDecisionRecord::withTransport(
                $carrierCode,
                $methodCode,
                $outcome,
                $fallbackEligibility
            );
            $this->outcomes[$carrierCode][$methodCode] = $outcome;

            return;
        }

        if ($prev->isSuccessful()) {
            $this->logger->debug(
                'ShippingCore decision collector: SUCCESS is terminal — later {status} ignored for {carrier}/{method}.',
                ['status' => $outcome->getStatus(), 'carrier' => $carrierCode, 'method' => $methodCode]
            );

            return;
        }

        if ($outcome->isSuccessful()) {
            // Success wins; success carries no fallback eligibility (NONE).
            $this->logger->debug(
                'ShippingCore decision collector: SUCCESS overrides earlier {status} for {carrier}/{method}.',
                ['status' => $prev->getStatus(), 'carrier' => $carrierCode, 'method' => $methodCode]
            );
            $this->decisions[$carrierCode][$methodCode] = CarrierRateDecisionRecord::withTransport(
                $carrierCode,
                $methodCode,
                $outcome,
                $fallbackEligibility
            );
            $this->outcomes[$carrierCode][$methodCode] = $outcome;

            return;
        }

        $sameDecision = $this->sameDecisionIdentity($existing, $outcome, $fallbackEligibility);
        if ($sameDecision) {
            $this->logger->debug(
                'ShippingCore decision collector: identical duplicate for {carrier}/{method} — first kept, eligibility sources merged.',
                ['carrier' => $carrierCode, 'method' => $methodCode]
            );
            $this->decisions[$carrierCode][$methodCode] = CarrierRateDecisionRecord::withTransport(
                $carrierCode,
                $methodCode,
                $prev,
                $this->mergeEligibility($existing->getFallbackEligibility(), $fallbackEligibility)
            );

            return;
        }

        // Conflicting non-success decisions: the FIRST record wins WHOLE — outcome and
        // eligibility always belong to the same execution; never synthesize a mixed record
        // (INVALID_CONFIGURATION outcome + TECHNICAL_FALLBACK eligibility = impossible state).
        $this->logger->debug(
            'ShippingCore decision collector: conflicting non-success statuses for {carrier}/{method} — first ({first}) kept, later {later} ignored.',
            ['carrier' => $carrierCode, 'method' => $methodCode, 'first' => $prev->getStatus(), 'later' => $outcome->getStatus()]
        );
        // (first record kept as-is — no eligibility union across executions)
    }

    /**
     * TASK-SEC-D r4 — FULL decision identity: status + failure reason + the complete
     * fallback-eligibility source set (order-insensitive) + rate amount/currency for
     * successes. Only an identical record is idempotent; anything else is a conflict and
     * the first record stays whole (no eligibility union across executions).
     */
    private function sameDecisionIdentity(
        CarrierRateDecisionRecordInterface $existing,
        CarrierRateOutcomeInterface $outcome,
        FallbackEligibilityInterface $eligibility
    ): bool {
        $prev = $existing->getOutcome();
        if ($prev->getStatus() !== $outcome->getStatus()
            || $prev->getFailureReason() !== $outcome->getFailureReason()
            || $existing->hasEligibilityTransport() === false) {
            return false;
        }

        $prevSources = $existing->getFallbackEligibility()?->getSources() ?? [];
        $nextSources = $eligibility->getSources();
        sort($prevSources);
        sort($nextSources);
        if ($prevSources !== $nextSources) {
            return false;
        }

        $prevRate = $prev->getRate();
        $nextRate = $outcome->getRate();
        if (($prevRate === null) !== ($nextRate === null)) {
            return false;
        }
        if ($prevRate !== null
            && ((float) $prevRate->getAmount() !== (float) $nextRate->getAmount()
                || $prevRate->getCurrency() !== $nextRate->getCurrency())) {
            return false;
        }

        return true;
    }

    private function mergeEligibility(
        ?FallbackEligibilityInterface $a,
        FallbackEligibilityInterface $b
    ): FallbackEligibilityInterface {
        if ($a === null) {
            return $b;
        }

        return new \Secomm\ShippingCore\Model\Fallback\FallbackEligibility(
            $a->hasTechnicalFallbackEligibility() || $b->hasTechnicalFallbackEligibility(),
            $a->hasLegacyAddressFallbackEligibility() || $b->hasLegacyAddressFallbackEligibility(),
            $a->hasIntegrationLimitationEligibility() || $b->hasIntegrationLimitationEligibility()
        );
    }

    public function getDecisionRecords(): array
    {
        return $this->active ? $this->decisions : [];
    }

    public function getOutcomes(): array
    {
        return $this->active ? $this->outcomes : [];
    }

    public function endCollection(): void
    {
        $this->outcomes = [];
        $this->decisions = [];
        $this->active = false;
    }
}
