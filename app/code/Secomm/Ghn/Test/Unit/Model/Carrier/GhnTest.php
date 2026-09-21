<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Carrier;

use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackingErrorFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory as TrackingStatusFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcomeCollector;
use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Rate\GhnParcel;
use Secomm\Ghn\Model\Rate\GhnRateCalculator;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimate;
use Secomm\Ghn\Model\Rate\GhnRateQuery;
use Secomm\Ghn\Model\Rate\GhnRateRequestMapper;
use Secomm\Ghn\Model\Rate\EstimatedPackage;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Rate\GhnRateAdjuster;
use Secomm\Ghn\Model\Tracking\GhnTrackingResultBuilder;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRate;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;

/**
 * TASK-FMBBSD (GHN-C slice 2) — Magento carrier adapter behavior: the active gate, the VN and
 * VND-only guards, the outcome → "method | no method" translation (with outcome-visible logs),
 * the stable `secomm_ghn` method identity, and estimation-never-crashes error containment.
 */
class GhnTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    private ResultFactory&MockObject $rateResultFactory;

    private MethodFactory&MockObject $rateMethodFactory;

    private ErrorFactory&MockObject $rateErrorFactory;

    private GhnRateCalculator&MockObject $rateCalculator;

    private GhnRateRequestMapper&MockObject $requestMapper;

    private LoggerInterface&MockObject $psrLogger;

    private StockRegistryInterface&MockObject $stockRegistry;

    private DirectoryHelper&MockObject $directoryData;

    private Ghn $carrier;

    private GhnRateAdjuster&MockObject $rateAdjuster;

    private Config&MockObject $ghnConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->rateResultFactory = $this->createMock(ResultFactory::class);
        $this->rateMethodFactory = $this->createMock(MethodFactory::class);
        $this->rateErrorFactory = $this->createMock(ErrorFactory::class);
        $this->rateCalculator = $this->createMock(GhnRateCalculator::class);
        $this->requestMapper = $this->createMock(GhnRateRequestMapper::class);
        $this->psrLogger = $this->createMock(LoggerInterface::class);
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->directoryData = $this->createMock(DirectoryHelper::class);

        $this->carrier = $this->createCarrier();
    }

    public function testInactiveCarrierReturnsFalse(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $this->assertFalse($this->carrier->collectRates($this->vnRequest()));
    }

    public function testNonVietnamDestinationIsHidden(): void
    {
        $this->givenActiveCarrier();
        $this->psrLogger->expects($this->never())->method('warning');

        $this->assertFalse($this->carrier->collectRates($this->vnRequest(destCountryId: 'US')));
    }

    public function testAllowedMethodsExposeTheStableMethodCode(): void
    {
        $this->givenConfigValue('name', 'GHN Delivery');

        $this->assertSame(['secomm_ghn' => 'GHN Delivery'], $this->carrier->getAllowedMethods());
    }

    // ---------- processAdditionalValidation: parent parity minus the weight trap ----------

    public function testUnconfiguredMaxWeightNoLongerRejectsEveryWeightedCart(): void
    {
        // The vendor trap: parent casts unset `max_package_weight` to 0.0 → every weighted
        // item "exceeds" it → the method would be hidden store-wide. Locked here.
        $this->givenActiveCarrier();
        $request = $this->validationRequest(items: [$this->quoteItem(999.0)]);

        $this->assertSame($this->carrier, $this->carrier->processAdditionalValidation($request));
    }

    public function testConfiguredMaxWeightIsStillEnforced(): void
    {
        // One getValue registration (PHPUnit keeps the first stub per method) — all fields here.
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => str_ends_with($path, '/active')
        );
        $this->givenConfigValues([
            'title' => 'GHN',
            'name' => 'GHN Delivery',
            'showmethod' => 0,
            'specificerrmsg' => 'too heavy',
            'max_package_weight' => '1',
        ]);
        $request = $this->validationRequest(items: [$this->quoteItem(2.0)]);

        $this->assertFalse($this->carrier->processAdditionalValidation($request));
    }

    public function testParentZipCodeGateIsPreserved(): void
    {
        $this->givenActiveCarrier();
        // `VN` is not zip-optional in the directory data → missing postcode must hide GHN,
        // exactly like the parent validation would.
        $this->directoryData->method('isZipCodeOptional')->with('VN')->willReturn(false);

        $this->assertFalse($this->carrier->processAdditionalValidation(
            $this->validationRequest(items: [], postcode: null)
        ));
        $this->assertSame($this->carrier, $this->carrier->processAdditionalValidation(
            $this->validationRequest(items: [], postcode: '700000')
        ));
    }

    public function testUnusableStoreConfigurationFailsClosedAsInvalidConfiguration(): void
    {
        $this->givenActiveCarrier();
        $this->requestMapper->method('map')->willThrowException(
            new LocalizedException(__('store weight unit must be kgs or lbs'))
        );
        $this->psrLogger->expects($this->once())->method('warning')->with(
            'GHN rate unavailable; no rate.',
            $this->callback(fn (array $context): bool =>
                $context['reason'] === Ghn::REASON_INVALID_CONFIGURATION
                && $context['status'] === CarrierRateOutcomeInterface::STATUS_UNAVAILABLE)
        );
        $this->rateCalculator->expects($this->never())->method('calculate');

        $this->assertFalse($this->carrier->collectRates($this->vnRequest()));
    }

    // ---------- outcome translation ----------

    public function testSuccessOutcomeBuildsTheSingleStableMethod(): void
    {
        $this->givenActiveCarrier();
        $this->givenCalculatorOutcome(
            CarrierRateOutcome::success(new CarrierRate(25000.0, 'VND'))
        );

        $appended = null;
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('append')->with(
            $this->callback(function ($method) use (&$appended): bool {
                $appended = $method;

                return true;
            })
        );
        // Method needs a price currency (setPrice rounds through it) — a real instance +
        // getter assertions is the honest shape.
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnArgument(0);
        $this->rateMethodFactory->method('create')->willReturnCallback(
            fn (): Method => new Method($priceCurrency)
        );
        $this->rateResultFactory->method('create')->willReturn($result);

        $rateResult = $this->carrier->collectRates($this->vnRequest());

        $this->assertInstanceOf(Result::class, $rateResult);
        $this->assertInstanceOf(Method::class, $appended);
        $this->assertSame('secomm_ghn', $appended->getCarrier());
        $this->assertSame('secomm_ghn', $appended->getMethod());
        $this->assertSame('GHN', $appended->getCarrierTitle());
        $this->assertSame('GHN Delivery', $appended->getMethodTitle());
        $this->assertSame(25000.0, $appended->getPrice());
        $this->assertSame(25000.0, $appended->getCost());
    }

    public function testUnavailableOutcomeHidesTheMethodAndLogsInfo(): void
    {
        $this->givenActiveCarrier();
        $this->givenCalculatorOutcome(
            CarrierRateOutcome::unavailable('PROVIDER_MAPPING_MISSING')
        );
        $this->psrLogger->expects($this->once())->method('info')->with(
            'GHN rate unavailable; no rate.',
            $this->callback(fn (array $context): bool =>
                $context['status'] === CarrierRateOutcomeInterface::STATUS_UNAVAILABLE
                && $context['reason'] === 'PROVIDER_MAPPING_MISSING')
        );
        $this->rateMethodFactory->expects($this->never())->method('create');

        $this->assertFalse($this->carrier->collectRates($this->vnRequest()));
    }

    public function testTechnicalFailureOutcomeHidesTheMethodAndLogsWarning(): void
    {
        $this->givenActiveCarrier();
        $this->givenCalculatorOutcome(
            CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR')
        );
        $this->psrLogger->expects($this->once())->method('warning')->with(
            'GHN rate technical failure; no rate.',
            $this->callback(fn (array $context): bool =>
                $context['status'] === CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE)
        );
        $this->rateMethodFactory->expects($this->never())->method('create');

        $this->assertFalse($this->carrier->collectRates($this->vnRequest()));
    }

    public function testNonVndBaseCurrencyIsHiddenWithWarning(): void
    {
        $this->givenActiveCarrier();
        $usd = $this->createMock(Currency::class);
        $usd->method('getCurrencyCode')->willReturn('USD');
        $this->psrLogger->expects($this->once())->method('warning')->with(
            'GHN rate: store base currency is not VND; method hidden.',
            ['base_currency' => 'USD']
        );
        $this->rateCalculator->expects($this->never())->method('calculate');

        $this->assertFalse($this->carrier->collectRates($this->vnRequest(baseCurrency: $usd)));
    }

    public function testUnexpectedExceptionIsContainedToNoRate(): void
    {
        $this->givenActiveCarrier();
        $this->requestMapper->method('map')->willThrowException(new \RuntimeException('boom'));
        $this->psrLogger->expects($this->once())->method('error')->with(
            'GHN collectRates failed; returning no rate (graceful).',
            $this->callback(fn (array $context): bool => $context['exception'] === 'boom')
        );

        $this->assertFalse($this->carrier->collectRates($this->vnRequest()));
    }

    // ---------- TASK-PWHG0V (GHN-E3-A/C): tracking + label boundaries ----------

    public function testTrackingAvailableTrueWithRealImplementation(): void
    {
        // Locked AFTER getTracking() is implemented on the E1 pipeline (brief §4: never a
        // temporary true flag).
        $this->assertTrue($this->carrier->isTrackingAvailable());
    }

    public function testLabelCapabilityStaysDisabled(): void
    {
        // TASK-PWHG0V E3-C outcome B: the GHN print endpoint exists (matrix §12) but no
        // Magento-compatible label artifact was verifiable in sandbox — a faked label is
        // forbidden (brief §26). Locked here.
        $this->assertFalse($this->carrier->isShippingLabelsAvailable());
    }

    public function testGetTrackingDelegatesToTheResultBuilder(): void
    {
        $builder = $this->createMock(GhnTrackingResultBuilder::class);
        $result = $this->createMock(\Magento\Shipping\Model\Tracking\Result::class);

        $this->givenConfigValue('title', 'GHN Delivery');
        $builder->expects($this->once())->method('build')
            ->with('L8TAKR', 'GHN Delivery')
            ->willReturn($result);

        $this->carrier = $this->createCarrier($builder);
        $this->assertSame($result, $this->carrier->getTracking('L8TAKR'));
    }
    public function testFallbackOnlyModeShortCircuitsBeforeMappingAndApi(): void
    {
        // TASK-MD2BD3 (v10 §4): FALLBACK_ONLY = ShippingCore short-circuits realtime RATE —
        // the GHN adapter must not touch canonical mapping or the provider API at all.
        $this->ghnConfig->method('getRateSourceMode')->willReturn(RateSourceMode::FALLBACK_ONLY);
        $this->rateCalculator->expects($this->never())->method('calculate');

        $result = $this->carrier->collectRates($this->vnRequest());

        $this->assertFalse($result);
    }

    public function testCarrierOnlyModeStillRunsThePipeline(): void
    {
        $this->givenActiveCarrier();
        $this->ghnConfig->method('getRateSourceMode')->willReturn(RateSourceMode::CARRIER_ONLY);
        $this->requestMapper->method('map')->willReturn(
            new GhnRateQuery('VN', 12, null, 'Phường Bến Nghé', new QuoteParcelEstimate([new EstimatedPackage(10, 'UNIT-SKU', 1500.0, 'quote_item_weight')]), null)
        );
        $this->rateCalculator->expects($this->once())->method('calculate')->willReturn(
            CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE)
        );

        $this->assertFalse($this->carrier->collectRates($this->vnRequest()));
    }
    // ---------- helpers ----------

    /**
     * A RateRequest shaped for the parent-parity validation path. Postcode defaults to a
     * present value so the zip gate stays out of the weight assertions.
     */
    private function validationRequest(array $items, ?string $postcode = '700000'): RateRequest
    {
        $request = (new RateRequest())->setDestCountryId('VN')->setAllItems($items);
        if ($postcode !== null) {
            $request->setDestPostcode($postcode);
        }

        return $request;
    }

    /**
     * Simple non-virtual, ship-together quote item whose product carries a unit weight (kg).
     */
    private function quoteItem(float $productWeightKg): Item&MockObject
    {
        $product = $this->createMock(\Magento\Catalog\Model\Product::class);
        $product->method('getId')->willReturn(5);
        $product->method('getWeight')->willReturn($productWeightKg);
        $product->method('isVirtual')->willReturn(false);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);

        $item = $this->createMock(Item::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getParentItem')->willReturn(null);
        // getHasChildren()/isShipSeparately() are magic-only — the stubbed __call returns null,
        // which routes the item through the parent's ship-together branch (the desired shape).
        $item->method('getStore')->willReturn($store);
        $item->method('getQty')->willReturn(1.0);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getIsQtyDecimal')->willReturn(false);
        $this->stockRegistry->method('getStockItem')->willReturn($stockItem);

        return $item;
    }

    private function givenCalculatorOutcome(CarrierRateOutcomeInterface $outcome): void
    {
        $this->rateCalculator->method('calculate')->willReturn($outcome);
        // TASK-WAWNDS: the real adjuster is config-driven and OFF by default in these tests —
        // the mocked one passes the provider rate through untouched.
        $this->rateAdjuster->method('adjust')->willReturnArgument(0);
        $this->requestMapper->method('map')->willReturn(
            new GhnRateQuery('VN', 12, null, 'Phường Bến Nghé', new QuoteParcelEstimate([new EstimatedPackage(10, 'UNIT-SKU', 1500.0, 'quote_item_weight')]), null)
        );
    }

    private function createCarrier(?GhnTrackingResultBuilder $trackingResultBuilder = null): Ghn
    {
        return new Ghn(
            $this->scopeConfig,
            $this->rateErrorFactory,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Security::class),
            $this->createMock(ElementFactory::class),
            $this->rateResultFactory,
            $this->rateMethodFactory,
            $this->createMock(TrackingResultFactory::class),
            $this->createMock(TrackingErrorFactory::class),
            $this->createMock(TrackingStatusFactory::class),
            $this->createMock(RegionFactory::class),
            $this->createMock(CountryFactory::class),
            $this->createMock(CurrencyFactory::class),
            $this->directoryData,
            $this->stockRegistry,
            $this->rateCalculator,
            $this->requestMapper,
            new GhnLogger($this->psrLogger),
            new CarrierRateOutcomeCollector(new NullLogger()),
            $trackingResultBuilder ?? $this->createMock(GhnTrackingResultBuilder::class),
            $this->rateAdjuster ??= $this->createMock(GhnRateAdjuster::class),
            $this->ghnConfig ??= $this->createMock(Config::class)
        );
    }

    private function vnRequest(string $destCountryId = 'VN', ?Currency $baseCurrency = null): RateRequest
    {
        /** @var Currency&MockObject $baseCurrency */
        $baseCurrency = $baseCurrency ?? $this->createMock(Currency::class);
        $baseCurrency->method('getCurrencyCode')->willReturn('VND');

        return (new RateRequest())
            ->setDestCountryId($destCountryId)
            ->setDestRegionId(12)
            ->setDestCity('Phường Bến Nghé')
            ->setPackageWeight(1.5)
            ->setBaseCurrency($baseCurrency);
    }

    private function givenActiveCarrier(): void
    {
        // `active` on, `showmethod` off — only the /active flag is set.
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => str_ends_with($path, '/active')
        );
        $this->givenConfigValues([
            'title' => 'GHN',
            'name' => 'GHN Delivery',
            'showmethod' => 0,
            'specificerrmsg' => 'unavailable',
        ]);
    }

    /**
     * One callback keyed by field — multiple `with()` matchers on the same mocked method
     * overwrite each other, so path-keyed resolution must live in a single stub.
     */
    private function givenConfigValues(array $values): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path) use ($values) {
                $field = (string) str_replace('carriers/secomm_ghn/', '', $path);

                return $values[$field] ?? null;
            }
        );
    }

    private function givenConfigValue(string $path, string $value): void
    {
        $this->givenConfigValues([$path => $value]);
    }
}
