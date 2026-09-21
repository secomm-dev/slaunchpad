<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Carrier;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Helper\Data as DirectoryData;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Shipment\Request as ShipmentRequest;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackErrorFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory as TrackStatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackResultFactory;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\GhtkAddressAdapter;
use Secomm\Ghtk\Model\Address\PickupAddress;
use Secomm\Ghtk\Model\Address\PickupAddressResolver;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Fee\FeeRequestMapper;
use Secomm\Ghtk\Model\Fee\FeeResponseMapper;
use Secomm\Ghtk\Model\Fee\RateComposer;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\Ghtk\Model\Log\MaskingLogger;
use Secomm\Ghtk\Model\Rate\GhtkFeeResponse;
use Secomm\Ghtk\Model\OrderSubmit\LabelPdfGenerator;
use Secomm\Ghtk\Model\OrderSubmit\OrderSubmitService;
use Secomm\Ghtk\Model\Origin\GhtkOriginProvider;
use Secomm\Ghtk\Model\Rate\GhtkRateOutcomeFactory;
use Secomm\Ghtk\Model\RateCache;
use Secomm\Ghtk\Model\Shipment\ShipmentWeightCalculator;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeCollectorInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Model\ShippingContextFactory;

/**
 * GHTK shipping carrier (SL-009 rate; SL-016 native shipping-label flow).
 *
 * Extends AbstractCarrierOnline so the carrier rides Magento's NATIVE label
 * lifecycle (DEC-SL016-001): normal shipments make no GHTK call; only the
 * "Create Shipping Label" path submits an order (before the shipment is
 * saved — failure aborts the shipment, never a false success). No observers,
 * no custom Online/Offline concept, no outbox.
 *
 * Rate path (collectRates) is unchanged and fully independent from shipment
 * creation: using a GHTK rate at checkout never implies a GHTK order.
 */
class Ghtk extends AbstractCarrierOnline implements CarrierInterface
{
    protected $_code = 'ghtk';
    protected $_isFixed = false;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Security $xmlSecurity,
        ElementFactory $xmlElFactory,
        ResultFactory $rateFactory,
        TrackResultFactory $trackFactory,
        TrackErrorFactory $trackErrorFactory,
        TrackStatusFactory $trackStatusFactory,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        DirectoryData $directoryData,
        StockRegistryInterface $stockRegistry,
        private ResultFactory $rateResultFactory,
        private MethodFactory $rateMethodFactory,
        private GhtkAddressAdapter $destResolver,
        private PickupAddressResolver $pickupResolver,
        private ShipmentWeightCalculator $weightCalculator,
        private GhtkApiClient $apiClient,
        private FeeResponseMapper $feeMapper,
        private GhtkRateOutcomeFactory $outcomeFactory,
        private RateComposer $rateComposer,
        private RateCache $rateCache,
        private GhtkConfig $ghtkConfig,
        private MaskingLogger $maskingLogger,
        private GhtkOriginProvider $originProvider,
        private ShippingContextFactory $contextFactory,
        private FeeRequestMapper $requestMapper,
        private OrderSubmitService $orderSubmitService,
        private LabelPdfGenerator $labelPdfGenerator,
        private readonly CarrierRateOutcomeCollectorInterface $outcomeCollector,
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

    public function getAllowedMethods(): array
    {
        return ['ghtk_standard' => $this->ghtkConfig->getName() ?: (string) __('GHTK Express')];
    }

    public function isTrackingAvailable(): bool
    {
        return false; // tracking numbers persist on the shipment; live lookup is a follow-up
    }

    public function isShippingLabelsAvailable(): bool
    {
        return true; // native "Create Shipping Label" checkbox → SL-016 flow
    }

    public function getContainerTypes(?\Magento\Framework\DataObject $params = null): array
    {
        return ['PACKAGE' => __('Package')];
    }

    /**
     * GHTK submits ONE order per shipment (single-parcel concept), so the
     * native per-package loop is replaced by a single aggregated submission:
     * package weights are summed inside OrderSubmitService. Returns native
     * response shape — errors make LabelGenerator throw, which ABORTS the
     * shipment save (no false success).
     *
     * @param ShipmentRequest $request
     * @return DataObject
     */
    public function requestToShipment($request)
    {
        try {
            $packages = $request->getPackages();
            if (!is_array($packages) || !$packages) {
                return (new DataObject())->setErrors([__('No packages for request.')]);
            }

            $result = $this->orderSubmitService->submit($request);
            $pdf = $this->labelPdfGenerator->generate(
                $result->trackingNumber,
                $result->labelId,
                $result->partnerOrderId,
                [
                    (string) $request->getShipperContactPersonName(),
                    (string) $request->getShipperAddressStreet(),
                    '',
                    (string) $request->getRecipientContactPersonName(),
                    (string) $request->getRecipientAddressStreet(),
                ]
            );

            return new DataObject([
                'info' => [
                    ['tracking_number' => $result->trackingNumber, 'label_content' => $pdf],
                ],
            ]);
        } catch (LocalizedException $e) {
            return (new DataObject())->setErrors([$e->getMessage()]);
        } catch (\Throwable $e) {
            $this->maskingLogger->error('GHTK requestToShipment failed.', ['exception' => $e->getMessage()]);
            return (new DataObject())->setErrors([__('GHTK shipping label failed: %1', $e->getMessage())]);
        }
    }

    /**
     * Never used — requestToShipment() is overridden above (single aggregated
     * submission instead of the per-package loop). Required by the abstract
     * parent.
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        throw new LocalizedException(__('GHTK submits one order per shipment; requestToShipment() is overridden.'));
    }

    /**
     * @return Result|false
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        try {
            return $this->collect($request);
        } catch (\Throwable $e) {
            // AC-011: never crash shipping estimation.
            $this->maskingLogger->error(
                'GHTK collectRates failed; returning no rate (graceful).',
                ['exception' => $e->getMessage()]
            );
            $this->reportOutcome(CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR));

            return $this->hide();
        }
    }

    /**
     * TASK-5XQXZK: report the normalized outcome FACT to ShippingCore (status + structured
     * reason). Reporting never triggers fallback itself and never throws into the carrier path.
     */
    private function reportOutcome(\Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface $outcome): void
    {
        $this->outcomeCollector->record($this->_code, 'ghtk_standard', $outcome);
    }

    /**
     * @return Result|false
     * @throws \Throwable Re-thrown to the catch-all in collectRates on unexpected failure.
     */
    private function collect(RateRequest $request)
    {
        $storeId = $request->getStoreId() !== null ? (int) $request->getStoreId() : null;

        // VN gate (AC-014).
        if ((string) $request->getDestCountryId() !== 'VN') {
            $this->reportOutcome(CarrierRateOutcome::unavailable(ShippingFailureReason::UNSUPPORTED_DESTINATION));
            return $this->hide();
        }

        // Destination (VN 2-level: province = region, ward stored in native city) — through
        // the ShippingCore canonical pipeline (TASK-7AJ3K8).
        $regionId = (int) $request->getDestRegionId();
        $wardName = trim((string) $request->getDestCity());
        $dest = $this->destResolver->resolve('VN', $regionId, null, $wardName !== '' ? $wardName : null, ShippingAddressOperation::RATE);
        if ($dest === null) {
            $this->maskingLogger->info('GHTK: destination not resolvable; no rate.', ['region_id' => $regionId]);
            $this->reportOutcome(CarrierRateOutcome::unavailable(ShippingFailureReason::CANONICAL_UNRESOLVED));
            return $this->hide();
        }

        // Origin (SL-015): context → provider chain → strict pickup gate (DEC-021).
        $context = $this->contextFactory->fromRateRequest($request, $this->_code);
        $origin = $this->originProvider->resolve($context);
        $pickup = $this->pickupResolver->resolve($origin, ShippingAddressOperation::RATE);
        if ($pickup === null) {
            $this->maskingLogger->warning(
                'GHTK: pickup configuration invalid; carrier inactive. '
                . 'Set carriers/ghtk/pick_* or configure the Magento Shipping Origin.'
            );
            $this->reportOutcome(CarrierRateOutcome::unavailable(ShippingFailureReason::INVALID_CONFIGURATION));
            return $this->hide();
        }

        $weightGram = $this->weightCalculator->calculate($request, $storeId);
        $value = (float) $request->getPackageValue();
        $transport = $this->ghtkConfig->getTransport($storeId);

        // Rate cache (AC-012) — key includes every parameter affecting the fee.
        $cacheKey = $this->buildCacheKey($pickup, $dest, $weightGram, $value, $transport);
        $cached = $this->rateCache->load($cacheKey);
        if ($cached !== null) {
            $this->reportOutcome($this->outcomeFactory->success((float) $cached));
            return $this->buildResult($cached, $storeId);
        }

        // Fee API call — outcome classification per TASK-W8SH0N (v5 semantics):
        // TECHNICAL_FAILURE (network/5xx/malformed) vs UNAVAILABLE (business/auth);
        // customer-facing behavior stays binary (rate / rate-unavailable).
        try {
            $response = $this->apiClient->getFee(
                $this->requestMapper->map($dest, $pickup, $weightGram, $value, $transport),
                $storeId
            );
        } catch (GhtkApiException $e) {
            $this->logRateOutcome(
                $this->outcomeFactory->fromTransportException($e),
                ['province' => $dest->province, 'ward' => $dest->ward, 'exception' => $e->getMessage()]
            );
            return $this->hide();
        }

        $feeResponse = $this->feeMapper->parse($response);
        if ($feeResponse->getKind() !== GhtkFeeResponse::KIND_SUCCESS) {
            $this->logRateOutcome(
                $this->outcomeFactory->fromParsedResponse($feeResponse),
                [
                    'response_kind' => $feeResponse->getKind(),
                    'error_code' => $feeResponse->getErrorCode(),
                    'message' => $feeResponse->getMessage(),
                    'province' => $dest->province,
                    'ward' => $dest->ward,
                ]
            );
            return $this->hide();
        }

        $fee = $feeResponse->getFee();
        if (!$fee->delivery) {
            $this->logRateOutcome(
                $this->outcomeFactory->fromParsedResponse($feeResponse),
                ['delivery' => false, 'province' => $dest->province, 'ward' => $dest->ward]
            );
            return $this->hide();
        }

        $amount = $this->rateComposer->compose($fee, $this->ghtkConfig->getRateInclude($storeId));
        $this->rateCache->save($cacheKey, $amount);
        $this->logRateOutcome($this->outcomeFactory->success($amount), ['amount' => $amount]);

        return $this->buildResult($amount, $storeId);
    }

    /**
     * One masked classification line per RATE attempt — safe diagnostics only
     * (status/reason/error_code/admin names); never token/telephone/full address.
     */
    private function logRateOutcome(\Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface $outcome, array $context): void
    {
        $this->reportOutcome($outcome);
        $level = $outcome->isSuccessful() ? 'info' : ($outcome->getStatus() === 'TECHNICAL_FAILURE' ? 'warning' : 'info');
        $this->maskingLogger->{$level}(
            'GHTK rate outcome: ' . $outcome->getStatus() . '.',
            $context + ['failure_reason' => $outcome->getFailureReason()]
        );
    }

    private function buildResult(float $amount, ?int $storeId): Result
    {
        $method = $this->rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->ghtkConfig->getTitle($storeId));
        $method->setMethod('ghtk_standard');
        $method->setMethodTitle($this->ghtkConfig->getName($storeId) ?: (string) __('GHTK Express'));
        $method->setPrice($amount);
        $method->setCost($amount);

        $result = $this->rateResultFactory->create();
        $result->append($method);

        return $result;
    }

    /**
     * Hide the carrier entirely (no rate, no error) when it cannot serve.
     */
    private function hide(): Result|false
    {
        if ($this->ghtkConfig->isShowMethod()) {
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->ghtkConfig->getTitle());
            $error->setErrorMessage($this->ghtkConfig->getSpecificerrmsg());

            $result = $this->rateResultFactory->create();
            $result->append($error);

            return $result;
        }

        return false;
    }

    private function buildCacheKey(
        PickupAddress $pickup,
        GhtkAddress $dest,
        int $weightGram,
        float $value,
        string $transport
    ): string {
        return md5(
            $this->requestMapper->pickupIdentity($pickup)
            . '|' . $dest->province . '|' . $dest->ward
            . '|' . $weightGram . '|' . $value . '|' . $transport
        );
    }
}
