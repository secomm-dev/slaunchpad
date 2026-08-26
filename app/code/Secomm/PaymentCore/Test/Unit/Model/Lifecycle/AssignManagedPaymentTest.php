<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PaymentCore\Test\Unit\Model\Lifecycle;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime as DateLib;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\PaymentCore\Api\Data\PaymentInterface;
use Secomm\PaymentCore\Api\PaymentRepositoryInterface;
use Secomm\PaymentCore\Model\Config;
use Secomm\PaymentCore\Model\Lifecycle\AssignManagedPayment;
use Secomm\PaymentCore\Model\PaymentFactory;

/**
 * FEAT-CSWYEJ / TASK-M20PT6 — snapshot assignment at place order (SPEC §4.1,
 * AC-001→AC-003, AC-014; DEC D4/D5).
 */
class AssignManagedPaymentTest extends TestCase
{
    private Config&MockObject $config;
    private PaymentRepositoryInterface&MockObject $repository;
    private PaymentFactory&MockObject $paymentFactory;
    private DateLib&MockObject $dateLib;
    private LoggerInterface&MockObject $logger;
    private OrderInterface&MockObject $order;
    private OrderPaymentInterface&MockObject $payment;
    private PaymentInterface&MockObject $newRecord;

    private AssignManagedPayment $service;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->paymentFactory = $this->createMock(PaymentFactory::class);
        $this->dateLib = $this->createMock(DateLib::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(OrderPaymentInterface::class);
        $this->newRecord = $this->createMock(PaymentInterface::class);

        $store = $this->createConfiguredMock(StoreInterface::class, ['getWebsiteId' => 1]);
        $this->order->method('getStore')->willReturn($store);
        $this->order->method('getEntityId')->willReturn(42);
        $this->order->method('getStoreId')->willReturn(1);
        $this->order->method('getIncrementId')->willReturn('000000042');
        $this->order->method('getPayment')->willReturn($this->payment);
        $this->payment->method('getMethod')->willReturn('vnpay');
        $this->newRecord->method('setOrderId')->willReturnSelf();
        $this->newRecord->method('setStoreId')->willReturnSelf();
        $this->newRecord->method('setMethodCode')->willReturnSelf();
        $this->newRecord->method('setExpiresAt')->willReturnSelf();
        $this->newRecord->method('setStatus')->willReturnSelf();

        $this->service = new AssignManagedPayment(
            $this->config,
            $this->repository,
            $this->paymentFactory,
            $this->dateLib,
            $this->logger
        );
    }

    public function testManagedOrderGetsSnapshotRecord(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isManagedMethod')->with('vnpay', 1)->willReturn(true);
        $this->config->method('resolveExpiryMinutes')->with('vnpay', 1)->willReturn(30);
        $this->repository->method('getByOrderId')->willThrowException(new NoSuchEntityException(__('none')));
        $this->paymentFactory->method('create')->willReturn($this->newRecord);
        $this->dateLib->method('gmtTimestamp')->willReturn(1000000000);
        $this->dateLib->method('gmtDate')->with('Y-m-d H:i:s', 1000000000 + 1800)->willReturn('2031-09-09 01:46:40');

        // Snapshot: expires_at = now + resolved minutes (AC-002, DEC D4)
        $this->newRecord->expects($this->once())->method('setExpiresAt')->with('2031-09-09 01:46:40');
        $this->newRecord->expects($this->once())->method('setStatus')->with(PaymentInterface::STATUS_ACTIVE);
        $this->repository->expects($this->once())->method('save');
        $this->service->execute($this->order);
    }

    public function testDisabledCoreCreatesNothing(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->repository->expects($this->never())->method('save');
        $this->service->execute($this->order);
    }

    public function testUnmanagedMethodCreatesNothing(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isManagedMethod')->willReturn(false);
        $this->repository->expects($this->never())->method('save');
        $this->service->execute($this->order);
    }

    public function testAlreadyTrackedOrderIsSkipped(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isManagedMethod')->willReturn(true);
        $this->repository->method('getByOrderId')->willReturn($this->newRecord);
        $this->repository->expects($this->never())->method('save');
        $this->service->execute($this->order);
    }

    public function testRepositoryFailureIsSwallowedAndLogged(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isManagedMethod')->willReturn(true);
        $this->repository->method('getByOrderId')->willThrowException(new NoSuchEntityException(__('none')));
        $this->paymentFactory->method('create')->willReturn($this->newRecord);
        $this->dateLib->method('gmtTimestamp')->willReturn(0);
        $this->repository->method('save')->willThrowException(new \RuntimeException('db down'));
        $this->logger->expects($this->once())->method('error');
        $this->service->execute($this->order);
    }
}
