<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Carrier;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Helper\Data as DirectoryData;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Shipment\Request as ShipmentRequest;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackErrorFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory as TrackStatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackResultFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Secomm\Ghtk\Model\Address\GhtkAddressAdapter;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddressResolver;
use Secomm\Ghtk\Model\Carrier\Ghtk;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Fee\FeeRequestMapper;
use Secomm\Ghtk\Model\Fee\FeeResponseMapper;
use Secomm\Ghtk\Model\Fee\RateComposer;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\Log\MaskingLogger;
use Secomm\Ghtk\Model\OrderSubmit\LabelPdfGenerator;
use Secomm\Ghtk\Model\OrderSubmit\OrderSubmitService;
use Secomm\Ghtk\Model\Origin\GhtkOriginProvider;
use Secomm\Ghtk\Model\RateCache;
use Secomm\Ghtk\Model\Shipment\ShipmentWeightCalculator;
use Secomm\ShippingCore\Api\OriginInterface;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Model\Origin;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcomeCollector;
use Secomm\ShippingCore\Model\ShippingContextFactory;

/**
 * SL-015 AC-5 extension-point proof (rate path) + SL-016 rate/submit
 * independence: a custom OriginProviderInterface replaces the default
 * shipping-origin provider via DI — and the carrier, WITHOUT any
 * modification, builds the fee request from that origin. The rate path never
 * touches the order-submit service.
 */
class GhtkTest extends TestCase
{
    /**
     * Builds the real carrier with only the origin *inner* provider stubbed —
     * everything else on the origin path (GhtkOriginProvider BC chain,
     * PickupAddressResolver, FeeRequestMapper) is the production code.
     */
    private function carrier(OriginInterface $innerOrigin, GhtkApiClient $apiClient): Ghtk
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);

        $inner = $this->createMock(OriginProviderInterface::class);
        $inner->method('resolve')->willReturn($innerOrigin);

        $ghtkConfig = $this->createMock(GhtkConfig::class);
        $ghtkConfig->method('getTitle')->willReturn('GHTK');
        $ghtkConfig->method('getName')->willReturn('GHTK Express');
        $ghtkConfig->method('getTransport')->willReturn('road');
        $ghtkConfig->method('getRateInclude')->willReturn([]);
        $ghtkConfig->method('isShowMethod')->willReturn(false);

        $destResolver = $this->createMock(GhtkAddressAdapter::class);
        $destResolver->method('resolve')->willReturn(new GhtkAddress('Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Trống', true));

        $weightCalculator = $this->createMock(ShipmentWeightCalculator::class);
        $weightCalculator->method('calculate')->willReturn(1500);

        $rateCache = $this->createMock(RateCache::class);
        $rateCache->method('load')->willReturn(null);

        $method = $this->createMock(Method::class);
        $methodFactory = $this->createMock(MethodFactory::class);
        $methodFactory->method('create')->willReturn($method);

        $rateResult = $this->createMock(Result::class);
        $rateResultFactory = $this->createMock(ResultFactory::class);
        $rateResultFactory->method('create')->willReturn($rateResult);

        // SL-016: the submit service must NEVER be called on the rate path.
        $orderSubmitService = $this->createMock(OrderSubmitService::class);
        $orderSubmitService->expects($this->never())->method('submit');

        return new Ghtk(
            $scopeConfig,
            $this->createMock(ErrorFactory::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Security::class),
            $this->createMock(ElementFactory::class),
            $rateResultFactory,
            $this->createMock(TrackResultFactory::class),
            $this->createMock(TrackErrorFactory::class),
            $this->createMock(TrackStatusFactory::class),
            $this->createMock(RegionFactory::class),
            $this->createMock(CountryFactory::class),
            $this->createMock(CurrencyFactory::class),
            $this->createMock(DirectoryData::class),
            $this->createMock(StockRegistryInterface::class),
            $rateResultFactory,
            $methodFactory,
            $destResolver,
            new PickupAddressResolver($destResolver),
            $weightCalculator,
            $apiClient,
            new FeeResponseMapper(),
            new \Secomm\Ghtk\Model\Rate\GhtkRateOutcomeFactory(),
            new RateComposer(),
            $rateCache,
            $ghtkConfig,
            $this->createMock(MaskingLogger::class),
            new GhtkOriginProvider($ghtkConfig, $inner), // legacy pick_* all empty → inner wins
            new ShippingContextFactory(),
            new FeeRequestMapper(),
            $orderSubmitService,
            $this->createMock(LabelPdfGenerator::class),
            new CarrierRateOutcomeCollector(new NullLogger()),
        );
    }

    private function request(): RateRequest
    {
        return new RateRequest([
            'store_id' => 1,
            'dest_country_id' => 'VN',
            'dest_region_id' => '467',
            'dest_city' => 'Phường Hàng Trống',
            'package_value' => 500000,
        ]);
    }

    /**
     * A custom fulfillment-style provider returns an MSI-source origin that
     * carries the carrier metadata pick_address_id → the carrier must send it.
     */
    public function testCustomOriginProviderWithCarrierMetadataWins(): void
    {
        $origin = new Origin(
            sourceCode: 'hcm-warehouse-1',
            countryId: 'VN',
            regionId: null,
            province: 'Hồ Chí Minh',
            district: null,
            ward: 'Phường Bến Nghé',
            street: null,
            postcode: null,
            telephone: null,
            contactName: null,
            metadata: ['ghtk.pick_address_id' => 'source-paid-999']
        );

        $apiClient = $this->createMock(GhtkApiClient::class);
        $apiClient->expects($this->once())
            ->method('getFee')
            ->with(
                $this->callback(function (array $params) {
                    return $params['pick_address_id'] === 'source-paid-999'
                        && $params['province'] === 'Hà Nội'
                        && $params['ward'] === 'Phường Hàng Trống'
                        && $params['weight'] === 1500;
                }),
                1 // store scope flows through to the client
            )
            ->willReturn(['fee' => ['fee' => 30000, 'insurance_fee' => 0, 'extFees' => 0, 'delivery' => true]]);

        $result = $this->carrier($origin, $apiClient)->collectRates($this->request());

        $this->assertInstanceOf(Result::class, $result);
    }

    /**
     * A custom provider without carrier metadata → normalized address fields
     * are sent (fallback path of the metadata rule, DEC-SL015-001 §3).
     */
    public function testCustomOriginProviderWithoutMetadataUsesAddress(): void
    {
        $origin = new Origin(
            sourceCode: 'hn-store',
            countryId: 'VN',
            regionId: null,
            province: 'Hà Nội',
            district: 'Hoàn Kiếm',
            ward: 'Phường Hàng Trống',
            street: null,
            postcode: null,
            telephone: null,
            contactName: null
        );

        $apiClient = $this->createMock(GhtkApiClient::class);
        $apiClient->expects($this->once())
            ->method('getFee')
            ->with(
                $this->callback(function (array $params) {
                    return ($params['pick_province'] ?? null) === 'Hà Nội'
                        && ($params['pick_ward'] ?? null) === 'Phường Hàng Trống'
                        && ($params['pick_district'] ?? null) === 'Hoàn Kiếm'
                        && !isset($params['pick_address_id']);
                }),
                1
            )
            ->willReturn(['fee' => ['fee' => 25000, 'delivery' => true]]);

        $this->assertInstanceOf(Result::class, $this->carrier($origin, $apiClient)->collectRates($this->request()));
    }

    /**
     * Strict gate stays active under a custom provider: an unusable custom
     * origin (no province/ward, no metadata) → carrier hides, no API call.
     */
    public function testUnusableCustomOriginHidesCarrierWithoutApiCall(): void
    {
        $origin = new Origin(
            sourceCode: 'broken',
            countryId: 'VN',
            regionId: null,
            province: null,
            district: null,
            ward: null,
            street: null,
            postcode: null,
            telephone: null,
            contactName: null
        );

        $apiClient = $this->createMock(GhtkApiClient::class);
        $apiClient->expects($this->never())->method('getFee');

        $this->assertFalse($this->carrier($origin, $apiClient)->collectRates($this->request()));
    }

    /**
     * SL-016 §9: a GHTK rate never implies a GHTK order — covered implicitly
     * by the never()-expectation on OrderSubmitService::submit above; this
     * test pins the module structure: no observer may auto-push on shipment
     * save (etc/events.xml must not exist).
     */
    public function testNoShipmentSaveObserversExist(): void
    {
        $this->assertFileDoesNotExist(dirname(__DIR__, 3) . '/etc/events.xml');
        $this->assertFileDoesNotExist(dirname(__DIR__, 3) . '/etc/adminhtml/events.xml');
    }

    /**
     * SL-016 §2: the carrier opts into the native label flow.
     */
    public function testShippingLabelsAvailable(): void
    {
        $origin = new Origin(null, 'VN', null, 'Hà Nội', null, 'Phường Hàng Trống', null, null, null, null);
        $carrier = $this->carrier($origin, $this->createMock(GhtkApiClient::class));

        $this->assertTrue($carrier->isShippingLabelsAvailable());
        $this->assertFalse($carrier->isTrackingAvailable());
        $this->assertNotEmpty($carrier->getContainerTypes());
        $this->assertInstanceOf(ShipmentRequest::class, new ShipmentRequest());
    }
}
