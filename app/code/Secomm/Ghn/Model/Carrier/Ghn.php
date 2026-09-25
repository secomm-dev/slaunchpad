<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Carrier;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Framework\Xml\Security;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackingErrorFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory as TrackingStatusFactory;
use Psr\Log\LoggerInterface;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\Ghn\Model\Capability\GhnRateCapabilityAdapter;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Rate\RealtimeRateContributorFactory;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackEligibility;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateExecutionRequestInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;
use Secomm\ShippingCore\Api\Rate\CarrierRateExecutionServiceInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionRequest;
use Secomm\ShippingCore\Model\ShippingContextFactory;
use Secomm\VietNamAddress\Api\VnOperationalAddressResolverInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeCollectorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;

/**
 * TASK-FMBBSD (GHN-C slice 2) — THE Magento carrier for Secomm_Ghn: a THIN adapter that turns
 * `collectRates()` into the ShippingCore v5 RATE pipeline (per-op handoff → Stage-2
 * APPROVED-mapping → GHN Calculate Fee → CarrierRateOutcome → RateResult\Method | no method).
 *
 * Ownership boundaries (architecture §7/§8): canonical VN resolution → ShippingCore /
 * VietNamAddress (never here); GHN provider mapping + GHN API → GhnRateCalculator;
 * fallback orchestration → composition upstream (this carrier only produces outcomes);
 * COD/insurance policy → not decided here (collectionAmount = null at RATE).
 *
 * Method identity is the stable pair carrier=`secomm_ghn` / method=`secomm_ghn` — provider
 * service ids/types and district/ward codes are never exposed as identity (SPEC §19).
 *
 * Never read config in the constructor: CarrierFactory instantiates the carrier from
 * `carriers/secomm_ghn/model` and sets the store scope AFTER construction.
 */
final class Ghn extends AbstractCarrierOnline implements CarrierInterface
{
    /** Carrier code AND method code — MUST equal the `carriers/secomm_ghn` config group. */
    public const CARRIER_CODE = 'secomm_ghn';

    /** One stable Magento method per provider (SPEC §20); not a GHN service id. */
    public const METHOD_CODE = self::CARRIER_CODE;

    /** GHN quotes VND — provider fact of the fee API, enforced in collect(). */
    private const CURRENCY_VND = 'VND';

    /**
     * Carrier-owned free-form diagnostic: store configuration unusable for rating
     * (e.g. weight unit missing/unrecognized — fail-closed per TL review 2026-09-14).
     */
    public const REASON_INVALID_CONFIGURATION = 'INVALID_CONFIGURATION';

    /** TASK-MD2BD3 (v10) — RateSourceMode::FALLBACK_ONLY short-circuit at the carrier entry. */
    public const REASON_RATE_SKIPPED_FALLBACK_ONLY = 'GHN_RATE_SKIPPED_FALLBACK_ONLY';

    protected $_code = self::CARRIER_CODE;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Security $xmlSecurity,
        ElementFactory $xmlElFactory,
        ResultFactory $rateFactory,
        MethodFactory $rateMethodFactory,
        TrackingResultFactory $trackFactory,
        TrackingErrorFactory $trackErrorFactory,
        TrackingStatusFactory $trackStatusFactory,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        DirectoryHelper $directoryData,
        StockRegistryInterface $stockRegistry,
        private readonly GhnLogger $ghnLogger,
        private readonly CarrierRateOutcomeCollectorInterface $outcomeCollector,
        private readonly \Secomm\Ghn\Model\Tracking\GhnTrackingResultBuilder $trackingResultBuilder,
        private readonly \Secomm\Ghn\Model\Rate\GhnRateAdjuster $rateAdjuster,
        private readonly \Secomm\Ghn\Model\Config $ghnConfig,
        private readonly CarrierRateExecutionServiceInterface $executionService,
        private readonly RuntimeAddressContextBuilderInterface $runtimeContextBuilder,
        private readonly VnOperationalAddressResolverInterface $operationalAddressResolver,
        private readonly ShippingContextFactory $shippingContextFactory,
        private readonly RealtimeRateContributorFactory $contributorFactory,
        private readonly GhnAddressCapability $addressCapability,
        array $data = []
    ) {
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
    }

    /**
     * Estimation never crashes: every unexpected failure is logged and the GHN method hidden.
     *
     * @return Result|Error|false — never a raw provider exception
     */
    public function collectRates(RateRequest $request): bool|Error|Result
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        try {
            return $this->collect($request);
        } catch (\Throwable $exception) {
            // TASK-SEC-D r4 (TL/SA decision): unexpected programming/runtime defects are
            // FAIL-CLOSED — explicit transported NONE (UNEXPECTED_RUNTIME_FAILURE), never
            // TECHNICAL_FALLBACK, never a legacy policy re-judge. Classified provider
            // failures (timeout/5xx/malformed/auth) were already translated inside the
            // contributor/calculator and never reach this catch-all. The decision is
            // recorded exactly once (success-terminal merge keeps an earlier record whole).
            $this->ghnLogger->error(
                'GHN collectRates failed; unexpected runtime failure — fail closed (no fallback).',
                [
                    'exception_class' => $exception::class,
                    'message' => $exception->getMessage(),
                    'carrier' => self::CARRIER_CODE,
                    'method' => self::METHOD_CODE,
                ]
            );
            $this->recordDecision(
                CarrierRateOutcome::technicalFailure(ShippingFailureReason::UNEXPECTED_RUNTIME_FAILURE),
                FallbackEligibility::none()
            );

            return $this->hide();
        }
    }

    /**
     * TASK-5XQXZK: report the normalized outcome FACT to ShippingCore (status + structured
     * reason). Reporting never drives fallback itself and never throws into the carrier path.
     */
    private function recordOutcome(CarrierRateOutcomeInterface $outcome): void
    {
        $this->outcomeCollector->record(self::CARRIER_CODE, self::METHOD_CODE, $outcome);
    }

    /**
     * TASK-SEC-D-transport — record the SHARED EXECUTION's decision atomically: the outcome
     * and the exact transported fallback eligibility of the SAME execution (never re-judged
     * here). Pre-execution carrier facts (VN destination gate) stay legacy records — the
     * composition judges those through the frozen reason contract.
     */
    private function recordDecision(CarrierRateOutcomeInterface $outcome, FallbackEligibilityInterface $eligibility): void
    {
        $this->outcomeCollector->recordDecision(self::CARRIER_CODE, self::METHOD_CODE, $outcome, $eligibility);
    }

    /**
     * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — the production RATE entry goes through the shared
     * `CarrierRateExecutionService` (v10 §35.6 frozen order: eligibility → mode → origin
     * readiness → address policy → realtime contributor). GHN keeps only carrier-specific
     * entry gates (VN destination, VND base currency); eligibility, mode short-circuit,
     * origin readiness, address policy and the realtime dispatch are the shared service's.
     * The realtime tail is `RealtimeRateContributor` → `GhnRateCalculator::quoteWithHandoff`
     * — identical pricing tail as the pre-wiring path (§25 ALL-parity).
     *
     * @return Result|Error|Result
     * @throws \Throwable re-thrown to the catch-all in collectRates on unexpected failure
     */
    private function collect(RateRequest $request): bool|Error|Result
    {
        // VN only (mirrors specificcountry=VN so direct collectRates() callers behave the same).
        if ((string) $request->getDestCountryId() !== 'VN') {
            // TASK-SEC-D-transport: explicit NONE — unsupported destination never degrades.
            $this->recordDecision(
                CarrierRateOutcome::unavailable(ShippingFailureReason::UNSUPPORTED_DESTINATION),
                FallbackEligibility::none()
            );

            return $this->hide();
        }

        $storeId = $this->getData("store") !== null ? (int) $this->getData("store") : null;
        $destinationScope = $this->ghnConfig->getDestinationScope($storeId);
        $allowedZoneCodes = $this->ghnConfig->getAllowedZoneCodes($storeId);
        $rateSourceMode = $this->ghnConfig->getRateSourceMode($storeId);

        // The fee is quoted in VND; a non-VND base would silently show a mis-scaled price.
        // VND-only rate slice — conversion is a recorded deviation, not a silent fallback.
        $baseCurrency = $request->getBaseCurrency();
        $baseCurrencyCode = $baseCurrency instanceof Currency ? $baseCurrency->getCurrencyCode() : null;
        if ($baseCurrencyCode !== self::CURRENCY_VND) {
            $this->ghnLogger->warning(
                'GHN rate: store base currency is not VND; method hidden.',
                ['base_currency' => $baseCurrencyCode]
            );
            // TASK-SEC-D-transport: explicit NONE — merchant configuration is fail-closed.
            $this->recordDecision(
                CarrierRateOutcome::unavailable(ShippingFailureReason::INVALID_CONFIGURATION),
                FallbackEligibility::none()
            );

            return $this->hide();
        }

        $decision = $this->executionService->execute(
            $this->buildExecutionRequest($request, $storeId, $destinationScope, $allowedZoneCodes, $rateSourceMode)
        );

        if (!$decision->shouldInvokeRealtime()) {
            if ($decision->getRealtimeOutcome() === null
                && $decision->getReason() === ShippingFailureReason::DESTINATION_NOT_IN_SCOPE
            ) {
                // §20/§22 — merchant-configured service-area restriction: fail closed; the
                // reported reason is what keeps the fallback coordinator from opening pricing.
                $this->ghnLogger->warning(
                    'GHN rate unavailable; destination outside configured zones.',
                    ['destination_scope' => $destinationScope, 'allowed_zone_codes' => $allowedZoneCodes]
                );
                $this->recordDecision(
                    CarrierRateOutcome::unavailable(ShippingFailureReason::DESTINATION_NOT_IN_SCOPE),
                    $decision->getFallbackEligibility()
                );

                return $this->hide();
            }

            if ($rateSourceMode === RateSourceMode::FALLBACK_ONLY) {
                // Eligible FALLBACK_ONLY short-circuit (§35.6): the composition's fallback
                // coordinator owns the fallback dispatch — GHN only reports the skip fact.
                $this->recordDecision(
                    CarrierRateOutcome::unavailable(self::REASON_RATE_SKIPPED_FALLBACK_ONLY),
                    $decision->getFallbackEligibility()
                );

                return $this->hide();
            }

            // Realtime mode, realtime blocked upstream (address policy / origin). Report the
            // ACTUAL canonical fact: an AMBIGUOUS block retains candidates on the handoff and
            // MUST surface as CANONICAL_AMBIGUOUS — that is the reason the shared fallback
            // policy judges as legacy-address-eligible (pre-wiring parity). Everything else
            // keeps its decision reason.
            $handoff = $decision->getCarrierFacingHandoff();
            $reason = $handoff !== null && $handoff->getCandidateCodes() !== []
                ? ShippingFailureReason::CANONICAL_AMBIGUOUS
                : ($decision->getReason() ?? ShippingFailureReason::CANONICAL_UNRESOLVED);
            $this->ghnLogger->call('GHN rate unavailable; realtime blocked upstream.', ['reason' => $reason]);
            // TASK-SEC-D-transport: the shared decision's eligibility is transported verbatim
            // (AMBIGUOUS block → legacy-address eligible per frozen policy — no re-judge).
            $this->recordDecision(CarrierRateOutcome::unavailable($reason), $decision->getFallbackEligibility());

            return $this->hide();
        }

        $outcome = $decision->getRealtimeOutcome();
        if ($outcome === null) {
            throw new \LogicException('Realtime execution decision without an outcome.');
        }

        if ($outcome->isSuccessful()) {
            // TASK-SEC-D r5 — SUCCESS COMMIT POINT: the transported SUCCESS is recorded only
            // AFTER a usable Magento rate result exists (adjust once + method/result built).
            // An adjuster/factory failure lands in the carrier catch-all as
            // UNEXPECTED_RUNTIME_FAILURE/NONE — the collector can never keep a stale SUCCESS
            // that contradicts a no-rate customer result (invariant: transported SUCCESS IFF
            // the carrier produced a usable method for this execution).
            $adjusted = $this->rateAdjuster->adjust((float) $outcome->getRate()->getAmount(), $storeId);
            $result = $this->buildResult($adjusted);
            $this->recordDecision($outcome, $decision->getFallbackEligibility());

            return $result;
        }

        // Non-success: record the exact transported decision BEFORE returning the hidden
        // result — adjustment/build never run on this path (recorded exactly once).
        $this->recordDecision($outcome, $decision->getFallbackEligibility());

        $context = ['status' => $outcome->getStatus(), 'reason' => $outcome->getFailureReason()];
        if ($outcome->getStatus() === CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE) {
            $this->ghnLogger->warning('GHN rate technical failure; no rate.', $context);

            return $this->hide();
        }

        $this->ghnLogger->call('GHN rate unavailable; no rate.', $context);

        return $this->hide();
    }

    /**
     * FEAT-QA23PZ — composes the shared execution request. Eligibility scalars come from the
     * canonical identity bridges ONLY (region-only id bridge for the province; the SAME
     * resolution context the handoff consumes for the ward — id-bridge first, name-bridge
     * fallback). Unresolved identity leads to empty/null scalars: fail-closed under
     * SELECTED_ZONES, harmless under ALL (the handoff classifies UNMAPPED as before).
     */
    private function buildExecutionRequest(
        RateRequest $request,
        ?int $storeId,
        string $destinationScope,
        array $allowedZoneCodes,
        string $rateSourceMode
    ): CarrierRateExecutionRequestInterface {
        $capability = new GhnRateCapabilityAdapter($this->addressCapability);
        $regionId = (int) $request->getDestRegionId();
        $localityName = trim((string) $request->getDestCity());
        $resolutionContext = $this->runtimeContextBuilder->build(
            $request->getDestCountryId() !== null ? (string) $request->getDestCountryId() : null,
            $regionId,
            null,
            $localityName !== '' ? $localityName : null,
            $capability
        );
        $identity = $this->operationalAddressResolver->resolveFromRuntime($regionId, 0)->getIdentity();

        return new CarrierRateExecutionRequest(
            carrierCode: self::CARRIER_CODE,
            destinationScope: $destinationScope,
            allowedZoneCodes: $allowedZoneCodes,
            destinationProvinceCode: $identity?->getRegionCode() ?? '',
            destinationWardCode: $resolutionContext->getSourceUnitCode(),
            rateSourceMode: $rateSourceMode,
            addressResolutionPolicy: $this->ghnConfig->getAddressResolutionPolicy($storeId),
            capability: $this->addressCapability,
            resolutionContext: $resolutionContext,
            shippingContext: $this->shippingContextFactory->fromRateRequest($request, self::CARRIER_CODE),
            realtimeContributor: $this->contributorFactory->create($request)
        );
    }


    /**
     * SUCCESS outcome → the single stable Magento method (price = cost = GHN total fee).
     */
    private function buildResult(float $amount): Result
    {
        /** @var Method $method */
        $method = $this->_rateMethodFactory->create();
        $method->setCarrier(self::CARRIER_CODE);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod(self::METHOD_CODE);
        $method->setMethodTitle($this->getConfigData('name'));
        $method->setPrice($amount);
        $method->setCost($amount);

        /** @var Result $result */
        $result = $this->_rateFactory->create();
        $result->append($method);

        return $result;
    }

    /**
     * "No GHN method" translation. When `showmethod` is enabled the carrier surfaces the
     * configured error message instead of vanishing silently (merchant diagnostics option).
     */
    private function hide(): bool|Error|Result
    {
        if (!$this->getConfigFlag('showmethod')) {
            return false;
        }

        /** @var Error $error */
        $error = $this->_rateErrorFactory->create();
        $error->setCarrier(self::CARRIER_CODE);
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setErrorMessage($this->getConfigData('specificerrmsg'));

        return $error;
    }

    /**
     * SPEC §19/§20 — one stable method code, not a GHN service id.
     */
    public function getAllowedMethods(): array
    {
        return [self::METHOD_CODE => (string) $this->getConfigData('name')];
    }

    /**
     * Parent validation parity (AbstractCarrierOnline::processAdditionalValidation) with ONE
     * deliberate fix: the per-item `max_package_weight` check runs only when the merchant
     * actually configured a max. The parent casts the unconfigured value to 0.0 and then
     * rejects EVERY weighted cart, silently hiding the method store-wide — a trap for a
     * carrier that does not ship this config. Aggregate weight policy stays with the rate
     * pipeline (`GhnParcel::isDeliverable()` + the heavy-parcel guard), where the GHN weight
     * class actually lives.
     *
     * Everything else from the parent is preserved verbatim: stock-item decimal-weight
     * expansion, the zip-code gate (`isZipCodeRequired`), and the showmethod error rendering.
     */
    public function processAdditionalValidation(DataObject $request): Ghn|bool
    {
        $maxAllowedWeight = $this->getConfigData('max_package_weight');
        $errorMsg = '';
        if ($maxAllowedWeight !== null && $maxAllowedWeight !== '') {
            foreach ($this->getAllItems($request) as $item) {
                $weight = $this->getValidatedItemWeight($item);
                if ($weight !== null && $weight > (double) $maxAllowedWeight) {
                    $errorMsg = (string) ($this->getConfigData('specificerrmsg')
                        ?: __('The shipping module is not available.'));
                    break;
                }
            }
        }

        if ($errorMsg === '' && !$request->getDestPostcode()
            && $this->isZipCodeRequired($request->getDestCountryId())
        ) {
            $errorMsg = (string) __('This shipping method is not available. Please specify the zip code.');
        }

        return $errorMsg === '' ? $this : $this->validationError($errorMsg);
    }

    /**
     * Parent-parity per-item effective weight (decimal qty / qty-increments expansion).
     *
     * @return float|null null = item skipped (no product, or decimal-divided without increments)
     */
    private function getValidatedItemWeight(DataObject $item): ?float
    {
        $product = $item->getProduct();
        if (!$product || !$product->getId()) {
            return null;
        }

        $weight = (float) $product->getWeight();
        $stockItemData = $this->stockRegistry->getStockItem(
            $product->getId(),
            $item->getStore()->getWebsiteId()
        );

        if ($stockItemData->getIsQtyDecimal() && $stockItemData->getIsDecimalDivided()) {
            if ($stockItemData->getEnableQtyIncrements() && $stockItemData->getQtyIncrements()) {
                $weight = $weight * $stockItemData->getQtyIncrements();
            } else {
                return null;
            }
        } elseif ($stockItemData->getIsQtyDecimal() && !$stockItemData->getIsDecimalDivided()) {
            $weight = $weight * $item->getQty();
        }

        return $weight;
    }

    /**
     * Parent-parity rendering of a validation failure: honor `showmethod`, else hide silently.
     */
    private function validationError(string $message): bool|Error
    {
        if (!$this->getConfigFlag('showmethod')) {
            return false;
        }

        /** @var Error $error */
        $error = $this->_rateErrorFactory->create();
        $error->setCarrier(self::CARRIER_CODE);
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setErrorMessage($message);

        return $error;
    }

    /**
     * TASK-PWHG0V (GHN-E3-A) — TRUE now that {@see getTracking()} is implemented on the E1
     * pipeline and runtime-proven: the admin Add-Tracking dropdown and the customer tracking
     * popup route here. Failure-safe by contract (the builder resolves errors to a safe
     * Magento `Error` result — never an exception).
     */
    public function isTrackingAvailable(): bool
    {
        return true;
    }

    /**
     * GHN-E3-A — the Magento tracking number for this carrier IS the GHN order_code (attached
     * by GHN-D's TrackAttacher; never client_order_code / shipment id / increment id). The
     * builder performs one provider query through the E1 fetcher + the SAME
     * GhnStatusMapper the webhook uses, feeds the normalized-state processor (occurrence-aware
     * no-op when already synced), and returns a display-only Status — or a safe Error result
     * when the provider is unreachable / the order is unknown (never crashes the popup).
     *
     * @param string $tracking GHN order_code
     * @return \Magento\Shipping\Model\Tracking\Result|false
     */
    public function getTracking($tracking)
    {
        return $this->trackingResultBuilder->build((string) $tracking, (string) $this->getConfigData('title'));
    }

    /**
     * Stays FALSE for GHN-D (TL brief §34/§35 option A): the Create Order response carries NO
     * label PDF, and a faked label_content would render a broken combined PDF. Shipment
     * creation is triggered by the shipment-save observer instead (label capability returns
     * with a real GHN label retrieval in a later slice).
     */
    public function isShippingLabelsAvailable(): bool
    {
        return false;
    }

    /**
     * Required by the abstract parent; the create-order flow (GHN-D) overrides the admin
     * shipment entry point instead of this per-package loop.
     */
    /**
     * Never used: the CREATE trigger is the shipment-save observer (labels stay disabled, so
     * the core per-package label loop never runs for this carrier). Throws hard in case a
     * future integration ever reaches it — creation goes through
     * `GhnShipmentCreationService::createForShipment()` only.
     */
    protected function _doShipmentRequest(DataObject $request): never
    {
        throw new LocalizedException(__('GHN shipment creation runs through the shipment observer, not the label flow.'));
    }
}
