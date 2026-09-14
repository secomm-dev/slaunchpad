<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Test\Unit\Model\Warehouse;

use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventorySalesApi\Model\GetAssignedStockIdForWebsiteInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\FulfillmentCore\Model\Warehouse\OrderFulfillmentSourceResolver;

class OrderFulfillmentSourceResolverTest extends TestCase
{
    public function testPreferItemSourceCodes(): void
    {
        $item = new class {
            public function getParentItemId(): mixed
            {
                return null;
            }

            public function getData(string $key): mixed
            {
                return $key === 'source_code' ? 'hcm' : null;
            }
        };

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn([$item]);
        $order->method('getEntityId')->willReturn(1);

        $resolver = $this->createResolver();
        $this->assertSame(['hcm'], $resolver->resolveSourceCodes($order));
    }

    public function testMultiSourceWarnsAndReturnsFirst(): void
    {
        $item1 = new class {
            public function getParentItemId(): mixed
            {
                return null;
            }

            public function getData(string $key): mixed
            {
                return $key === 'source_code' ? 'hcm' : null;
            }
        };
        $item2 = new class {
            public function getParentItemId(): mixed
            {
                return null;
            }

            public function getData(string $key): mixed
            {
                return $key === 'source_code' ? 'hn' : null;
            }
        };

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn([$item1, $item2]);
        $order->method('getEntityId')->willReturn(2);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $resolver = new OrderFulfillmentSourceResolver(
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(GetAssignedStockIdForWebsiteInterface::class),
            $this->createMock(GetSourcesAssignedToStockOrderedByPriorityInterface::class),
            $this->createMock(DefaultSourceProviderInterface::class),
            $logger
        );

        $codes = $resolver->resolveSourceCodes($order);
        $this->assertSame(['hcm', 'hn'], $codes);
        $this->assertSame('hcm', $codes[0]);
    }

    public function testFallsBackToDefaultSource(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn([]);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(3);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);

        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getWebsite')->willReturn($website);

        $getStock = $this->createMock(GetAssignedStockIdForWebsiteInterface::class);
        $getStock->method('execute')->willReturn(null);

        $defaultSource = $this->createMock(DefaultSourceProviderInterface::class);
        $defaultSource->method('getCode')->willReturn('default');

        $resolver = new OrderFulfillmentSourceResolver(
            $storeManager,
            $getStock,
            $this->createMock(GetSourcesAssignedToStockOrderedByPriorityInterface::class),
            $defaultSource,
            $this->createMock(LoggerInterface::class)
        );

        $this->assertSame(['default'], $resolver->resolveSourceCodes($order));
    }

    public function testWebsiteStockSources(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn([]);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(4);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);

        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getWebsite')->willReturn($website);

        $getStock = $this->createMock(GetAssignedStockIdForWebsiteInterface::class);
        $getStock->method('execute')->willReturn(1);

        $source = $this->createMock(SourceInterface::class);
        $source->method('getSourceCode')->willReturn('stock-src');

        $getSources = $this->createMock(GetSourcesAssignedToStockOrderedByPriorityInterface::class);
        $getSources->method('execute')->willReturn([$source]);

        $resolver = new OrderFulfillmentSourceResolver(
            $storeManager,
            $getStock,
            $getSources,
            $this->createMock(DefaultSourceProviderInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->assertSame(['stock-src'], $resolver->resolveSourceCodes($order));
    }

    private function createResolver(): OrderFulfillmentSourceResolver
    {
        return new OrderFulfillmentSourceResolver(
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(GetAssignedStockIdForWebsiteInterface::class),
            $this->createMock(GetSourcesAssignedToStockOrderedByPriorityInterface::class),
            $this->createMock(DefaultSourceProviderInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }
}
