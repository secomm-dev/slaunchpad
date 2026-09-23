<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ahamove\Test\Unit\Model\Carrier;

use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Config\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ahamove\Helper\Data as AhamoveHelper;
use Secomm\Ahamove\Logger\Logger as LoggerShipping;
use Secomm\Ahamove\Model\Connect\Api;
use Secomm\Ahamove\Model\Carrier\AhamoveAbstractCarrier;
use Secomm\Ahamove\Model\Data\AhamoveAddressFactory;
use Secomm\Ahamove\Model\Data\PackageItemFactory;
use Secomm\Ahamove\Model\PackageFactory;
use Secomm\Ahamove\Model\ResourceModel\AhamoveOrderStatus\CollectionFactory;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Model\ShippingContextFactory;

class AhamoveAbstractCarrierAddressTest extends TestCase
{
    private AhamoveAbstractCarrier $carrier;

    protected function setUp(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $rateErrorFactory = $this->createMock(ErrorFactory::class);
        $logger = $this->createMock(LoggerInterface::class);
        $rateResultFactory = $this->createMock(ResultFactory::class);
        $rateMethodFactory = $this->createMock(MethodFactory::class);
        $ahamoveAddressFactory = $this->createMock(AhamoveAddressFactory::class);
        $api = $this->getMockBuilder(Api::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__destruct'])
            ->getMock();
        $ahamoveHelper = $this->createMock(AhamoveHelper::class);
        $countryFactory = $this->createMock(CountryFactory::class);
        $regionFactory = $this->createMock(RegionFactory::class);
        $loggerShipping = $this->createMock(LoggerShipping::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $timezone = $this->createMock(TimezoneInterface::class);
        $trackFactory = $this->createMock(TrackingResultFactory::class);
        $trackStatusFactory = $this->createMock(StatusFactory::class);
        $orderStatusColFactory = $this->createMock(CollectionFactory::class);
        $quoteAddressFactory = $this->createMock(AddressFactory::class);
        $packageItemFactory = $this->createMock(PackageItemFactory::class);
        $packageFactory = $this->createMock(PackageFactory::class);
        $shippingContextFactory = $this->createMock(ShippingContextFactory::class);
        $originProvider = $this->createMock(OriginProviderInterface::class);

        $this->carrier = new class(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $rateResultFactory,
            $rateMethodFactory,
            $ahamoveAddressFactory,
            $api,
            $ahamoveHelper,
            $countryFactory,
            $regionFactory,
            $loggerShipping,
            $serializer,
            $cache,
            $timezone,
            $trackFactory,
            $trackStatusFactory,
            $orderStatusColFactory,
            $quoteAddressFactory,
            $packageItemFactory,
            $packageFactory,
            $shippingContextFactory,
            $originProvider
        ) extends AhamoveAbstractCarrier {
            protected $_code = 'ahamove_test';

            public function calculateShippingFee(\Secomm\Ahamove\Api\Data\AhamoveAddressInterface $ahamoveAddress): float
            {
                return 25000.0;
            }

            public function testGetCityTo(RateRequest $request): mixed
            {
                $reflection = new \ReflectionClass(AhamoveAbstractCarrier::class);
                $method = $reflection->getMethod('getCityTo');
                $method->setAccessible(true);
                return $method->invoke($this, $request);
            }

            public function testGetRegion(RateRequest $request): mixed
            {
                $reflection = new \ReflectionClass(AhamoveAbstractCarrier::class);
                $method = $reflection->getMethod('getRegion');
                $method->setAccessible(true);
                return $method->invoke($this, $request);
            }
            public function testEstimateShippingCost(RateRequest $request): float
            {
                return $this->estimateShippingCost($request);
            }
        };
    }

    public function testEstimateShippingCostDoesNotThrowOnNullShippingAddress(): void
    {
        $request = new RateRequest();
        $fee = $this->carrier->testEstimateShippingCost($request);
        $this->assertIsFloat($fee);
    }

    public function testGetCityToWithNullShippingAddressDoesNotThrow(): void
    {
        $request = new RateRequest();
        $request->setDestCity('');
        // shipping_address is null

        $city = $this->carrier->testGetCityTo($request);
        $this->assertSame('', $city);
    }

    public function testGetFullStreetWithNullShippingAddressDoesNotThrow(): void
    {
        $request = new RateRequest();
        $request->setDestStreet('');

        $street = $this->carrier->getFullStreet($request);
        $this->assertSame('', $street);
    }

    public function testGetRegionWithNullShippingAddressDoesNotThrow(): void
    {
        $request = new RateRequest();
        $request->setDestRegion('');
        $request->setDestRegionCode('');

        $region = $this->carrier->testGetRegion($request);
        $this->assertSame('', $region);
    }

    public function testGetShippingAddressSelectedExtractsFromQuoteItem(): void
    {
        $quoteAddress = $this->createMock(QuoteAddress::class);
        $quoteAddress->method('getCity')->willReturn('Ho Chi Minh');

        $item = $this->createMock(QuoteItem::class);
        $item->method('getAddress')->willReturn($quoteAddress);

        $request = new RateRequest();
        $request->setDestCity('');
        $request->setAllItems([$item]);

        $selected = $this->carrier->getShippingAddressSelected($request);
        $this->assertSame($quoteAddress, $selected);

        $city = $this->carrier->testGetCityTo($request);
        $this->assertSame('Ho Chi Minh', $city);
    }

    public function testGetCityToWithDataObjectShippingAddress(): void
    {
        $request = new RateRequest();
        $request->setDestCity('');
        $request->setData('shipping_address', new DataObject(['city' => ['city' => 'Da Nang']]));

        $city = $this->carrier->testGetCityTo($request);
        $this->assertSame('Da Nang', $city);
    }
}
