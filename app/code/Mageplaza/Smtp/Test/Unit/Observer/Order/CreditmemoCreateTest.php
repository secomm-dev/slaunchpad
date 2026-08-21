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
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Order\CreditmemoCreate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CreditmemoCreate::class)]
class CreditmemoCreateTest extends TestCase
{
    private EmailMarketing&MockObject $helperEmailMarketing;
    private LoggerInterface&MockObject $logger;

    private CreditmemoCreate $subject;

    protected function setUp(): void
    {
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);
        $this->logger               = $this->createMock(LoggerInterface::class);

        $this->subject = new CreditmemoCreate($this->helperEmailMarketing, $this->logger);
    }

    private function enableGate(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app');
    }

    // getId()/getCreatedAt()/getUpdatedAt()/getOrder() are declared on CreditmemoInterface
    // (vendor/magento/module-sales/Api/Data/CreditmemoInterface.php:385,598 + Creditmemo.php:245)
    // — no magic accessors involved, plain createMock suffices.
    private function makeCreditmemo(?int $id, string $createdAt, string $updatedAt): Creditmemo&MockObject
    {
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getId')->willReturn($id);
        $creditmemo->method('getCreatedAt')->willReturn($createdAt);
        $creditmemo->method('getUpdatedAt')->willReturn($updatedAt);

        return $creditmemo;
    }

    private function observerWithCreditmemo(Creditmemo $creditmemo): Observer&MockObject
    {
        $event    = new Event(['data_object' => $creditmemo]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }


    public function testExecuteSkipsWhenEmailMarketingDisabled(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(false);

        $observer = $this->createMock(Observer::class);
        $observer->expects($this->never())->method('getEvent');

        $this->subject->execute($observer);
    }

    public function testExecuteSkipsWhenSecretKeyMissing(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('');

        $observer = $this->createMock(Observer::class);
        $observer->expects($this->never())->method('getEvent');

        $this->subject->execute($observer);
    }

    public function testExecuteSkipsWhenAppIdMissing(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('');

        $observer = $this->createMock(Observer::class);
        $observer->expects($this->never())->method('getEvent');

        $this->subject->execute($observer);
    }

    public function testExecuteSyncsCreditmemoAndUpdatesCustomerWhenAllConditionsTrue(): void
    {
        $this->enableGate();

        $creditmemo = $this->makeCreditmemo(9, '2026-01-01 00:00:00', '2026-01-01 00:00:00');
        $order      = $this->createMock(Order::class);
        $order->method('getCustomerId')->willReturn(7);
        $creditmemo->method('getOrder')->willReturn($order);

        $this->helperEmailMarketing->expects($this->once())->method('sendOrderRequest')
            ->with($creditmemo, EmailMarketing::CREDITMEMO_URL);
        $this->helperEmailMarketing->expects($this->once())->method('updateCustomer')->with(7);

        $this->subject->execute($this->observerWithCreditmemo($creditmemo));
    }

    public function testExecuteUpdatesCustomerEvenWhenCreditmemoIsNotNew(): void
    {
        $this->enableGate();

        $creditmemo = $this->makeCreditmemo(9, '2026-01-01 00:00:00', '2026-01-02 00:00:00');
        $order      = $this->createMock(Order::class);
        $order->method('getCustomerId')->willReturn(3);
        $creditmemo->method('getOrder')->willReturn($order);

        $this->helperEmailMarketing->expects($this->never())->method('sendOrderRequest');
        $this->helperEmailMarketing->expects($this->once())->method('updateCustomer')->with(3);

        $this->subject->execute($this->observerWithCreditmemo($creditmemo));
    }

    public function testExecuteLogsExceptionFromUpdateCustomer(): void
    {
        $this->enableGate();

        $creditmemo = $this->makeCreditmemo(9, '2026-01-01 00:00:00', '2026-01-01 00:00:00');
        $order      = $this->createMock(Order::class);
        $order->method('getCustomerId')->willReturn(7);
        $creditmemo->method('getOrder')->willReturn($order);

        // syncCreditmemo() catches its own exception; force the failure on sendOrderRequest so
        // its internal try/catch is what logs it — execute() itself has no surrounding try/catch.
        $this->helperEmailMarketing->method('sendOrderRequest')->willThrowException(new Exception('boom'));
        $this->logger->expects($this->once())->method('critical')->with('boom');
        $this->helperEmailMarketing->expects($this->once())->method('updateCustomer')->with(7);

        $this->subject->execute($this->observerWithCreditmemo($creditmemo));
    }


    public function testSyncCreditmemoSendsOrderRequestForNewCreditmemo(): void
    {
        $creditmemo = $this->makeCreditmemo(9, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

        $this->helperEmailMarketing->expects($this->once())->method('sendOrderRequest')
            ->with($creditmemo, EmailMarketing::CREDITMEMO_URL);

        $this->subject->syncCreditmemo($creditmemo);
    }

    public function testSyncCreditmemoSkipsWhenDatesDoNotMatch(): void
    {
        $creditmemo = $this->makeCreditmemo(9, '2026-01-01 00:00:00', '2026-01-02 00:00:00');

        $this->helperEmailMarketing->expects($this->never())->method('sendOrderRequest');

        $this->subject->syncCreditmemo($creditmemo);
    }

    public function testSyncCreditmemoSkipsWhenIdIsMissing(): void
    {
        $creditmemo = $this->makeCreditmemo(null, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

        $this->helperEmailMarketing->expects($this->never())->method('sendOrderRequest');

        $this->subject->syncCreditmemo($creditmemo);
    }

    public function testSyncCreditmemoLogsExceptionAndDoesNotRethrow(): void
    {
        $creditmemo = $this->makeCreditmemo(9, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

        $this->helperEmailMarketing->method('sendOrderRequest')->willThrowException(new Exception('sync failed'));
        $this->logger->expects($this->once())->method('critical')->with('sync failed');

        $this->subject->syncCreditmemo($creditmemo);
    }
}
