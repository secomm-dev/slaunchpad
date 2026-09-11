<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Service\SuccessSessionPreparer;

/**
 * SuccessSessionPreparer (corrective TASK-EDS9T5 Blocker 4): mirrors the
 * core Onepage::saveOrder session updates so the standard success page
 * validates after the payment-first redirect flow. This is the ONLY writer
 * of the ZaloPay success-session state — and it lives on the BROWSER Return
 * path, never on the IPN path.
 */
class SuccessSessionPreparerTest extends TestCase
{
    /**
     * The whole Last* set is written exactly as Onepage::saveOrder does.
     *
     * @return void
     */
    public function testPrepareMirrorsOnepageSaveOrderSessionWrites(): void
    {
        $session = new SessionStub();
        $preparer = new SuccessSessionPreparer($session, $this->createMock(\Psr\Log\LoggerInterface::class));

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(77);
        $order->method('getIncrementId')->willReturn('000000123');
        $order->method('getState')->willReturn('processing');

        $preparer->prepare($this->newAttempt(), $order);

        $this->assertSame(42, $session->calls['last_quote_id']);
        $this->assertSame(42, $session->calls['last_success_quote_id']);
        $this->assertSame(77, $session->calls['last_order_id']);
        $this->assertSame('000000123', $session->calls['last_real_order_id']);
        $this->assertSame('processing', $session->calls['last_order_status']);
    }

    /**
     * A failing session backend is swallowed (session state must never break
     * an already-finalized order's customer flow) and logged.
     *
     * @return void
     */
    public function testPrepareSwallowsSessionFailure(): void
    {
        $session = new class extends SessionStub {
            /**
             * @return never
             */
            public function setLastQuoteId($quoteId): static
            {
                throw new \RuntimeException('session storage down');
            }
        };
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('error');
        $preparer = new SuccessSessionPreparer($session, $logger);

        $preparer->prepare($this->newAttempt(), $this->createMock(OrderInterface::class));

        // No exception surfaced.
        $this->assertTrue(true);
    }

    /**
     * @return PaymentAttempt
     */
    private function newAttempt(): PaymentAttempt
    {
        $attempt = new PaymentAttempt(
            $this->createMock(\Magento\Framework\Model\Context::class),
            $this->createMock(\Magento\Framework\Registry::class),
            $this->newResourceStub()
        );
        $attempt->setEntityId(9);
        $attempt->setQuoteId(42);
        $attempt->setReservedOrderId('000000123');
        $attempt->setAppTransId('260826_1000_000000123');
        $attempt->setAmount(100000);
        $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_FINALIZED);

        return $attempt;
    }

    /**
     * @return \Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource|\PHPUnit\Framework\MockObject\MockObject
     */
    private function newResourceStub()
    {
        $resource = $this->getMockBuilder(\Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resource->method('getIdFieldName')->willReturn(PaymentAttemptInterface::ENTITY_ID);

        return $resource;
    }
}
