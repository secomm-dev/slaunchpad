<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Test\Unit\Model\Order;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use PHPUnit\Framework\TestCase;
use Secomm\Pancake\Model\Config\PancakeConfig;
use Secomm\Pancake\Model\Order\PayloadBuilder;

class PayloadBuilderTest extends TestCase
{
    public function testCustomIdIsIncrementIdAndItemsAreOneTimeProducts(): void
    {
        $config = $this->createMock(PancakeConfig::class);
        $config->method('getShopId')->willReturn('4');

        $address = $this->createMock(OrderAddressInterface::class);
        $address->method('getFirstname')->willReturn('An');
        $address->method('getLastname')->willReturn('Nguyen');
        $address->method('getTelephone')->willReturn('0900000000');
        $address->method('getStreet')->willReturn(['12 Le Loi']);
        $address->method('getCity')->willReturn('Hanoi');
        $address->method('getRegion')->willReturn('HN');
        $address->method('getPostcode')->willReturn('100000');
        $address->method('getCountryId')->willReturn('VN');

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getQtyOrdered')->willReturn(2.0);
        $item->method('getPrice')->willReturn(150000.0);
        $item->method('getWeight')->willReturn(0.2);
        $item->method('getName')->willReturn('Tee');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($address);
        $order->method('getItems')->willReturn([$item]);
        $order->method('getIncrementId')->willReturn('100000123');
        $order->method('getStoreId')->willReturn(1);
        $order->method('getShippingAmount')->willReturn(30000.0);

        $payload = (new PayloadBuilder($config))->build($order, 'b4cb5897-warehouse');

        $this->assertSame('100000123', $payload['custom_id']);
        $this->assertSame(4, $payload['shop_id']);
        $this->assertSame('b4cb5897-warehouse', $payload['warehouse_id']);
        $this->assertTrue($payload['items'][0]['one_time_product']);
        $this->assertSame('Tee', $payload['items'][0]['variation_info']['name']);
    }
}
