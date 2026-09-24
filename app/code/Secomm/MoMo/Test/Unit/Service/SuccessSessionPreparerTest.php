<?php
/**
 * Unit test for the checkout success session preparer (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Model\PaymentAttempt;
use Secomm\MoMo\Service\SuccessSessionPreparer;

/**
 * Verifies AC8: the 5 checkout success-session keys the standard success
 * page validates are rebuilt from the attempt + bound order — and nothing
 * else (the quote stays recoverable: no clearHelperData).
 */
class SuccessSessionPreparerTest extends TestCase
{
    /**
     * The preparer mirrors the core Onepage::saveOrder session updates.
     *
     * @return void
     */
    public function testPrepareRebuildsTheFiveSuccessKeys(): void
    {
        $session = new SessionStub();
        $preparer = new SuccessSessionPreparer($session, $this->createMock(\Psr\Log\LoggerInterface::class));

        $attempt = new PaymentAttempt($this->createMock(Context::class), $this->createMock(Registry::class));
        $attempt->setQuoteId(42);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(5001);
        $order->method('getIncrementId')->willReturn('200000001');
        $order->method('getState')->willReturn('processing');

        $preparer->prepare($attempt, $order);

        $this->assertSame(42, $session->calls['last_quote_id']);
        $this->assertSame(42, $session->calls['last_success_quote_id']);
        $this->assertSame(5001, $session->calls['last_order_id']);
        $this->assertSame('200000001', $session->calls['last_real_order_id']);
        $this->assertSame('processing', $session->calls['last_order_status']);
        $this->assertArrayNotHasKey('clearHelperData', $session->calls);
    }
}
