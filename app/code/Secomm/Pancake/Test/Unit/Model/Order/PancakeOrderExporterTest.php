<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Test\Unit\Model\Order;

use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\FulfillmentCore\Api\Data\WarehouseMapDto;
use Secomm\FulfillmentCore\Api\OrderFulfillmentSourceResolverInterface;
use Secomm\FulfillmentCore\Api\WarehouseMapResolverInterface;
use Secomm\Pancake\Model\Client\PosClient;
use Secomm\Pancake\Model\Config\PancakeConfig;
use Secomm\Pancake\Model\Order\PancakeOrderExporter;
use Secomm\Pancake\Model\Order\PayloadBuilder;

class PancakeOrderExporterTest extends TestCase
{
    public function testExportFailsWhenWarehouseUnmapped(): void
    {
        $config = $this->createMock(PancakeConfig::class);
        $config->method('isEnabled')->willReturn(true);

        $sourceResolver = $this->createMock(OrderFulfillmentSourceResolverInterface::class);
        $sourceResolver->method('resolveSourceCodes')->willReturn(['hcm']);

        $mapResolver = $this->createMock(WarehouseMapResolverInterface::class);
        $mapResolver->method('resolve')->with('pancake', 'hcm')->willReturn(null);

        $posClient = $this->createMock(PosClient::class);
        $posClient->expects($this->never())->method('createOrder');

        $exporter = new PancakeOrderExporter(
            $config,
            $this->createMock(PayloadBuilder::class),
            $posClient,
            $sourceResolver,
            $mapResolver,
            $this->createMock(LoggerInterface::class)
        );

        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');

        $result = $exporter->export($order);
        $this->assertFalse($result->isSuccess());
        $this->assertSame(PancakeOrderExporter::ERROR_WAREHOUSE_UNMAPPED, $result->getErrorCode());
    }

    public function testExportSendsWarehouseIdWhenMapped(): void
    {
        $config = $this->createMock(PancakeConfig::class);
        $config->method('isEnabled')->willReturn(true);

        $sourceResolver = $this->createMock(OrderFulfillmentSourceResolverInterface::class);
        $sourceResolver->method('resolveSourceCodes')->willReturn(['hcm']);

        $map = new WarehouseMapDto();
        $map->setServiceCode('pancake')
            ->setMagentoSourceCode('hcm')
            ->setExternalWarehouseId('W1')
            ->setExternalWarehouseLabel('Kho HCM');

        $mapResolver = $this->createMock(WarehouseMapResolverInterface::class);
        $mapResolver->method('resolve')->willReturn($map);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');

        $payloadBuilder = $this->createMock(PayloadBuilder::class);
        $payloadBuilder->expects($this->once())
            ->method('build')
            ->with($order, 'W1')
            ->willReturn(['warehouse_id' => 'W1', 'custom_id' => '100000001']);

        $posClient = $this->createMock(PosClient::class);
        $posClient->expects($this->once())
            ->method('createOrder')
            ->willReturn(['id' => 99]);

        $exporter = new PancakeOrderExporter(
            $config,
            $payloadBuilder,
            $posClient,
            $sourceResolver,
            $mapResolver,
            $this->createMock(LoggerInterface::class)
        );

        $result = $exporter->export($order);
        $this->assertTrue($result->isSuccess());
        $this->assertSame('99', $result->getExternalOrderId());
    }
}
