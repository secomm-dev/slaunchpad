<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidAddressException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRateUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Exception\GhnMappingNotFoundException;
use Secomm\Ghn\Model\Exception\GhnRateEstimationException;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Api\Rate\RealtimeCarrierRateContributorInterface;

/**
 * TASK-WAWNDS freeze (v10 §35) — Secomm_Ghn's realtime RATE contribution for the shared
 * `CarrierRateExecutionService`. The execution service invokes this ONLY after eligibility,
 * RateSourceMode and AddressResolutionPolicy passed — the handoff argument IS the final
 * carrier-facing PRE-2025 destination (PICK_PRIMARY output indistinguishable from a natural
 * resolution; GHN never sees candidates/ranking/eligibility internals).
 *
 * This is a pure adapter: canonical resolution NEVER happens here (no handoff-service call,
 * no context builder) — the Stage-1 work belongs to ShippingCore. The GHN-owned work is the
 * quote-time estimate → GHN provider mapping → Fee API → outcome normalization.
 *
 * Instance is created per collectRates() through {@see RealtimeRateContributorFactory} with
 * the CURRENT RateRequest closed over (weight/units/store) — the execution request cannot
 * carry Magento rate internals (shared seam stays provider-free).
 */
class RealtimeRateContributor implements RealtimeCarrierRateContributorInterface
{
    /** Merchant-side carrier configuration unusable for rating (weight unit etc.) — fail closed. */
    public const REASON_INVALID_CONFIGURATION = 'INVALID_CONFIGURATION';

    public function __construct(
        private readonly GhnRateCalculator $rateCalculator,
        private readonly GhnRateRequestMapper $requestMapper,
        private readonly GhnLogger $logger,
        private readonly ?RateRequest $request = null
    ) {
    }

    /**
     * Defensive boundary: the shared execution service dispatches only its own carrier code;
     * anything else is a wiring mistake, not a rate result.
     */
    public function contribute(
        string $carrierCode,
        CarrierAddressHandoffInterface $handoff
    ): CarrierRateOutcomeInterface {
        if ($carrierCode !== 'secomm_ghn') {
            throw new \LogicException(sprintf('GHN contributor invoked for carrier "%s".', $carrierCode));
        }
        if ($this->request === null) {
            throw new \LogicException('GHN contributor was created without a rate request.');
        }

        try {
            $query = $this->requestMapper->map($this->request);
        } catch (GhnRateEstimationException $estimationException) {
            // Adapter/data limitation — surfaced verbatim; the execution service maps it
            // through the shared fallback-eligibility policy (never GHN deciding fallback).
            $this->logger->call('GHN rate unavailable; no rate.', [
                'status' => CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                'reason' => $estimationException->getReasonCode(),
                'detail' => $estimationException->getMessage(),
            ]);

            return CarrierRateOutcome::unavailable($estimationException->getReasonCode());
        } catch (LocalizedException $configurationException) {
            // Store/unit configuration failure (e.g. unusable weight unit from the
            // StoreWeightConverter) — fail closed as merchant-side INVALID_CONFIGURATION,
            // mirroring the carrier boundary mapping (never a guessed rate).
            $this->logger->warning('GHN rate unavailable; store configuration unusable.', [
                'reason' => self::REASON_INVALID_CONFIGURATION,
                'detail' => $configurationException->getMessage(),
            ]);

            return CarrierRateOutcome::unavailable(self::REASON_INVALID_CONFIGURATION);
        }

        // Defensive only: upstream, the execution service already blocked non-applicable /
        // unresolved handoffs before the realtime path. This branch exists so the contributor
        // stays correct even if invoked outside the frozen execution flow.
        if (!$handoff->isApplicable() || $handoff->getResolvedAddress() === null) {
            $this->logger->call('GHN rate unavailable; unresolved carrier-facing handoff (defensive).', [
                'reason' => $handoff->getFailureReason() ?? ShippingFailureReason::CANONICAL_UNRESOLVED,
            ]);

            return CarrierRateOutcome::unavailable(
                $handoff->getFailureReason() ?? ShippingFailureReason::CANONICAL_UNRESOLVED
            );
        }

        // TASK-WAWNDS correctness pass — the seam contract is OUTCOME-BASED: every supported
        // provider/domain exception translates to a CarrierRateOutcome here (exact parity
        // with GhnRateCalculator::calculate()); Programming defects (TypeError, …) still
        // propagate fail-loud — no Throwable catch.
        try {
            return $this->rateCalculator->quoteWithHandoff($query, $handoff);
        } catch (GhnMappingNotFoundException $mappingException) {
            // Stage-2 domain: canonical unit exists, no APPROVED GHN mapping — integration
            // limitation; the execution service maps this reason to INTEGRATION_LIMITATION
            // (outcome STAYS UNAVAILABLE, never reclassified).
            $this->logger->call('GHN rate unavailable; no rate.', [
                'status' => CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                'reason' => ShippingFailureReason::PROVIDER_MAPPING_MISSING,
                'detail' => $mappingException->getMessage(),
            ]);

            return CarrierRateOutcome::unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING);
        } catch (
            ProviderTimeoutException
            | ProviderRemoteException
            | ProviderServiceUnavailableException $technicalException
        ) {
            // Temporary transport/provider outage (429/5xx included — the client classifies
            // them together): TECHNICAL_FAILURE keeps the shared fallback path reachable.
            $this->logger->call('GHN rate technical failure', [
                'reason' => ShippingFailureReason::TECHNICAL_ERROR,
                'exception_class' => $technicalException::class,
                'detail' => $technicalException->getMessage(),
            ]);

            return CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR);
        } catch (
            ProviderAuthenticationException
            | ProviderInvalidAddressException
            | ProviderInvalidRequestException
            | ProviderRateUnavailableException $businessException
        ) {
            // Real business/auth/request rejection — UNAVAILABLE, never fallback-triggering.
            $this->logger->call('GHN rate unavailable', [
                'reason' => ShippingFailureReason::SERVICE_UNAVAILABLE,
                'exception_class' => $businessException::class,
                'detail' => $businessException->getMessage(),
            ]);

            return CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE);
        }
    }
}
