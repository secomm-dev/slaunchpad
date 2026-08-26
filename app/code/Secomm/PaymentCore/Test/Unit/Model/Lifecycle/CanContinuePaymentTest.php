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
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\PaymentCore\Api\Data\PaymentInterface;
use Secomm\PaymentCore\Api\PaymentRepositoryInterface;
use Secomm\PaymentCore\Model\Config;
use Secomm\PaymentCore\Model\Lifecycle\CanContinuePayment;

/**
 * FEAT-CSWYEJ / TASK-M20PT6 — every denial branch of the Continue Payment
 * guard (SPEC §4.1/§4.8, AC-004→AC-006, AC-014).
 */
class CanContinuePaymentTest extends TestCase
{
    private Config&MockObject $config;
    private PaymentRepositoryInterface&MockObject $repository;
    private DateLib&MockObject $dateLib;
    private OrderInterface&MockObject $order;
    private OrderPaymentInterface&MockObject $payment;
    private PaymentInterface&MockObject $record;

    private CanContinuePayment $service;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->dateLib = $this->createMock(DateLib::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(OrderPaymentInterface::class);
        $this->record = $this->createMock(PaymentInterface::class);

        $store = $this->createConfiguredMock(StoreInterface::class, ['getWebsiteId' => 1]);
        $this->order->method('getStore')->willReturn($store);
        $this->order->method('getEntityId')->willReturn(42);
        $this->order->method('getPayment')->willReturn($this->payment);
        $this->payment->method('getMethod')->willReturn('vnpay');

        $this->service = new CanContinuePayment($this->config, $this->repository, $this->dateLib);
    }

    private function allowThroughConfig(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isManagedMethod')->willReturn(true);
        $this->config->method('isContinueDisabled')->willReturn(false);
    }

    private function allowThroughRecord(string $expiresAt = '2999-01-01 00:00:00'): void
    {
        $this->repository->method('getByOrderId')->with(42)->willReturn($this->record);
        $this->record->method('getStatus')->willReturn(PaymentInterface::STATUS_ACTIVE);
        $this->record->method('getExpiresAt')->willReturn($expiresAt);
    }

    public function testAllGuardsPass(): void
    {
        $this->allowThroughConfig();
        $this->allowThroughRecord();
        $this->order->method('getState')->willReturn(Order::STATE_NEW);
        $this->order->method('canCancel')->willReturn(true);
        $this->order->method('getTotalDue')->willReturn(100.0);
        $this->dateLib->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $this->assertSame(CanContinuePayment::REASON_OK, $this->service->denyReason($this->order));
        $this->assertTrue($this->service->canContinue($this->order));
    }

    public function testCoreDisabledDenies(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->assertSame(CanContinuePayment::REASON_CORE_DISABLED, $this->service->denyReason($this->order));
    }

    public function testMethodNotManagedDenies(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isManagedMethod')->willReturn(false);
        $this->assertSame(CanContinuePayment::REASON_METHOD_NOT_MANAGED, $this->service->denyReason($this->order));
    }

    public function testContinueDisabledDenies(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isManagedMethod')->willReturn(true);
        $this->config->method('isContinueDisabled')->willReturn(true);
        $this->assertSame(CanContinuePayment::REASON_CONTINUE_DISABLED, $this->service->denyReason($this->order));
    }

    public function testNoRecordDenies(): void
    {
        $this->allowThroughConfig();
        $this->repository->method('getByOrderId')->willThrowException(
            $this->createConfiguredMock(NoSuchEntityException::class, [])
        );
        $this->assertSame(CanContinuePayment::REASON_NO_RECORD, $this->service->denyReason($this->order));
    }

    public function testExpiredRecordDenies(): void
    {
        $this->allowThroughConfig();
        $this->allowThroughRecord('2025-01-01 00:00:00');
        $this->dateLib->method('gmtDate')->willReturn('2026-08-25 00:00:00');
        $this->assertSame(CanContinuePayment::REASON_EXPIRED, $this->service->denyReason($this->order));
    }

    public function testResolvedRecordDenies(): void
    {
        $this->allowThroughConfig();
        $this->repository->method('getByOrderId')->willReturn($this->record);
        $this->record->method('getStatus')->willReturn(PaymentInterface::STATUS_CANCELED);
        $this->record->method('getExpiresAt')->willReturn('2999-01-01 00:00:00');
        $this->assertSame(CanContinuePayment::REASON_NOT_ACTIVE, $this->service->denyReason($this->order));
    }

    public function testNonNewStateDenies(): void
    {
        $this->allowThroughConfig();
        $this->allowThroughRecord();
        $this->dateLib->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $this->order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $this->assertSame(CanContinuePayment::REASON_STATE, $this->service->denyReason($this->order));
    }

    public function testZeroTotalDueDenies(): void
    {
        $this->allowThroughConfig();
        $this->allowThroughRecord();
        $this->dateLib->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $this->order->method('getState')->willReturn(Order::STATE_NEW);
        $this->order->method('canCancel')->willReturn(true);
        $this->order->method('getTotalDue')->willReturn(0.0);
        $this->assertSame(CanContinuePayment::REASON_NOT_PAYABLE, $this->service->denyReason($this->order));
    }
}
