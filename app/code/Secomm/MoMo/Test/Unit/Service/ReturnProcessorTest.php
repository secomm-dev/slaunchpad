<?php
/**
 * Unit test for the browser Return processor (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Model\PaymentAttempt;
use Secomm\MoMo\Service\OrderFinalizer;
use Secomm\MoMo\Service\PaymentAttemptLifecycle;
use Secomm\MoMo\Service\ReturnProcessor;
use Secomm\MoMo\Service\SuccessSessionPreparer;

/**
 * Verifies that the browser return NEVER trusts browser params (AC7): the
 * server-side v2/query owns every payment-state decision, the attempt
 * lookup is by echoed order_ref only, 7002 is non-terminal and the AC8
 * success session only follows an authoritative finalization.
 */
class ReturnProcessorTest extends TestCase
{
    private PaymentAttemptRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;

    private CommandPoolInterface&\PHPUnit\Framework\MockObject\MockObject $commandPool;

    private CommandInterface&\PHPUnit\Framework\MockObject\MockObject $queryCommand;

    private OrderFinalizer&\PHPUnit\Framework\MockObject\MockObject $orderFinalizer;

    private PaymentAttemptLifecycle&\PHPUnit\Framework\MockObject\MockObject $lifecycle;

    private SuccessSessionPreparer&\PHPUnit\Framework\MockObject\MockObject $successSessionPreparer;

    private ReturnProcessor $processor;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->queryCommand = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->with('query_transaction')->willReturn($this->queryCommand);
        $this->orderFinalizer = $this->createMock(OrderFinalizer::class);
        $this->lifecycle = $this->createMock(PaymentAttemptLifecycle::class);
        $this->successSessionPreparer = $this->createMock(SuccessSessionPreparer::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->processor = new ReturnProcessor(
            $this->repository,
            $this->commandPool,
            $this->orderFinalizer,
            $this->lifecycle,
            $this->successSessionPreparer,
            $logger
        );
    }

    /**
     * No reference, no processing.
     *
     * @return void
     */
    public function testMissingOrderRefThrows(): void
    {
        $this->repository->expects($this->never())->method('getByOrderRef');

        $this->expectException(LocalizedException::class);

        $this->processor->process([]);
    }

    /**
     * An order_ref with no attempt row is a customer-safe refusal.
     *
     * @return void
     */
    public function testUnknownReferenceThrows(): void
    {
        $this->repository->method('getByOrderRef')->willReturn(null);
        $this->commandPool->expects($this->never())->method('get');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('session not found');

        $this->processor->process(['orderId' => 'UNKNOWN']);
    }

    /**
     * A signature-invalid query response fails INSIDE the command: the
     * browser hit mutates nothing.
     *
     * @return void
     */
    public function testQueryFailureMutatesNothing(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->queryCommand->method('execute')->willThrowException(new CommandException(__('bad signature')));
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('could not be verified');

        $this->processor->process(['orderId' => 'MOMOREF']);
    }

    /**
     * 7002 (still processing) is non-terminal: no lifecycle mutation, the
     * customer is asked to check back.
     *
     * @return void
     */
    public function testStillProcessingIsNonTerminal(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->queryCommand->method('execute')->willReturn($this->queryResult(['resultCode' => 7002]));
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('still being processed');

        $this->processor->process(['orderId' => 'MOMOREF']);
    }

    /**
     * 7000 (transaction not yet paid) is ALSO non-terminal (same class as
     * 7002 in MoMo's result-code table): no lifecycle mutation, no failure
     * recorded, the customer is asked to check back.
     *
     * @return void
     */
    public function testNotYetPaidIsNonTerminal(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->queryCommand->method('execute')->willReturn($this->queryResult(['resultCode' => 7000]));
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('still being processed');

        $this->processor->process(['orderId' => 'MOMOREF']);
    }

    /**
     * An authoritative PAID with a mismatching amount quarantines and
     * refuses the customer success path (AC4).
     *
     * @return void
     */
    public function testAmountMismatchRefuses(): void
    {
        $attempt = $this->attempt('active');
        $this->repository->method('getByOrderRef')->willReturn($attempt);
        $this->queryCommand->method('execute')->willReturn(
            $this->queryResult(['resultCode' => 0, 'amount' => 200000, 'transId' => '987654321'])
        );
        $this->lifecycle->expects($this->once())->method('recordAmountMismatch')
            ->with('MOMOREF', 200000, 'Return', '987654321');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('amount mismatch');

        $this->processor->process(['orderId' => 'MOMOREF']);
    }

    /**
     * Verified money without a positive transId is quarantined — never an
     * order from the browser return.
     *
     * @return void
     */
    public function testMissingTransIdQuarantines(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->queryCommand->method('execute')->willReturn(
            $this->queryResult(['resultCode' => 0, 'amount' => 150000, 'transId' => ''])
        );
        $this->lifecycle->expects($this->once())->method('recordProviderIdentityUnavailable')
            ->with('MOMOREF', 'Return-query');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('could not match your payment');

        $this->processor->process(['orderId' => 'MOMOREF']);
    }

    /**
     * The authoritative paid path: PAID via the lifecycle, order finalized,
     * the 5 checkout success keys rebuilt (AC8), success page returned.
     *
     * @return void
     */
    public function testVerifiedPaidFinalizesAndPreparesSuccessSession(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->queryCommand->method('execute')->willReturn(
            $this->queryResult(['resultCode' => 0, 'amount' => 150000, 'transId' => '987654321'])
        );
        $paid = $this->attempt('paid');
        $this->lifecycle->method('recordVerifiedPaid')->willReturn($paid);
        $order = $this->createMock(OrderInterface::class);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($paid, '987654321')->willReturn($order);
        $this->successSessionPreparer->expects($this->once())->method('prepare')->with($paid, $order);

        $this->assertSame(
            'checkout/onepage/success',
            $this->processor->process(['orderId' => 'MOMOREF'])
        );
    }

    /**
     * PAID evidence arriving on a terminal (FAILED/EXPIRED) attempt keeps
     * the terminal state: no order, manual reconciliation.
     *
     * @return void
     */
    public function testPaidEvidenceOnTerminalAttemptRefuses(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('failed'));
        $this->queryCommand->method('execute')->willReturn(
            $this->queryResult(['resultCode' => 0, 'amount' => 150000, 'transId' => '987654321'])
        );
        $kept = $this->attempt('failed', ['provider_transaction_id' => '987654321']);
        $this->lifecycle->method('recordVerifiedPaid')->willReturn($kept);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('could not match your payment');

        $this->processor->process(['orderId' => 'MOMOREF']);
    }

    /**
     * An authoritative failure transitions where the fresh state permits and
     * refuses the success page.
     *
     * @return void
     */
    public function testAuthoritativeFailureRefuses(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->queryCommand->method('execute')->willReturn(
            $this->queryResult(['resultCode' => 700, 'amount' => 150000, 'transId' => '987654321'])
        );
        $failed = $this->attempt('failed');
        $this->lifecycle->method('recordVerifiedFailure')->willReturn($failed);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');
        $this->successSessionPreparer->expects($this->never())->method('prepare');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('was not completed');

        $this->processor->process(['orderId' => 'MOMOREF']);
    }

    /**
     * A failure claim against an attempt the IPN already FINALIZED recovers
     * the bound order and STILL sends the customer to the success page.
     *
     * @return void
     */
    public function testFailureAfterFinalizeRecoversOrderWithSuccessSession(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->queryCommand->method('execute')->willReturn(
            $this->queryResult(['resultCode' => 700, 'amount' => 150000, 'transId' => '987654321'])
        );
        $finalized = $this->attempt('finalized', ['order_id' => 5001]);
        $this->lifecycle->method('recordVerifiedFailure')->willReturn($finalized);
        $order = $this->createMock(OrderInterface::class);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($finalized)->willReturn($order);
        $this->successSessionPreparer->expects($this->once())->method('prepare')->with($finalized, $order);

        $this->assertSame(
            'checkout/onepage/success',
            $this->processor->process(['orderId' => 'MOMOREF'])
        );
    }

    /**
     * The v2/query result wrapper.
     *
     * @param array $data
     * @return ResultInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function queryResult(array $data): ResultInterface
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('get')->willReturn($data);

        return $result;
    }

    /**
     * A real attempt in the given status.
     *
     * @param string $status
     * @param array $extra
     * @return PaymentAttempt
     */
    private function attempt(string $status, array $extra = []): PaymentAttempt
    {
        $attempt = new PaymentAttempt($this->createMock(Context::class), $this->createMock(Registry::class));
        $attempt->setEntityId(7);
        $attempt->setQuoteId(42);
        $attempt->setOrderRef('MOMOREF');
        $attempt->setRequestId('MOMOREF-R1111');
        $attempt->setAmount(150000);
        $attempt->setPaymentStatus($status);
        foreach ($extra as $field => $value) {
            $attempt->setData($field, $value);
        }

        return $attempt;
    }
}
