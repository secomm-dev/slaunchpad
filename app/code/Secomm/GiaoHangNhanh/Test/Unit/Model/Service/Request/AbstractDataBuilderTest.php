<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */

namespace Secomm\GiaoHangNhanh\Test\Unit\Model\Service\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\GhnAddressMapper\Api\Data\LocationResultInterface;
use Secomm\GhnAddressMapper\Api\LocationResolverInterface;
use Secomm\GiaoHangNhanh\Helper\Rate;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\ConfigInterface;
use Secomm\GiaoHangNhanh\Model\Config;
use Secomm\GiaoHangNhanh\Model\Exception\GhnLocationMappingException;
use Secomm\GiaoHangNhanh\Model\Service\Request\AbstractDataBuilder;

/**
 * BUG-JBX3H9 — GHN location resolution must fail closed: an unmappable destination/origin
 * never falls back to hardcoded district/ward ids, regardless of any (legacy) config value.
 */
class AbstractDataBuilderTest extends TestCase
{
    private ConfigInterface&MockObject $serviceConfig;
    private LocationResolverInterface&MockObject $locationResolver;
    private LoggerInterface&MockObject $logger;
    private AbstractDataBuilder $builder;

    protected function setUp(): void
    {
        $this->serviceConfig = $this->createMock(ConfigInterface::class);
        $this->locationResolver = $this->createMock(LocationResolverInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $serviceConfig = $this->serviceConfig;
        $locationResolver = $this->locationResolver;
        $logger = $this->logger;

        $this->builder = new class (
            $serviceConfig,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(Information::class),
            $this->createMock(AddressFactory::class),
            $this->createMock(Config::class),
            $this->createMock(Rate::class),
            $locationResolver,
            $logger
        ) extends AbstractDataBuilder {
            public function exposedResolveGhnLocation(int $regionId, string $city = '', int $cityId = 0): array
            {
                return $this->resolveGhnLocation($regionId, $city, $cityId);
            }

            public function build(array $buildSubject)
            {
                return [];
            }
        };
    }

    public function testValidMappingByIdIsUsed(): void
    {
        $this->locationResolver->expects($this->once())
            ->method('resolve')
            ->with(601, 12345)
            ->willReturn($this->createLocationResult(1442, '20112'));
        $this->locationResolver->expects($this->never())->method('resolveByName');

        $location = $this->builder->exposedResolveGhnLocation(601, '', 12345);

        $this->assertSame(['toDistrictId' => 1442, 'toWardCode' => '20112'], $location);
    }

    public function testValidMappingByNameIsUsedWhenNoCityId(): void
    {
        $this->locationResolver->expects($this->once())
            ->method('resolveByName')
            ->with(601, 'Phường Bến Nghé')
            ->willReturn($this->createLocationResult(1442, '20112'));

        $location = $this->builder->exposedResolveGhnLocation(601, 'Phường Bến Nghé', 0);

        $this->assertSame(['toDistrictId' => 1442, 'toWardCode' => '20112'], $location);
    }

    public function testMissingMappingFailsClosedEvenWithLegacyDevelopModeValue(): void
    {
        // A stale DB value of the removed develop-mode flag must not reactivate any fallback.
        $this->serviceConfig->method('getValue')->with('is_develop_mode')->willReturn(1);
        $this->locationResolver->method('resolve')
            ->willThrowException(new NoSuchEntityException(__('No mapping.')));
        $this->expectMappingFailureLog();

        try {
            $this->builder->exposedResolveGhnLocation(601, '', 12345);
            $this->fail('Expected GhnLocationMappingException');
        } catch (GhnLocationMappingException $e) {
            $this->assertStringContainsString('region_id=601', $e->getMessage());
        }
    }

    public function testMissingIdentifiersFailClosed(): void
    {
        $this->locationResolver->expects($this->never())->method('resolve');
        $this->locationResolver->expects($this->never())->method('resolveByName');
        $this->expectMappingFailureLog();

        $this->expectException(GhnLocationMappingException::class);
        $this->builder->exposedResolveGhnLocation(0, '', 0);
    }

    public function testMissingRegionWithOnlyCityNameFailsClosed(): void
    {
        $this->locationResolver->expects($this->never())->method('resolveByName');

        $this->expectException(GhnLocationMappingException::class);
        $this->builder->exposedResolveGhnLocation(0, 'Phường Bến Nghé');
    }

    public function testPartialMappingWithoutDistrictFailsClosed(): void
    {
        $this->locationResolver->method('resolve')
            ->willReturn($this->createLocationResult(0, '20112'));
        $logContext = [];
        $this->expectMappingFailureLog($logContext);

        try {
            $this->builder->exposedResolveGhnLocation(601, '', 12345);
            $this->fail('Expected GhnLocationMappingException');
        } catch (GhnLocationMappingException $e) {
            $this->assertStringContainsString('region_id=601', $e->getMessage());
        }

        $this->assertStringContainsString('incomplete mapping row', (string)($logContext['reason'] ?? ''));
    }

    public function testPartialMappingWithoutWardFailsClosed(): void
    {
        $this->locationResolver->method('resolve')
            ->willReturn($this->createLocationResult(1442, ''));

        $this->expectException(GhnLocationMappingException::class);
        $this->builder->exposedResolveGhnLocation(601, '', 12345);
    }

    private function createLocationResult(int $districtId, string $wardCode): LocationResultInterface&MockObject
    {
        $result = $this->createMock(LocationResultInterface::class);
        $result->method('getDistrictId')->willReturn($districtId);
        $result->method('getWardCode')->willReturn($wardCode);

        return $result;
    }

    private function expectMappingFailureLog(array &$logContext = []): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('[GHN Location Mapping]'),
                $this->callback(function (array $context) use (&$logContext): bool {
                    $logContext = $context;

                    return isset($context['side'], $context['region_id'], $context['city_id'], $context['city']);
                })
            );
    }
}
