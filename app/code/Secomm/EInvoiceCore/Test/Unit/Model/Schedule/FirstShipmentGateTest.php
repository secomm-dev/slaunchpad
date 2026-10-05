<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Test\Unit\Model\Schedule;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceCore\Model\Schedule\FirstShipmentGate;

/**
 * Unit tests for first-shipment eligibility gate.
 */
class FirstShipmentGateTest extends TestCase
{
    /**
     * System under test.
     *
     * @var FirstShipmentGate
     */
    private $gate;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->gate = new FirstShipmentGate();
    }

    /**
     * @return void
     */
    public function testIsFirstShipmentWhenCollectionSizeIsOne(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getSize')->willReturn(1);

        /** @var Order&MockObject $order */
        $order = $this->createMock(Order::class);
        $order->method('getShipmentsCollection')->willReturn($collection);

        self::assertTrue($this->gate->isFirstShipment($order));
    }

    /**
     * @return void
     */
    public function testIsNotFirstShipmentWhenMultipleShipments(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getSize')->willReturn(2);

        /** @var Order&MockObject $order */
        $order = $this->createMock(Order::class);
        $order->method('getShipmentsCollection')->willReturn($collection);

        self::assertFalse($this->gate->isFirstShipment($order));
    }
}
