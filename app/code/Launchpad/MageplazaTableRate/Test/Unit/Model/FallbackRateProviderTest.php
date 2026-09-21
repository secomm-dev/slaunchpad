<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — internal fallback calculator tests (rework of TASK-NQT782).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Model;

use Launchpad\MageplazaTableRate\Model\Exception\FallbackConfigurationException;
use Launchpad\MageplazaTableRate\Model\FallbackRateProvider;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Mageplaza\TableRateShipping\Model\Method;
use Mageplaza\TableRateShipping\Model\MethodFactory;
use Mageplaza\TableRateShipping\Model\Rate;
use Mageplaza\TableRateShipping\Model\ResourceModel\Rate\Collection;
use Mageplaza\TableRateShipping\Model\ResourceModel\Rate\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Fallback\FallbackRateRequestInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackRate;

class FallbackRateProviderTest extends TestCase
{
    private MethodFactory $methodFactory;

    private CollectionFactory $rateCollectionFactory;

    private FallbackRateProvider $provider;

    protected function setUp(): void
    {
        $this->methodFactory = $this->getMockBuilder(MethodFactory::class)
            ->disableOriginalConstructor()->getMock();
        $this->rateCollectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()->getMock();
        $this->provider = new FallbackRateProvider($this->methodFactory, $this->rateCollectionFactory);
    }

    private function methodMock(array $behavior = []): Method
    {
        // isActive/getTitle are real methods; getId/getName/getCalculateRule are DataObject
        // magic accessors -> added to the mock explicitly.
        $method = $this->getMockBuilder(Method::class)
            ->onlyMethods(['getId', 'isActive', 'getTitle', 'load'])
            ->addMethods(['getName', 'getCalculateRule'])
            ->disableOriginalConstructor()
            ->getMock();
        $method->method('load')->willReturnSelf();
        $method->method('getId')->willReturn($behavior['id'] ?? 10);
        $method->method('isActive')->willReturn($behavior['active'] ?? true);
        $method->method('getTitle')->willReturn($behavior['title'] ?? 'Giao tiết kiệm');
        $method->method('getName')->willReturn($behavior['name'] ?? 'Ten noi bo');
        $method->method('getCalculateRule')->willReturn($behavior['rule'] ?? 'min');

        return $method;
    }

    private function givenCollection(array $rates): void
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()->getMock();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('filterByRequest')->willReturnSelf();
        $collection->method('getItems')->willReturn($rates);

        $this->rateCollectionFactory->method('create')->willReturn($collection);
    }

    private function rateMock(float $price): Rate
    {
        $rate = $this->getMockBuilder(Rate::class)->disableOriginalConstructor()->getMock();
        $rate->method('calculatePrice')->willReturn($price);

        return $rate;
    }

    public function testBucketCodeParses(): void
    {
        $this->assertSame(10, FallbackRateProvider::methodIdFromBucketCode('mptr_10'));
        $this->assertNull(FallbackRateProvider::methodIdFromBucketCode('STANDARD'));
        $this->assertNull(FallbackRateProvider::methodIdFromBucketCode('mptr_'));
        $this->assertNull(FallbackRateProvider::methodIdFromBucketCode('mptr_12x'));
    }

    public function testGetRateWithForeignBucketReturnsNull(): void
    {
        $request = $this->createMock(FallbackRateRequestInterface::class);
        $this->assertNull($this->provider->getRate('SOMETHING_ELSE', $request));
    }

    public function testCalculateInactiveMethodReturnsNull(): void
    {
        $method = $this->methodMock(['active' => false]);
        $this->methodFactory->method('create')->willReturn($method);
        $this->givenCollection([]);

        $this->assertNull($this->provider->calculate(10, new RateRequest()));
    }

    public function testCalculateNoMatchingRowReturnsNullNeverZero(): void
    {
        $this->methodFactory->method('create')->willReturn($this->methodMock());
        $this->givenCollection([]);

        $this->assertNull($this->provider->calculate(10, new RateRequest()));
    }

    public function testCalculateCombinesByMinRule(): void
    {
        $this->methodFactory->method('create')->willReturn($this->methodMock(['rule' => 'min']));
        $this->givenCollection([$this->rateMock(40000.0), $this->rateMock(35000.0)]);

        $fallbackRate = $this->provider->calculate(10, $this->storeRequest());

        $this->assertInstanceOf(FallbackRate::class, $fallbackRate);
        $this->assertSame(35000.0, $fallbackRate->getAmount());
        $this->assertSame('Giao tiết kiệm', $fallbackRate->getLabel());
    }

    public function testCalculateUsesPackageScalars(): void
    {
        $this->methodFactory->method('create')->willReturn($this->methodMock(['rule' => 'sum']));
        $this->givenCollection([$this->rateMock(20000.0), $this->rateMock(10000.0)]);

        $request = $this->storeRequest();
        $request->setPackageWeight(2.5);
        $request->setPackageValue(500000.0);
        $request->setPackageQty(3);

        $fallbackRate = $this->provider->calculate(10, $request);
        $this->assertSame(30000.0, $fallbackRate->getAmount());
    }

    public function testCalculateMissingMethodThrowsConfigException(): void
    {
        $method = $this->methodMock(['id' => 0]);
        $this->methodFactory->method('create')->willReturn($method);
        $this->givenCollection([]);

        $this->expectException(FallbackConfigurationException::class);
        $this->provider->calculate(404, $this->storeRequest());
    }

    public function testStoreIdTravelsFromMagentoRequest(): void
    {
        $method = $this->methodMock();
        $method->method('isActive')->with(7)->willReturn(true);
        $this->methodFactory->method('create')->willReturn($method);
        $this->givenCollection([$this->rateMock(1000.0)]);

        $request = new RateRequest();
        $request->setStoreId(7);

        $this->assertInstanceOf(FallbackRate::class, $this->provider->calculate(10, $request));
    }

    private function storeRequest(): RateRequest
    {
        $request = new RateRequest();
        $request->setStoreId(1);
        $request->setDestCountryId('VN');
        $request->setDestRegionId(20);
        $request->setDestCity('Phường Bến Nghé');

        return $request;
    }
}
