<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Exception\ContractMismatchException;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Helper\Data as ZaloPayHelper;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Service\OrderFinalizer;
use Secomm\ZaloPay\Service\PaymentAttemptLifecycle;
use Secomm\ZaloPay\Service\ReturnProcessor;
use Secomm\ZaloPay\Service\SuccessSessionPreparer;

/**
 * Browser Return contract (corrective TASK-EDS9T5 Blocker 1 + 2).
 *
 * THE BROWSER REDIRECT CAN NEVER TERMINALLY FAIL A PAYMENT:
 *  - browser `status` is never payment proof — the authoritative v2/query
 *    ALWAYS runs and owns every payment-state decision (cases 1-4);
 *  - every state decision is made on the LOCKED FRESH row inside
 *    PaymentAttemptLifecycle (real instance wired here) — never the stale
 *    lookup copy (case 5);
 *  - a stale Return can never regress FINALIZED (case 7) and the Return/IPN
 *    race converges on the finalizer's single-order contract (case 9);
 *  - amount mismatch = money-real evidence, no order (case 13);
 *  - IPN-first then late Return: same finalized order recovered AND the
 *    customer success session prepared by the Return path (case 23);
 *  - a bad browser checksum is diagnostic/tamper EVIDENCE only: it never
 *    terminally mutates the attempt and never blocks the authoritative
 *    verification (corrective round 3, Blocker 1 — matrix #1-#4);
 *  - processing (query return_code 3) stays non-terminal — no mutation, no
 *    order; the IPN, a later Return or the TTL resolves it.
 */
class ReturnProcessorTest extends TestCase
{
    private const APP_TRANS_ID = '260826_1000_000000123';
    private const ZP_TRANS_ID = '240801000001';

    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var CommandPoolInterface|MockObject
     */
    private $commandPool;

    /**
     * @var OrderFinalizer|MockObject
     */
    private $orderFinalizer;

    /**
     * @var SuccessSessionPreparer|MockObject
     */
    private $successSessionPreparer;

    /**
     * @var ZaloPayHelper|MockObject
     */
    private $helperData;

    /**
     * @var ReturnProcessor
     */
    private $processor;

    /**
     * @var PaymentAttempt|null Attempt captured by the save stub.
     */
    private ?PaymentAttempt $saved = null;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->orderFinalizer = $this->createMock(OrderFinalizer::class);
        $this->successSessionPreparer = $this->createMock(SuccessSessionPreparer::class);
        $this->helperData = $this->createMock(ZaloPayHelper::class);

        $connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $lifecycle = new PaymentAttemptLifecycle(
            $this->repository,
            $resourceConnection,
            $this->createMock(Logger::class)
        );

        $this->processor = new ReturnProcessor(
            $this->repository,
            $this->commandPool,
            $this->orderFinalizer,
            $lifecycle,
            $this->successSessionPreparer,
            $this->helperData,
            $this->createMock(Logger::class)
        );
    }

    // ---- Blocker 1: browser params never decide a payment state ----

    /**
     * Case 1: browser says failed/absent — this alone can NEVER mark the
     * attempt FAILED. The authoritative query still runs; processing (3)
     * keeps the attempt untouched and non-terminal.
     *
     * @return void
     */
    public function testBrowserFailureStatusCannotMarkFailedByItself(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubQuery([AbstractResponseValidator::RETURN_CODE => 3]);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        try {
            $this->processor->process($this->params(['status' => '0']));
            $this->fail('LocalizedException was not thrown.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('still being processed', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
    }

    /**
     * Case 2: browser says FAILED, the authoritative query says PAID ->
     * the order finalizes (browser params are ignored as proof).
     *
     * @return void
     */
    public function testBrowserFailedButAuthoritativeQueryPaidFinalizesOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubPaidQuery();
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(88);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt), self::ZP_TRANS_ID)
            ->willReturn($order);
        $this->successSessionPreparer->expects($this->once())->method('prepare')
            ->with($this->identicalTo($attempt), $this->identicalTo($order));

        $result = $this->processor->process($this->params(['status' => '0']));

        $this->assertSame('checkout/onepage/success', $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $attempt->getProviderTransactionId());
    }

    /**
     * Case 3: browser says PAID, the authoritative query says processing ->
     * no order, no mutation.
     *
     * @return void
     */
    public function testBrowserPaidButQueryProcessingPlacesNoOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubQuery([AbstractResponseValidator::RETURN_CODE => 3]);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('still being processed');
        $this->processor->process($this->params(['status' => '1']));
    }

    /**
     * Case 4: browser says PAID, the authoritative query gives a definitive
     * failure -> no order, the attempt lands in the correct terminal state
     * (FAILED) via the lifecycle.
     *
     * @return void
     */
    public function testBrowserPaidButAuthoritativeQueryFailureTransitionsFailed(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 2,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
        ]);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        try {
            $this->processor->process($this->params(['status' => '1']));
            $this->fail('LocalizedException was not thrown.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('was not completed', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $this->saved->getPaymentStatus());
        $this->assertStringContainsString('return_code 2', (string)$this->saved->getLastError());
    }

    // ---- Blocker 2: decisions on the locked fresh row ----

    /**
     * Case 5/9: the Return/IPN race — the lookup copy says ACTIVE but the
     * LOCKED row is already PAID (the IPN won the race). The locked row
     * decides: no second mutation, finalization converges on one order.
     *
     * @return void
     */
    public function testDecisionIsMadeOnLockedFreshRowNotStaleCopy(): void
    {
        $staleCopy = $this->newActiveAttempt();
        $freshRow = $this->newActiveAttempt()->markPaid(self::ZP_TRANS_ID);
        $this->repository->method('getByAppTransId')->willReturn($staleCopy);
        $this->repository->method('lockByAppTransId')->willReturn($freshRow);
        $this->repository->expects($this->never())->method('save'); // PAID row: idempotent no-op
        $this->stubPaidQuery();
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(88);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($freshRow), self::ZP_TRANS_ID)
            ->willReturn($order);

        $result = $this->processor->process($this->params(['status' => '1']));

        $this->assertSame('checkout/onepage/success', $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $freshRow->getPaymentStatus());
    }

    /**
     * Case 7: a STALE Return (duplicate, after the IPN finalized) can never
     * regress FINALIZED — even when the provider query now reports a
     * failure. The bound order is recovered for the customer.
     *
     * @return void
     */
    public function testStaleReturnCannotRegressFinalizedAttempt(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid(self::ZP_TRANS_ID)->markFinalized(77);
        $this->stubAttempt($attempt);
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => -1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
        ]);
        $this->repository->expects($this->never())->method('save');
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(77);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt))
            ->willReturn($order);
        $this->successSessionPreparer->expects($this->once())->method('prepare');

        $result = $this->processor->process($this->params(['status' => '0']));

        $this->assertSame('checkout/onepage/success', $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $attempt->getPaymentStatus());
        $this->assertSame(77, $attempt->getOrderId());
    }

    // ---- amount lock ----

    /**
     * Case 13: provider money for a different amount than the snapshot ->
     * evidence persisted money-real, NO order, customer-safe failure.
     *
     * @return void
     */
    public function testAmountMismatchRecordsEvidenceAndPlacesNoOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => AbstractResponseValidator::RETURN_CODE_ACCEPT,
            AbstractResponseValidator::TOTAL_AMOUNT => 50000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        try {
            $this->processor->process($this->params(['status' => '1']));
            $this->fail('LocalizedException was not thrown.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('amount mismatch', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertStringContainsString(
            'Return amount mismatch: paid 50000, snapshot 100000',
            (string)$this->saved->getLastError()
        );
        $this->assertNull($this->saved->getOrderId());
    }

    // ---- checksum evidence (corrective round 3, Blocker 1) ----

    /**
     * Round-3 matrix #1/#2: a BAD browser checksum never terminally mutates
     * the attempt and NEVER blocks the authoritative query — misleading
     * browser params + query SUCCESS -> the payment finalizes (exactly one
     * order via the finalizer). The checksum is diagnostic evidence only.
     *
     * @return void
     */
    public function testBadChecksumNeverBlocksAuthoritativeVerification(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->helperData->method('verifyRedirect')->willReturn(false);
        $this->stubPaidQuery();
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(88);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt), self::ZP_TRANS_ID)
            ->willReturn($order);
        $this->successSessionPreparer->expects($this->once())->method('prepare');

        $result = $this->processor->process($this->params(['status' => '0', 'checksum' => 'tampered']));

        $this->assertSame('checkout/onepage/success', $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertNull($this->saved->getOrderId()); // finalizer owns the binding, never the return path
    }

    /**
     * Round-3 matrix #3: misleading browser success (with a bad checksum)
     * + authoritative query FAIL -> NO order; the attempt goes FAILED
     * through the lifecycle — the query result owns the state.
     *
     * @return void
     */
    public function testBadChecksumWithAuthoritativeFailurePlacesNoOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->helperData->method('verifyRedirect')->willReturn(false);
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 2,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
        ]);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        try {
            $this->processor->process($this->params(['status' => '1', 'checksum' => 'tampered']));
            $this->fail('LocalizedException was not thrown.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('was not completed', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $this->saved->getPaymentStatus());
        $this->assertNull($this->saved->getOrderId());
    }

    /**
     * Round-3 matrix #4: the Return ALWAYS uses the authoritative result —
     * without a checksum at all the v2/query still runs and decides.
     *
     * @return void
     */
    public function testMissingChecksumStillVerifiesAuthoritatively(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->helperData->expects($this->never())->method('verifyRedirect');
        $this->stubQuery([AbstractResponseValidator::RETURN_CODE => 3]);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('still being processed');
        $this->processor->process($this->params(['status' => '1', 'checksum' => '']));
    }

    // ---- IPN-first, Return-late (case 23) ----

    /**
     * Case 23: the IPN finalized first (no browser was present); the late
     * Return recovers the SAME finalized order AND prepares the CUSTOMER
     * success session (session ownership lives on the Return path only).
     *
     * @return void
     */
    public function testLateReturnAfterIpnFinalizationRecoversOrderAndPreparesCustomerSession(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid(self::ZP_TRANS_ID)->markFinalized(77);
        $this->stubAttempt($attempt);
        $this->stubPaidQuery();
        $this->repository->expects($this->never())->method('save');
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(77);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt), self::ZP_TRANS_ID)
            ->willReturn($order);
        $this->successSessionPreparer->expects($this->once())->method('prepare')
            ->with($this->identicalTo($attempt), $this->identicalTo($order));

        $result = $this->processor->process($this->params(['status' => '1']));

        $this->assertSame('checkout/onepage/success', $result);
    }

    /**
     * Fresh finalization on the Return path also prepares the customer
     * success session (the finalizer itself is session-free).
     *
     * @return void
     */
    public function testFreshFinalizationPreparesCustomerSuccessSession(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubPaidQuery();
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(88);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')->willReturn($order);
        $this->successSessionPreparer->expects($this->once())->method('prepare');

        $result = $this->processor->process($this->params(['status' => '1']));

        $this->assertSame('checkout/onepage/success', $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
    }

    // ---- money-real PAID evidence on a terminal attempt ----

    /**
     * Late Return carrying authoritative PAID evidence for a STALE attempt:
     * evidence + provider transaction identity preserved, NO order, NO
     * terminal broadening — customer-safe reconciliation message.
     *
     * @return void
     */
    public function testPaidEvidenceOnTerminalAttemptRecordsOnlyAndRefusesOrder(): void
    {
        $attempt = $this->newActiveAttempt()->markStale();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubPaidQuery();
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        try {
            $this->processor->process($this->params(['status' => '1']));
            $this->fail('LocalizedException was not thrown.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('could not match your payment', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $attempt->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $attempt->getProviderTransactionId());
        $this->assertStringContainsString('manual reconciliation', (string)$attempt->getLastError());
    }

    // ---- contract mismatch passthrough ----

    /**
     * The finalizer refuses on contract mismatch: customer-safe message,
     * the attempt kept its money-real state (finalizer's contract).
     *
     * @return void
     */
    public function testContractMismatchSurfacesCustomerSafeMessage(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubPaidQuery();
        $this->orderFinalizer->method('finalizeOrRecover')
            ->willThrowException(new ContractMismatchException(__('quote total changed')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('could not match your payment');
        $this->processor->process($this->params(['status' => '1']));
    }

    // ---- lookup / transport failures ----

    /**
     * Missing apptransid -> invalid payload.
     *
     * @return void
     */
    public function testMissingAppTransIdIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid ZaloPay return payload');
        $this->processor->process(['status' => '1']);
    }

    /**
     * Unknown attempt -> customer-safe "session not found".
     *
     * @return void
     */
    public function testUnknownAttemptIsRejected(): void
    {
        $this->repository->method('getByAppTransId')->willReturn(null);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('session not found');
        $this->processor->process($this->params(['status' => '1']));
    }

    /**
     * v2/query transport failure: nothing decided, nothing mutated, retry
     * message.
     *
     * @return void
     */
    public function testQueryTransportFailureMutatesNothing(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $command = $this->createMock(CommandInterface::class);
        $command->method('execute')->willThrowException(new \RuntimeException('timeout'));
        $this->commandPool->method('get')->with('query_transaction')->willReturn($command);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('could not be verified right now');
        $this->processor->process($this->params(['status' => '1']));
    }

    // ---- helpers ----

    /**
     * @param array $overrides
     * @return array
     */
    private function params(array $overrides): array
    {
        return array_merge([
            'apptransid' => self::APP_TRANS_ID,
            'status' => '1',
        ], $overrides);
    }

    /**
     * @param array $data
     * @return void
     */
    private function stubQuery(array $data): void
    {
        $command = $this->createMock(CommandInterface::class);
        $result = $this->createMock(ResultInterface::class);
        $result->method('get')->willReturn($data);
        $command->method('execute')->with([AbstractResponseValidator::TRANSACTION_ID => self::APP_TRANS_ID])
            ->willReturn($result);
        $this->commandPool->method('get')->with('query_transaction')->willReturn($command);
    }

    /**
     * @return void
     */
    private function stubPaidQuery(): void
    {
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => AbstractResponseValidator::RETURN_CODE_ACCEPT,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
    }

    /**
     * @param PaymentAttempt $attempt
     * @return void
     */
    private function stubAttempt(PaymentAttempt $attempt): void
    {
        $this->repository->method('getByAppTransId')->willReturn($attempt);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
    }

    /**
     * @return void
     */
    private function stubSave(): void
    {
        $this->saved = null;
        $this->repository->method('save')->willReturnCallback(
            function (PaymentAttemptInterface $attempt) {
                $this->saved = $attempt;

                return $attempt;
            }
        );
    }

    /**
     * @return PaymentAttempt
     */
    private function newActiveAttempt(): PaymentAttempt
    {
        $attempt = new PaymentAttempt(
            $this->createMock(\Magento\Framework\Model\Context::class),
            $this->createMock(\Magento\Framework\Registry::class),
            $this->newResourceStub()
        );
        $attempt->setEntityId(9);
        $attempt->setQuoteId(42);
        $attempt->setReservedOrderId('000000123');
        $attempt->setAppTransId(self::APP_TRANS_ID);
        $attempt->setAmount(100000);
        $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
        $attempt->setExpiresAt('2099-01-01 00:00:00');
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_INITIATED);
        $attempt->markActive('https://pay.zalopay.vn/order/abc');

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
