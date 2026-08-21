<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Observer\Order;

use Exception;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Shipment;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Order\ShipmentCreate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ShipmentCreate::class)]
class ShipmentCreateTest extends TestCase
{
    private EmailMarketing&MockObject $helperEmailMarketing;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createSut(): ShipmentCreate
    {
        return new ShipmentCreate($this->helperEmailMarketing, $this->logger);
    }

    private function enableGate(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app');
    }

    // getId() and getCreatedAt()/getUpdatedAt() are declared methods (AbstractModel /
    // Shipment.php:652,750) — no magic accessors involved, plain createMock suffices.
    private function createShipment(?int $id, string $createdAt, string $updatedAt): Shipment&MockObject
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getId')->willReturn($id);
        $shipment->method('getCreatedAt')->willReturn($createdAt);
        $shipment->method('getUpdatedAt')->willReturn($updatedAt);

        return $shipment;
    }

    private function observerWithShipment(Shipment $shipment): Observer&MockObject
    {
        $event = new Event(['data_object' => $shipment]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    public function testExecuteSkipsWhenEmailMarketingDisabled(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperEmailMarketing->expects($this->never())->method('sendOrderRequest');

        $shipment = $this->createShipment(5, '2026-01-01 00:00:00', '2026-01-01 00:00:00');
        $this->createSut()->execute($this->observerWithShipment($shipment));
    }

    public function testExecuteSkipsWhenSecretKeyMissing(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('');
        $this->helperEmailMarketing->expects($this->never())->method('sendOrderRequest');

        $shipment = $this->createShipment(5, '2026-01-01 00:00:00', '2026-01-01 00:00:00');
        $this->createSut()->execute($this->observerWithShipment($shipment));
    }

    public function testExecuteSkipsWhenAppIdMissing(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('');
        $this->helperEmailMarketing->expects($this->never())->method('sendOrderRequest');

        $shipment = $this->createShipment(5, '2026-01-01 00:00:00', '2026-01-01 00:00:00');
        $this->createSut()->execute($this->observerWithShipment($shipment));
    }

    public function testExecuteSendsOrderRequestForNewShipment(): void
    {
        $this->enableGate();
        $shipment = $this->createShipment(5, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

        $this->helperEmailMarketing->expects($this->once())
            ->method('sendOrderRequest')
            ->with($shipment, EmailMarketing::SHIPMENT_URL);

        $this->createSut()->execute($this->observerWithShipment($shipment));
    }

    public function testExecuteSkipsWhenShipmentAlreadyUpdated(): void
    {
        $this->enableGate();
        $shipment = $this->createShipment(5, '2026-01-01 00:00:00', '2026-01-02 00:00:00');

        $this->helperEmailMarketing->expects($this->never())->method('sendOrderRequest');

        $this->createSut()->execute($this->observerWithShipment($shipment));
    }

    public function testExecuteSkipsWhenShipmentHasNoId(): void
    {
        $this->enableGate();
        $shipment = $this->createShipment(null, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

        $this->helperEmailMarketing->expects($this->never())->method('sendOrderRequest');

        $this->createSut()->execute($this->observerWithShipment($shipment));
    }

    public function testExecuteCatchesExceptionAndLogsCritical(): void
    {
        $this->enableGate();
        $shipment = $this->createShipment(5, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

        $this->helperEmailMarketing->method('sendOrderRequest')
            ->willThrowException(new Exception('boom'));
        $this->logger->expects($this->once())->method('critical')->with('boom');

        $this->createSut()->execute($this->observerWithShipment($shipment));
    }
}
