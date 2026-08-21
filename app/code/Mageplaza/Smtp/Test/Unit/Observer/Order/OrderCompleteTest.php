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
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as ResourceOrder;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Order\OrderComplete;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(OrderComplete::class)]
class OrderCompleteTest extends TestCase
{
    private EmailMarketing&MockObject $helperMock;
    private LoggerInterface&MockObject $loggerMock;
    private ResourceOrder&MockObject $resourceOrderMock;

    private OrderComplete $subject;

    protected function setUp(): void
    {
        $this->helperMock        = $this->createMock(EmailMarketing::class);
        $this->loggerMock        = $this->createMock(LoggerInterface::class);
        $this->resourceOrderMock = $this->createMock(ResourceOrder::class);

        $this->subject = new OrderComplete(
            $this->helperMock,
            $this->loggerMock,
            $this->resourceOrderMock
        );
    }

    private function enableGate(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('secret');
        $this->helperMock->method('getAppID')->willReturn('app');
    }

    private function makeOrder(array $overrides = []): Order&MockObject
    {
        $data = array_merge([
            'id'                                     => 100,
            'customer_id'                             => 55,
            'customer_email'                          => 'customer@example.com',
            'created_at'                              => '2026-07-14 10:00:00',
            'updated_at'                               => '2026-07-14 10:00:00',
            'state'                                   => Order::STATE_PROCESSING,
            'status'                                  => 'processing',
            'mp_smtp_email_marketing_order_created'   => false,
            'mp_smtp_email_marketing_synced'          => false,
        ], $overrides);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($data['id']);
        $order->method('getCustomerId')->willReturn($data['customer_id']);
        $order->method('getCustomerEmail')->willReturn($data['customer_email']);
        $order->method('getCreatedAt')->willReturn($data['created_at']);
        $order->method('getUpdatedAt')->willReturn($data['updated_at']);
        $order->method('getState')->willReturn($data['state']);
        $order->method('getStatus')->willReturn($data['status']);
        $order->method('getData')->willReturnCallback(
            static fn (string $key) => $data[$key] ?? null
        );

        return $order;
    }

    private function observerWithOrder(Order $order): Observer&MockObject
    {
        $event    = new Event(['order' => $order]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    private function stubUpdateFlagConnection(): AdapterInterface&MockObject
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->resourceOrderMock->method('getConnection')->willReturn($connection);
        $this->resourceOrderMock->method('getMainTable')->willReturn('sales_order');

        return $connection;
    }


    public function testExecuteNoopWhenEmailMarketingDisabled(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(false);

        $observer = $this->createMock(Observer::class);
        $observer->expects($this->never())->method('getEvent');

        $this->subject->execute($observer);
    }

    public function testExecuteNoopWhenSecretKeyMissing(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('');

        $observer = $this->createMock(Observer::class);
        $observer->expects($this->never())->method('getEvent');

        $this->subject->execute($observer);
    }

    public function testExecuteNoopWhenAppIdMissing(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('secret');
        $this->helperMock->method('getAppID')->willReturn('');

        $observer = $this->createMock(Observer::class);
        $observer->expects($this->never())->method('getEvent');

        $this->subject->execute($observer);
    }

    public function testExecuteSyncsNewlyCreatedOrder(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'id'                                    => 100,
            'customer_id'                            => 55,
            'created_at'                             => '2026-07-14 10:00:00',
            'updated_at'                              => '2026-07-14 10:00:00',
            'state'                                  => Order::STATE_PROCESSING,
            'mp_smtp_email_marketing_order_created'  => false,
            'mp_smtp_email_marketing_synced'         => false,
        ]);
        $order->expects($this->once())->method('setData')
            ->with('mp_smtp_email_marketing_order_created', true);

        // syncOrder() calls sendOrderRequest($order) with no $url — PHPUnit records the
        // invocation with the default filled in, so the matcher needs both arguments.
        $this->helperMock->expects($this->once())->method('sendOrderRequest')->with($order, '');
        $this->helperMock->expects($this->once())->method('updateCustomer')->with(55);
        $this->helperMock->expects($this->never())->method('updateOrderStatusRequest');

        $connection = $this->stubUpdateFlagConnection();
        $connection->expects($this->once())->method('update')
            ->with('sales_order', ['mp_smtp_email_marketing_order_created' => 1], ['entity_id = ?' => 100]);

        $this->subject->execute($this->observerWithOrder($order));
    }

    public function testExecuteSkipsStatusUpdateWhenStateIsNew(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'state'                                  => Order::STATE_NEW,
            'mp_smtp_email_marketing_order_created'  => true,
            'mp_smtp_email_marketing_synced'         => false,
        ]);
        $order->expects($this->never())->method('setData');

        $this->helperMock->expects($this->never())->method('updateOrderStatusRequest');
        $this->helperMock->expects($this->never())->method('sendOrderRequest');
        $this->resourceOrderMock->expects($this->never())->method('getConnection');

        $this->subject->execute($this->observerWithOrder($order));
    }

    public function testExecuteUpdatesOrderStatusWhenStateNotNewOrComplete(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'id'                                     => 101,
            'status'                                 => 'processing',
            'state'                                  => Order::STATE_PROCESSING,
            'customer_email'                         => 'jane@example.com',
            'created_at'                              => '2026-07-14 10:00:00',
            'updated_at'                               => '2026-07-14 12:00:00',
            'mp_smtp_email_marketing_order_created'  => true,
            'mp_smtp_email_marketing_synced'         => false,
        ]);

        $this->helperMock->method('formatDate')->willReturnArgument(0);
        $this->helperMock->expects($this->once())->method('updateOrderStatusRequest')->with([
            'id'         => 101,
            'status'     => 'processing',
            'state'      => Order::STATE_PROCESSING,
            'email'      => 'jane@example.com',
            'is_utc'     => true,
            'created_at' => '2026-07-14 10:00:00',
            'updated_at' => '2026-07-14 12:00:00',
        ]);
        $this->helperMock->expects($this->never())->method('sendOrderRequest');

        $this->subject->execute($this->observerWithOrder($order));
    }

    public function testExecuteSendsCompleteOrderRequestWhenNotYetSynced(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'id'                                     => 102,
            'state'                                  => Order::STATE_COMPLETE,
            'created_at'                              => '2026-07-14 10:00:00',
            'updated_at'                               => '2026-07-14 12:00:00',
            'mp_smtp_email_marketing_order_created'  => true,
            'mp_smtp_email_marketing_synced'         => false,
        ]);

        $this->helperMock->expects($this->never())->method('updateOrderStatusRequest');
        $this->helperMock->expects($this->once())->method('sendOrderRequest')
            ->with($order, EmailMarketing::ORDER_COMPLETE_URL);

        $connection = $this->stubUpdateFlagConnection();
        $connection->expects($this->once())->method('update')
            ->with('sales_order', ['mp_smtp_email_marketing_synced' => 1], ['entity_id = ?' => 102]);

        $this->subject->execute($this->observerWithOrder($order));
    }

    public function testExecuteSkipsCompleteOrderRequestWhenAlreadySynced(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'state'                                  => Order::STATE_COMPLETE,
            'mp_smtp_email_marketing_order_created'  => true,
            'mp_smtp_email_marketing_synced'         => true,
        ]);

        $this->helperMock->expects($this->never())->method('updateOrderStatusRequest');
        $this->helperMock->expects($this->never())->method('sendOrderRequest');
        $this->resourceOrderMock->expects($this->never())->method('getConnection');

        $this->subject->execute($this->observerWithOrder($order));
    }

    public function testExecuteLogsExceptionFromHelper(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'state'                                  => Order::STATE_PROCESSING,
            'created_at'                              => '2026-07-14 10:00:00',
            'updated_at'                               => '2026-07-14 10:00:00',
            'mp_smtp_email_marketing_order_created'  => false,
        ]);

        // syncOrder() catches its own exception; force the failure on updateCustomer so the
        // outer try/catch in execute() is what logs it.
        $this->helperMock->method('updateCustomer')->willThrowException(new Exception('sync failed'));
        $this->loggerMock->expects($this->once())->method('critical')->with('sync failed');

        $this->subject->execute($this->observerWithOrder($order));
    }


    public function testSyncOrderDelegatesToSendOrderRequest(): void
    {
        $order = $this->createMock(Order::class);

        $this->helperMock->expects($this->once())->method('sendOrderRequest')->with($order, '');

        $this->subject->syncOrder($order);
    }

    public function testSyncOrderLogsExceptionAndDoesNotRethrow(): void
    {
        $order = $this->createMock(Order::class);

        $this->helperMock->method('sendOrderRequest')->willThrowException(new Exception('boom'));
        $this->loggerMock->expects($this->once())->method('critical')->with('boom');

        $this->subject->syncOrder($order);
    }


    public function testUpdateFlagWritesFlagColumnForEntity(): void
    {
        $connection = $this->stubUpdateFlagConnection();
        $connection->expects($this->once())->method('update')
            ->with('sales_order', ['mp_smtp_email_marketing_synced' => 1], ['entity_id = ?' => 42]);

        $this->subject->updateFlag(42, 'mp_smtp_email_marketing_synced');
    }
}
