<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PaymentCore\Test\Unit\Model\Lifecycle;

use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime as DateLib;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\PaymentCore\Api\Data\PaymentInterface;
use Secomm\PaymentCore\Api\PaymentRepositoryInterface;
use Secomm\PaymentCore\Model\Lifecycle\CancelExpiredOrder;

/**
 * FEAT-CSWYEJ / DEC-FEATCSWYEJ-004 — expired + still pending = not paid → cancel.
 * Provider verify removed from the cron; state is the payment truth.
 */
class CancelExpiredOrderTest extends TestCase
{
    private OrderManagementInterface&MockObject $orderManagement;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private PaymentRepositoryInterface&MockObject $paymentRepository;
    private LockManagerInterface&MockObject $lockManager;
    private DateLib&MockObject $dateLib;
    private LoggerInterface&MockObject $logger;
    private PaymentInterface&MockObject $record;
    private OrderInterface&MockObject $order;

    private CancelExpiredOrder $service;

    protected function setUp(): void
    {
        $this->orderManagement = $this->createMock(OrderManagementInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->dateLib = $this->createMock(DateLib::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->record = $this->createMock(PaymentInterface::class);
        $this->order = $this->createMock(OrderInterface::class);

        $this->record->method('getOrderId')->willReturn(42);
        $this->record->method('getMethodCode')->willReturn('vnpay');
        $this->orderRepository->method('get')->with(42)->willReturn($this->order);
        $this->lockManager->method('lock')->willReturn(true);

        $this->service = new CancelExpiredOrder(
            $this->orderManagement,
            $this->orderRepository,
            $this->paymentRepository,
            $this->lockManager,
            $this->dateLib,
            $this->logger
        );
    }

    private function orderState(string $state, bool $canCancel = true): void
    {
        $this->order->method('getState')->willReturn($state);
        $this->order->method('canCancel')->willReturn($canCancel);
    }

    public function testExpiredPendingCancelsViaLifecycle(): void
    {
        $this->orderState(Order::STATE_NEW);
        $this->orderManagement->expects($this->once())->method('cancel')->with(42);
        $this->record->expects($this->once())->method('setStatus')->with(PaymentInterface::STATUS_CANCELED);
        $this->service->process($this->record);
    }

    public function testProcessingStateResolvesCompleted(): void
    {
        $this->orderState(Order::STATE_PROCESSING);
        $this->orderManagement->expects($this->never())->method('cancel');
        $this->record->expects($this->once())->method('setStatus')->with(PaymentInterface::STATUS_COMPLETED);
        $this->service->process($this->record);
    }

    public function testCanceledStateResolvesCanceled(): void
    {
        $this->orderState(Order::STATE_CANCELED);
        $this->orderManagement->expects($this->never())->method('cancel');
        $this->record->expects($this->once())->method('setStatus')->with(PaymentInterface::STATUS_CANCELED);
        $this->service->process($this->record);
    }

    public function testCancelThrowKeepsRecordActiveAndLogs(): void
    {
        $this->orderState(Order::STATE_NEW);
        $this->orderManagement->method('cancel')->willThrowException(new \RuntimeException('boom'));
        $this->record->expects($this->never())->method('setStatus');
        $this->logger->expects($this->once())->method('error');
        $this->service->process($this->record);
    }

    public function testLockBusySkipsEverything(): void
    {
        $this->lockManager->method('lock')->willReturn(false);
        $this->orderManagement->expects($this->never())->method('cancel');
        $this->service->process($this->record);
    }

    public function testCanCancelFalseSkipsCancel(): void
    {
        $this->orderState(Order::STATE_NEW, false);
        $this->orderManagement->expects($this->never())->method('cancel');
        $this->service->process($this->record);
    }
}
