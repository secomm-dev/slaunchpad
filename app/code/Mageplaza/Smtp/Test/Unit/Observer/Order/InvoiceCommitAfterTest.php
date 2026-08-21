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
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order as ResourceOrder;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Order\InvoiceCommitAfter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(InvoiceCommitAfter::class)]
class InvoiceCommitAfterTest extends TestCase
{
    private EmailMarketing&MockObject $helperMock;
    private LoggerInterface&MockObject $loggerMock;
    private ResourceOrder&MockObject $resourceOrderMock;

    private InvoiceCommitAfter $subject;

    protected function setUp(): void
    {
        $this->helperMock        = $this->createMock(EmailMarketing::class);
        $this->loggerMock        = $this->createMock(LoggerInterface::class);
        $this->resourceOrderMock = $this->createMock(ResourceOrder::class);

        $this->subject = new InvoiceCommitAfter(
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
            'id'                                    => 100,
            'customer_id'                            => 55,
            'created_at'                             => '2026-07-14 10:00:00',
            'updated_at'                             => '2026-07-14 10:00:00',
            'mp_smtp_email_marketing_order_created'  => false,
        ], $overrides);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($data['id']);
        $order->method('getCustomerId')->willReturn($data['customer_id']);
        $order->method('getCreatedAt')->willReturn($data['created_at']);
        $order->method('getUpdatedAt')->willReturn($data['updated_at']);
        $order->method('getData')->willReturnCallback(
            static fn (string $key) => $data[$key] ?? null
        );

        return $order;
    }

    // getOrder() is declared on Invoice (vendor/magento/module-sales/Model/Order/Invoice.php:238);
    // getId()/getCreatedAt()/getUpdatedAt() are declared in AbstractModel — plain createMock suffices.
    private function makeInvoice(?int $id, string $createdAt, string $updatedAt, Order $order): Invoice&MockObject
    {
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getId')->willReturn($id);
        $invoice->method('getCreatedAt')->willReturn($createdAt);
        $invoice->method('getUpdatedAt')->willReturn($updatedAt);
        $invoice->method('getOrder')->willReturn($order);

        return $invoice;
    }

    // Event::getDataObject() resolves via DataObject::__call() -> getData('data_object'),
    // matching AbstractModel::_getEventData() (vendor/magento/framework/Model/AbstractModel.php:578).
    private function observerWithInvoice(Invoice $invoice): Observer&MockObject
    {
        $event    = new Event(['data_object' => $invoice]);
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

    public function testExecuteSyncsNewOrderAndNewInvoice(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'id'                                     => 100,
            'customer_id'                             => 55,
            'created_at'                              => '2026-07-14 10:00:00',
            'updated_at'                               => '2026-07-14 10:00:00',
            'mp_smtp_email_marketing_order_created'  => false,
        ]);
        $order->expects($this->once())->method('setData')
            ->with('mp_smtp_email_marketing_order_created', true);

        $invoice = $this->makeInvoice(7, '2026-07-14 11:00:00', '2026-07-14 11:00:00', $order);

        $calls = [];
        $this->helperMock->expects($this->exactly(2))->method('sendOrderRequest')
            ->willReturnCallback(function ($object, $url = '') use (&$calls): void {
                $calls[] = [$object, $url];
            });
        $this->helperMock->expects($this->exactly(2))->method('updateCustomer')->with(55);

        $connection = $this->stubUpdateFlagConnection();
        $connection->expects($this->once())->method('update')
            ->with('sales_order', ['mp_smtp_email_marketing_order_created' => 1], ['entity_id = ?' => 100]);

        $this->subject->execute($this->observerWithInvoice($invoice));

        $this->assertCount(2, $calls);
        $this->assertSame([$order, ''], $calls[0]);
        $this->assertSame([$invoice, EmailMarketing::INVOICE_URL], $calls[1]);
    }

    public function testExecuteSkipsOrderBlockWhenAlreadyFlagged(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'created_at'                              => '2026-07-14 10:00:00',
            'updated_at'                               => '2026-07-14 10:00:00',
            'mp_smtp_email_marketing_order_created'  => true,
        ]);
        $order->expects($this->never())->method('setData');

        $invoice = $this->makeInvoice(7, '2026-07-14 09:00:00', '2026-07-14 11:00:00', $order);

        $this->helperMock->expects($this->never())->method('sendOrderRequest');
        $this->helperMock->expects($this->once())->method('updateCustomer')->with(55);
        $this->resourceOrderMock->expects($this->never())->method('getConnection');

        $this->subject->execute($this->observerWithInvoice($invoice));
    }

    public function testExecuteSkipsOrderBlockWhenCreatedAtDiffersFromUpdatedAt(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'created_at'                              => '2026-07-14 10:00:00',
            'updated_at'                               => '2026-07-14 12:00:00',
            'mp_smtp_email_marketing_order_created'  => false,
        ]);
        $order->expects($this->never())->method('setData');

        $invoice = $this->makeInvoice(7, '2026-07-14 09:00:00', '2026-07-14 11:00:00', $order);

        $this->helperMock->expects($this->never())->method('sendOrderRequest');
        $this->helperMock->expects($this->once())->method('updateCustomer')->with(55);
        $this->resourceOrderMock->expects($this->never())->method('getConnection');

        $this->subject->execute($this->observerWithInvoice($invoice));
    }

    public function testExecuteSendsInvoiceRequestWhenInvoiceIsNew(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'mp_smtp_email_marketing_order_created' => true,
        ]);
        $invoice = $this->makeInvoice(7, '2026-07-14 11:00:00', '2026-07-14 11:00:00', $order);

        $this->helperMock->expects($this->once())->method('sendOrderRequest')
            ->with($invoice, EmailMarketing::INVOICE_URL);
        $this->helperMock->expects($this->once())->method('updateCustomer')->with(55);

        $this->subject->execute($this->observerWithInvoice($invoice));
    }

    public function testExecuteSkipsInvoiceRequestWhenInvoiceNotNew(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'mp_smtp_email_marketing_order_created' => true,
        ]);
        $invoice = $this->makeInvoice(7, '2026-07-14 09:00:00', '2026-07-14 11:00:00', $order);

        $this->helperMock->expects($this->never())->method('sendOrderRequest');
        $this->helperMock->expects($this->once())->method('updateCustomer')->with(55);

        $this->subject->execute($this->observerWithInvoice($invoice));
    }

    public function testExecuteSkipsInvoiceRequestWhenInvoiceHasNoId(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'mp_smtp_email_marketing_order_created' => true,
        ]);
        $invoice = $this->makeInvoice(null, '2026-07-14 11:00:00', '2026-07-14 11:00:00', $order);

        $this->helperMock->expects($this->never())->method('sendOrderRequest');
        $this->helperMock->expects($this->once())->method('updateCustomer')->with(55);

        $this->subject->execute($this->observerWithInvoice($invoice));
    }

    public function testExecuteLogsExceptionFromHelper(): void
    {
        $this->enableGate();

        $order = $this->makeOrder([
            'mp_smtp_email_marketing_order_created' => true,
        ]);
        $invoice = $this->makeInvoice(7, '2026-07-14 11:00:00', '2026-07-14 11:00:00', $order);

        $this->helperMock->method('sendOrderRequest')->willThrowException(new Exception('boom'));
        $this->loggerMock->expects($this->once())->method('critical')->with('boom');

        $this->subject->execute($this->observerWithInvoice($invoice));
    }
}
