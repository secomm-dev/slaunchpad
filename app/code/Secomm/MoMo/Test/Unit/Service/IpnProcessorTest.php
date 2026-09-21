<?php
/**
 * Unit test for the MoMo IPN processor (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Gateway\Validator\NotifyValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Secomm\MoMo\Model\PaymentAttempt;
use Secomm\MoMo\Service\IpnProcessor;
use Secomm\MoMo\Service\OrderFinalizer;
use Secomm\MoMo\Service\PaymentAttemptLifecycle;

/**
 * Verifies the IPN domain outcomes: STRICT signed-value parsing (a
 * malformed amount/transId is INVALID with zero mutation), the
 * signature/identity gate, the positive-transId requirement for automatic
 * finalization and the AC9 retryable contract.
 */
class IpnProcessorTest extends TestCase
{
    private PaymentAttemptRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;

    private NotifyValidator&\PHPUnit\Framework\MockObject\MockObject $notifyValidator;

    private OrderFinalizer&\PHPUnit\Framework\MockObject\MockObject $orderFinalizer;

    private PaymentAttemptLifecycle&\PHPUnit\Framework\MockObject\MockObject $lifecycle;

    private IpnProcessor $processor;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->notifyValidator = $this->createMock(NotifyValidator::class);
        $this->orderFinalizer = $this->createMock(OrderFinalizer::class);
        $this->lifecycle = $this->createMock(PaymentAttemptLifecycle::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->processor = new IpnProcessor(
            $this->repository,
            $this->notifyValidator,
            $this->orderFinalizer,
            $this->lifecycle,
            $logger
        );
    }

    /**
     * A payload without orderId is invalid — nothing is even looked up.
     *
     * @return void
     */
    public function testMissingOrderIdAnswersInvalid(): void
    {
        $this->repository->expects($this->never())->method('getByOrderRef');

        $this->assertSame(IpnProcessor::OUTCOME_INVALID_CALLBACK, $this->processor->process([]));
    }

    /**
     * A well-formed reference matching no attempt is unknown — nothing to
     * mutate.
     *
     * @return void
     */
    public function testUnknownReferenceAnswersUnknown(): void
    {
        $this->repository->method('getByOrderRef')->willReturn(null);

        $this->assertSame(
            IpnProcessor::OUTCOME_UNKNOWN_REFERENCE,
            $this->processor->process(['orderId' => 'UNKNOWN'])
        );
    }

    /**
     * A malformed signed amount inside the payload is a provider anomaly:
     * INVALID with ZERO mutation — never cast to a passing value.
     *
     * @return void
     */
    public function testMalformedAmountAnswersInvalid(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->notifyValidator->expects($this->never())->method('validate');
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');

        $this->assertSame(
            IpnProcessor::OUTCOME_INVALID_CALLBACK,
            $this->processor->process(['orderId' => 'MOMOREF', 'amount' => '12.5', 'transId' => '111'])
        );
    }

    /**
     * A malformed transId (e.g. an array) is also a provider anomaly.
     *
     * @return void
     */
    public function testMalformedTransIdAnswersInvalid(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->notifyValidator->expects($this->never())->method('validate');

        $this->assertSame(
            IpnProcessor::OUTCOME_INVALID_CALLBACK,
            $this->processor->process(['orderId' => 'MOMOREF', 'amount' => '150000', 'transId' => ['x']])
        );
    }

    /**
     * A payload failing the signature/identity/amount chain is invalid and
     * never mutates.
     *
     * @return void
     */
    public function testValidatorFailureAnswersInvalid(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $result = $this->createMock(ResultInterface::class);
        $result->method('isValid')->willReturn(false);
        $result->method('getFailsDescription')->willReturn(['MoMo notify signature verification failed.']);
        $this->notifyValidator->method('validate')->willReturn($result);
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');

        $this->assertSame(
            IpnProcessor::OUTCOME_INVALID_CALLBACK,
            $this->processor->process($this->payload())
        );
    }

    /**
     * resultCode 0 without a positive transId: verified money without a
     * provable identity is quarantined (no order), and the callback is
     * acknowledged so MoMo does not retry forever.
     *
     * @return void
     */
    public function testSuccessWithoutTransIdQuarantinesAndAcknowledges(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->notifyValidator->method('validate')->willReturn($this->validResult());
        $this->lifecycle->expects($this->once())->method('recordProviderIdentityUnavailable')
            ->with('MOMOREF', 'IPN');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->assertSame(
            IpnProcessor::OUTCOME_ACK_RECONCILIATION,
            $this->processor->process($this->payload(['transId' => 0]))
        );
    }

    /**
     * The authoritative success path: PAID via the lifecycle, order
     * finalized, SUCCESS outcome.
     *
     * @return void
     */
    public function testVerifiedPaidFinalizesAndAnswersSuccess(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->notifyValidator->method('validate')->willReturn($this->validResult());
        $paid = $this->attempt('paid', ['provider_transaction_id' => '987654321']);
        $this->lifecycle->method('recordVerifiedPaid')->willReturn($paid);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($paid, '987654321');

        $this->assertSame(
            IpnProcessor::OUTCOME_SUCCESS,
            $this->processor->process($this->payload())
        );
    }

    /**
     * A quarantined attempt (conflicting identity etc.) after the canonical
     * PAID transition is acknowledged without an order.
     *
     * @return void
     */
    public function testQuarantinedAfterPaidAcknowledges(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->notifyValidator->method('validate')->willReturn($this->validResult());
        $quarantined = $this->attempt('paid', ['requires_reconciliation' => 1]);
        $this->lifecycle->method('recordVerifiedPaid')->willReturn($quarantined);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->assertSame(
            IpnProcessor::OUTCOME_ACK_RECONCILIATION,
            $this->processor->process($this->payload())
        );
    }

    /**
     * A finalization technical failure keeps the attempt money-real and asks
     * MoMo to retry (AC9: HTTP 500 + IPN retry is the recovery driver).
     *
     * @return void
     */
    public function testFinalizationFailureAnswersRetryable(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->notifyValidator->method('validate')->willReturn($this->validResult());
        $paid = $this->attempt('paid');
        $this->lifecycle->method('recordVerifiedPaid')->willReturn($paid);
        $this->orderFinalizer->method('finalizeOrRecover')
            ->willThrowException(new \RuntimeException('temporary DB outage'));

        $this->assertSame(
            IpnProcessor::OUTCOME_RETRYABLE_FAILURE,
            $this->processor->process($this->payload())
        );
    }

    /**
     * A contract refusal during IPN finalization is deterministic: the
     * evidence is persisted by the finalizer and the callback is
     * acknowledged — retrying cannot fix it.
     *
     * @return void
     */
    public function testContractMismatchAcknowledges(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->notifyValidator->method('validate')->willReturn($this->validResult());
        $paid = $this->attempt('paid');
        $this->lifecycle->method('recordVerifiedPaid')->willReturn($paid);
        $this->orderFinalizer->method('finalizeOrRecover')
            ->willThrowException(new \Secomm\MoMo\Exception\ContractMismatchException(__('mismatch')));

        $this->assertSame(
            IpnProcessor::OUTCOME_ACK_RECONCILIATION,
            $this->processor->process($this->payload())
        );
    }

    /**
     * A signature-valid authoritative FAILURE (cancelled at MoMo) after the
     * order was already finalized recovers the bound order and answers
     * SUCCESS.
     *
     * @return void
     */
    public function testAuthoritativeFailureAfterFinalizeRecoversAndSucceeds(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('finalized'));
        $this->notifyValidator->method('validate')->willReturn($this->validResult());
        $finalized = $this->attempt('finalized', ['order_id' => 5001]);
        $this->lifecycle->method('recordVerifiedFailure')->willReturn($finalized);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')->with($finalized);

        $this->assertSame(
            IpnProcessor::OUTCOME_SUCCESS,
            $this->processor->process($this->payload(['resultCode' => 700, 'message' => 'cancelled']))
        );
    }

    /**
     * An authoritative failure conflicting with recorded PAID keeps the
     * money-real state and acknowledges (never regressed).
     *
     * @return void
     */
    public function testAuthoritativeFailureOnPaidAcknowledges(): void
    {
        $this->repository->method('getByOrderRef')->willReturn($this->attempt('active'));
        $this->notifyValidator->method('validate')->willReturn($this->validResult());
        $paid = $this->attempt('paid');
        $this->lifecycle->method('recordVerifiedFailure')->willReturn($paid);

        $this->assertSame(
            IpnProcessor::OUTCOME_ACK_RECONCILIATION,
            $this->processor->process($this->payload(['resultCode' => 700, 'message' => 'cancelled']))
        );
    }

    /**
     * The canonical MoMo success Notify payload for the test attempt.
     *
     * @param array $overrides
     * @return array
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'orderId' => 'MOMOREF',
            'requestId' => 'MOMOREF-R1111',
            'partnerCode' => 'MOMO',
            'amount' => '150000',
            'transId' => '987654321',
            'resultCode' => 0,
            'message' => 'Successful',
            'signature' => 'valid',
        ], $overrides);
    }

    /**
     * A passing validator result.
     */
    private function validResult(): ResultInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('isValid')->willReturn(true);
        $result->method('getFailsDescription')->willReturn([]);

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
