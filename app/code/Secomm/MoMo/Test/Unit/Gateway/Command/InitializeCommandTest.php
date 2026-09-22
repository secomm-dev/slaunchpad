<?php
/**
 * Unit test for the gateway `initialize` command (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Gateway\Command;

use Magento\Framework\DataObject;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Gateway\Command\InitializeCommand;

/**
 * Verifies the initialize command: the order is created in pending_payment
 * and the core confirmation email is suppressed at placement (the
 * OrderFinalizer sends it itself, post-commit, under the dispatch claim).
 */
class InitializeCommandTest extends TestCase
{
    /**
     * The command sets the pending_payment state object, authorizes the
     * total due and suppresses the placement-time email.
     *
     * @return void
     */
    public function testExecutePreparesPendingPaymentAndSuppressesEmail(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getTotalDue')->willReturn(150000.0);
        $order->method('getBaseTotalDue')->willReturn(150000.0);
        $order->expects($this->once())->method('setCanSendNewEmailFlag')->with(false);

        $payment = $this->createMock(Payment::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->expects($this->once())->method('setAmountAuthorized')->with(150000.0);
        $payment->expects($this->once())->method('setBaseAmountAuthorized')->with(150000.0);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        $stateObject = new DataObject();
        (new InitializeCommand())->execute(['payment' => $paymentDO, 'stateObject' => $stateObject]);

        $this->assertSame(Order::STATE_PENDING_PAYMENT, $stateObject->getData('state'));
        $this->assertSame(Order::STATE_PENDING_PAYMENT, $stateObject->getData('status'));
        $this->assertFalse((bool)$stateObject->getData('is_notified'));
    }

    /**
     * A missing state object is an invalid command subject.
     *
     * @return void
     */
    public function testExecuteWithoutStateObjectThrows(): void
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($this->createMock(Payment::class));

        $this->expectException(\InvalidArgumentException::class);

        (new InitializeCommand())->execute(['payment' => $paymentDO]);
    }
}
