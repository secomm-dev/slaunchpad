<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\ShippingContextFactory;

class ShippingContextFactoryTest extends TestCase
{
    public function testFromRateRequestMapsScalars(): void
    {
        $request = new RateRequest(['store_id' => 3, 'quote_id' => 42]);
        $context = (new ShippingContextFactory())->fromRateRequest($request, 'ghtk');

        $this->assertSame(3, $context->getStoreId());
        $this->assertSame('ghtk', $context->getCarrierCode());
        $this->assertSame(42, $context->getQuoteId());
        $this->assertNull($context->getWebsiteId());
        $this->assertNull($context->getSourceCode());
    }

    public function testFromRateRequestWithoutDataYieldsNulls(): void
    {
        $context = (new ShippingContextFactory())->fromRateRequest(new RateRequest(), 'ghtk');

        $this->assertNull($context->getStoreId());
        $this->assertNull($context->getQuoteId());
    }

    public function testFromShipmentMapsScalars(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getQuoteId')->willReturn(77);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getStoreId')->willReturn(5);
        $shipment->method('getOrder')->willReturn($order);

        $context = (new ShippingContextFactory())->fromShipment($shipment, 'ghtk');

        $this->assertSame(5, $context->getStoreId());
        $this->assertSame('ghtk', $context->getCarrierCode());
        $this->assertSame(77, $context->getQuoteId());
    }

    public function testFromShipmentWithoutOrderYieldsNullQuote(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getStoreId')->willReturn(null);
        $shipment->method('getOrder')->willReturn(null);

        $context = (new ShippingContextFactory())->fromShipment($shipment, 'ghtk');

        $this->assertNull($context->getStoreId());
        $this->assertNull($context->getQuoteId());
    }

    public function testCreateDefaults(): void
    {
        $context = (new ShippingContextFactory())->create();

        $this->assertNull($context->getStoreId());
        $this->assertNull($context->getWebsiteId());
        $this->assertNull($context->getCarrierCode());
        $this->assertNull($context->getQuoteId());
        $this->assertNull($context->getSourceCode());
    }
}
