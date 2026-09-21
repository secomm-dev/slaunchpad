<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */

namespace Secomm\GiaoHangNhanh\Test\Unit\Model\Service\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
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
use Secomm\GiaoHangNhanh\Model\Service\Request\ServicesDataBuilder;

/**
 * BUG-JBX3H9 — the services request must derive both districts from real configuration and
 * the location mapping; the removed develop-mode branch must not be able to fake them.
 */
class ServicesDataBuilderTest extends TestCase
{
    private const FROM_DISTRICT = 1442;

    private ConfigInterface&MockObject $serviceConfig;
    private LocationResolverInterface&MockObject $locationResolver;
    private ServicesDataBuilder $builder;

    protected function setUp(): void
    {
        $this->serviceConfig = $this->createMock(ConfigInterface::class);
        $this->locationResolver = $this->createMock(LocationResolverInterface::class);

        $serviceConfig = $this->serviceConfig;
        $locationResolver = $this->locationResolver;

        $this->serviceConfig->method('getValue')->willReturnCallback(
            function (string $field) {
                return match ($field) {
                    'district' => self::FROM_DISTRICT,
                    'is_develop_mode' => 1, // legacy stale value — must be ignored entirely
                    default => null,
                };
            }
        );

        $this->builder = new ServicesDataBuilder(
            $serviceConfig,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(Information::class),
            $this->createMock(AddressFactory::class),
            $this->createMock(Config::class),
            $this->createMock(Rate::class),
            $locationResolver,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testBuildsFromConfiguredFromDistrictAndMappedToDistrict(): void
    {
        $rateRequest = $this->createMock(RateRequest::class);
        $rateRequest->method('getData')->willReturnCallback(
            function (string $field) {
                return match ($field) {
                    'dest_region_id' => 601,
                    'dest_city' => 'Thành phố Thủ Đức',
                    default => null,
                };
            }
        );

        $locationResult = $this->createMock(LocationResultInterface::class);
        $locationResult->method('getDistrictId')->willReturn(1444);
        $locationResult->method('getWardCode')->willReturn('20110');
        $this->locationResolver->expects($this->once())
            ->method('resolveByName')
            ->with(601, 'Thành phố Thủ Đức')
            ->willReturn($locationResult);

        $data = $this->builder->build(['rate_request' => $rateRequest]);

        $this->assertSame(self::FROM_DISTRICT, $data['from_district']);
        $this->assertSame(1444, $data['to_district']);
    }

    public function testUnmappedDestinationFailsClosed(): void
    {
        $rateRequest = $this->createMock(RateRequest::class);
        $rateRequest->method('getData')->willReturnCallback(
            function (string $field) {
                return match ($field) {
                    'dest_region_id' => 601,
                    'dest_city' => 'Unknown City',
                    default => null,
                };
            }
        );
        $this->locationResolver->method('resolveByName')
            ->willThrowException(new NoSuchEntityException(__('No mapping.')));

        $this->expectException(GhnLocationMappingException::class);
        $this->builder->build(['rate_request' => $rateRequest]);
    }
}
