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
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Exception\ContractMismatchException;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Service\IpnProcessor;
use Secomm\ZaloPay\Service\OrderFinalizer;
use Secomm\ZaloPay\Service\PaymentAttemptLifecycle;

/**
 * IPN contract (payment-first only; corrective round 3 — BLOCKERS 3/4/5).
 * A valid successful IPN drives the WHOLE canonical lifecycle
 * server-to-server — MAC + amount verification, attempt PAID through the
 * REAL PaymentAttemptLifecycle (locked fresh row), OrderFinalizer (exactly
 * one Sales Order) — the customer browser is never required.
 *
 * The processor returns DOMAIN OUTCOMES; the controller serializes the
 * official `{return_code, return_message}` contract:
 *  - exact provider amount + finalize  -> OUTCOME_SUCCESS            (rc 1);
 *  - duplicate callback FINALIZED      -> OUTCOME_SUCCESS (idempotent, rc 1);
 *  - missing/zero amount               -> AUTHORITATIVE v2/query fallback:
 *     query paid + EXACT amount        -> SUCCESS (round-3 #13/#14);
 *     query processing (3)             -> OUTCOME_RETRYABLE_FAILURE (rc 0);
 *     query FAIL (2)                   -> authoritative failure recorded;
 *  - wrong amount                      -> OUTCOME_ACK_RECONCILIATION + structured
 *     requires_reconciliation quarantine, NO order (round-3 #15/#17);
 *  - conflicting zp_trans_id           -> quarantine, NO auto-finalize (#19);
 *  - duplicate zp_trans_id             -> idempotent (#20);
 *  - quarantined attempt               -> NEVER auto-finalized later (#18);
 *  - MAC failure                       -> OUTCOME_INVALID_CALLBACK (rc 2),
 *     NO mutation, NO lookup (round-3 #11);
 *  - unknown app_trans_id              -> OUTCOME_INVALID_CALLBACK (documented
 *     "Invalid"; not retryable) (round-3 #12);
 *  - transient finalization failure    -> OUTCOME_RETRYABLE_FAILURE (rc 0).
 */
class IpnProcessorTest extends TestCase
{
    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var Authorization|MockObject
     */
    private $authorization;

    /**
     * @var CommandPoolInterface|MockObject
     */
    private $commandPool;

    /**
     * @var OrderFinalizer|MockObject
     */
    private $orderFinalizer;

    /**
     * @var IpnProcessor
     */
    private $processor;

    /**
     * @var PaymentAttempt|null Attempt captured by the save stub.
     */
    private ?PaymentAttempt $saved = null;

    private const DATA_STRING = '{"app_id":2554,"app_trans_id":"260826_1000_000000123"}';
    private const VALID_MAC = 'valid-mac';
    private const APP_TRANS_ID = '260826_1000_000000123';
    private const ZP_TRANS_ID = '240801000001';
    private const APP_ID = 2554;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->authorization = $this->createMock(Authorization::class);
        $this->authorization->method('getMacKey2')->willReturn(self::VALID_MAC);
        $this->authorization->method('getAppId')->willReturn((string)self::APP_ID);
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->orderFinalizer = $this->createMock(OrderFinalizer::class);

        $connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $lifecycle = new PaymentAttemptLifecycle(
            $this->repository,
            $resourceConnection,
            $this->createMock(Logger::class)
        );

        $this->processor = new IpnProcessor(
            $this->repository,
            $this->authorization,
            $this->commandPool,
            $this->orderFinalizer,
            $lifecycle,
            $this->createMock(Logger::class)
        );
    }

    // ---- Round-3 #16: exact amount may finalize ----

    /**
     * IPN with the EXACT provider amount: attempt PAID (via the lifecycle
     * lock), canonical finalization runs immediately, outcome SUCCESS (the
     * controller answers return_code 1 "Success"). No browser ever needed.
     *
     * @return void
     */
    public function testExactAmountIpnFinalizesWithSuccessOutcome(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt), self::ZP_TRANS_ID);

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $attempt->getProviderTransactionId());
    }

    /**
     * The decision is made on the LOCKED FRESH row — the lookup copy still
     * says ACTIVE but the row is already PAID (a racing Return recorded it):
     * no second mutation, finalization still converges on one order.
     *
     * @return void
     */
    public function testDecisionIsMadeOnLockedFreshRowNotStaleCopy(): void
    {
        $staleCopy = $this->newActiveAttempt();
        $freshRow = $this->newActiveAttempt()->markPaid(self::ZP_TRANS_ID);
        $this->repository->method('getByAppTransId')->willReturn($staleCopy);
        $this->repository->method('lockByAppTransId')->willReturn($freshRow);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($freshRow), self::ZP_TRANS_ID);

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $freshRow->getPaymentStatus());
    }

    /**
     * Round-3 #9: duplicate callback on FINALIZED -> idempotent success ACK
     * (documented return_code 1) without any re-mutation.
     *
     * @return void
     */
    public function testDuplicateCallbackOnFinalizedAttemptReturnsDocumentedSuccessAck(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid(self::ZP_TRANS_ID)->markFinalized(77);
        $this->stubAttempt($attempt);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt), self::ZP_TRANS_ID);

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $attempt->getPaymentStatus());
        $this->assertSame(77, $attempt->getOrderId());
    }

    /**
     * Duplicate callback on an already-PAID attempt (a previous finalize
     * failed transiently): canonical finalization is RETRIED.
     *
     * @return void
     */
    public function testDuplicateCallbackOnPaidAttemptRetriesFinalization(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid(self::ZP_TRANS_ID);
        $this->stubAttempt($attempt);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt), self::ZP_TRANS_ID);

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
    }

    // ---- Round-3 #11/#12: documented invalid callback ----

    /**
     * MAC failure -> OUTCOME_INVALID_CALLBACK (controller answers the
     * documented return_code 2 "Invalid"); NOTHING is looked up or mutated,
     * the finalizer never runs.
     *
     * @return void
     */
    public function testMacFailureReturnsInvalidOutcomeWithoutMutation(): void
    {
        $this->repository->expects($this->never())->method('getByAppTransId');
        $this->repository->expects($this->never())->method('lockByAppTransId');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $payload = $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000]);
        $payload['mac'] = 'tampered';

        $this->assertSame(IpnProcessor::OUTCOME_INVALID_CALLBACK, $this->processor->process($payload));
    }

    /**
     * Round-3 #12: unknown app_trans_id (valid MAC or not) follows the
     * documented policy — "Invalid" (return_code 2), nothing mutated, no
     * retry loop for a reference that will never exist.
     *
     * @return void
     */
    public function testUnknownAttemptReturnsDocumentedInvalidOutcome(): void
    {
        $this->repository->method('getByAppTransId')->willReturn(null);
        $this->repository->expects($this->never())->method('lockByAppTransId');
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->assertSame(
            IpnProcessor::OUTCOME_INVALID_CALLBACK,
            $this->processor->process($this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000]))
        );
    }

    // ---- Round-3 #13/#14: missing/zero amount -> authoritative query fallback ----

    /**
     * Round-3 #13: MISSING callback amount NEVER finalizes by itself — the
     * authoritative v2/query must confirm return_code 1 AND the exact
     * amount; then (and only then) PAID + canonical finalization.
     *
     * @return void
     */
    public function testMissingAmountIsConfirmedByAuthoritativeQueryBeforeFinalize(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt), self::ZP_TRANS_ID);

        // payload WITHOUT the amount key entirely.
        $outcome = $this->processor->process($this->payload(['zp_trans_id' => self::ZP_TRANS_ID]));

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
    }

    /**
     * Round-3 #14: amount = 0 NEVER means "continue anyway" — same
     * authoritative-query fallback: exact query amount -> finalize.
     *
     * @return void
     */
    public function testZeroAmountIsConfirmedByAuthoritativeQueryBeforeFinalize(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover');

        $outcome = $this->processor->process($this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 0]));

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
    }

    /**
     * Missing amount + query PROCESSING (3): no mutation, no order,
     * retryable outcome (ZaloPay retries the callback per its policy).
     *
     * @return void
     */
    public function testMissingAmountWithProcessingQueryIsRetryableWithoutMutation(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubQuery([AbstractResponseValidator::RETURN_CODE => 3]);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process($this->payload(['zp_trans_id' => self::ZP_TRANS_ID]));

        $this->assertSame(IpnProcessor::OUTCOME_RETRYABLE_FAILURE, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
    }

    /**
     * Missing amount + query says PAID but with a WRONG amount: quarantined
     * (money-real evidence), NO order, acknowledged — retries are useless.
     *
     * @return void
     */
    public function testMissingAmountWithWrongQueryAmountQuarantinesWithoutOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 50000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process($this->payload(['zp_trans_id' => self::ZP_TRANS_ID]));

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertTrue($this->saved->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_AMOUNT_MISMATCH,
            $this->saved->getReconciliationCode()
        );
        $this->assertStringContainsString('IPN-query amount mismatch: paid 50000, snapshot 100000',
            (string)$this->saved->getLastError());
        $this->assertNull($this->saved->getOrderId());
    }

    /**
     * Missing amount + query PAID without an amount either (documented: the
     * amount is only available on success): without a verified exact amount
     * there is NO automatic order — quarantined, no order.
     *
     * @return void
     */
    public function testMissingAmountWithAmountlessPaidQueryPlacesNoOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process($this->payload(['zp_trans_id' => self::ZP_TRANS_ID]));

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertTrue($this->saved->getRequiresReconciliation());
        $this->assertNull($this->saved->getOrderId());
    }

    /**
     * Missing amount + authoritative query transport failure: retryable, no
     * mutation (the recovery worker / provider retries resolve it).
     *
     * @return void
     */
    public function testMissingAmountWithQueryTransportFailureIsRetryable(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $command = $this->createMock(CommandInterface::class);
        $command->method('execute')->willThrowException(new \RuntimeException('timeout'));
        $this->commandPool->method('get')->with('query_transaction')->willReturn($command);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process($this->payload(['zp_trans_id' => self::ZP_TRANS_ID]));

        $this->assertSame(IpnProcessor::OUTCOME_RETRYABLE_FAILURE, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
    }

    /**
     * Missing amount + authoritative query FAIL (2): the callback's paid
     * claim loses to the authoritative result — failure recorded where the
     * fresh state permits, acknowledged.
     *
     * @return void
     */
    public function testMissingAmountWithQueryFailureRecordsAuthoritativeFailure(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([AbstractResponseValidator::RETURN_CODE => 2]);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process($this->payload(['zp_trans_id' => self::ZP_TRANS_ID]));

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $this->saved->getPaymentStatus());
        $this->assertStringContainsString('return_code 2', (string)$this->saved->getLastError());
        $this->assertNull($this->saved->getOrderId());
    }

    // ---- Round-3 #15/#17: wrong amount -> structured quarantine, no order ----

    /**
     * Round-3 #15: WRONG provider amount -> money-real PAID + structured
     * requires_reconciliation quarantine + exact evidence, ACK outcome —
     * never auto-placed, no retry loop.
     *
     * @return void
     */
    public function testWrongAmountQuarantinesWithStructuredEvidence(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 50000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertTrue($this->saved->getRequiresReconciliation());
        $this->assertSame(PaymentAttemptInterface::RECON_AMOUNT_MISMATCH, $this->saved->getReconciliationCode());
        $this->assertStringContainsString(
            'IPN amount mismatch: paid 50000, snapshot 100000',
            (string)$this->saved->getLastError()
        );
        $this->assertNull($this->saved->getOrderId());
    }

    // ---- Round-3 #18: quarantined attempts can never auto-finalize later ----

    /**
     * Round-3 #18: a previously-quarantined attempt (e.g. an amount
     * mismatch) receiving a later VALID callback with the exact amount is
     * STILL refused automatic finalization — the flag is only clearable by
     * an explicit manual/reconciliation workflow.
     *
     * @return void
     */
    public function testQuarantinedAttemptCannotAutoFinalizeOnLaterValidCallback(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid(self::ZP_TRANS_ID);
        $attempt->setRequiresReconciliation(true);
        $attempt->setReconciliationCode(PaymentAttemptInterface::RECON_AMOUNT_MISMATCH);
        $this->stubAttempt($attempt);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertTrue($attempt->getRequiresReconciliation());
        $this->assertNull($attempt->getOrderId());
    }

    // ---- Round-3 #19/#20: conflicting / duplicate zp_trans_id ----

    /**
     * Round-3 #19: same app_trans_id with a DIFFERENT authoritative
     * zp_trans_id -> the FIRST recorded identity is kept, the attempt is
     * quarantined (provider_transaction_conflict) money-real, and NO order
     * is placed.
     *
     * @return void
     */
    public function testConflictingProviderTransactionIdQuarantinesWithoutOrder(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid(self::ZP_TRANS_ID);
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => '999999999999', 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $this->saved->getProviderTransactionId()); // first proof owns it
        $this->assertTrue($this->saved->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT,
            $this->saved->getReconciliationCode()
        );
        $this->assertStringContainsString('999999999999', (string)$this->saved->getLastError());
        $this->assertNull($this->saved->getOrderId());
    }

    /**
     * Round-3 #20: the EXACT duplicate zp_trans_id is idempotent — no
     * quarantine, canonical finalization still runs.
     *
     * @return void
     */
    public function testExactDuplicateProviderTransactionIdIsIdempotent(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid(self::ZP_TRANS_ID);
        $this->stubAttempt($attempt);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover');

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertFalse($attempt->getRequiresReconciliation());
        $this->assertSame(self::ZP_TRANS_ID, $attempt->getProviderTransactionId());
    }

    // ---- terminal-state / failure semantics (preserved round 2) ----

    /**
     * Round-3 case 8: a late paid callback on a terminal (STALE) attempt is
     * EVIDENCE ONLY — status never broadened, provider identity preserved,
     * ACK outcome (no order, retries useless).
     *
     * @return void
     */
    public function testLateCallbackOnStaleAttemptIsRecordedOnly(): void
    {
        $attempt = $this->newActiveAttempt()->markStale();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $attempt->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $attempt->getProviderTransactionId());
        $this->assertStringContainsString('manual reconciliation', (string)$attempt->getLastError());
    }

    /**
     * Transient finalization failure: the attempt stays PAID (money-real,
     * never FAILED) and the outcome is RETRYABLE (controller answers the
     * documented return_code 0 "callback again").
     *
     * @return void
     */
    public function testTransientFinalizationFailureReturnsRetryOutcome(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->orderFinalizer->method('finalizeOrRecover')
            ->willThrowException(new \RuntimeException('DB gone away'));

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_RETRYABLE_FAILURE, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertNull($attempt->getOrderId());
    }

    /**
     * Deterministic contract mismatch inside the finalizer: the lifecycle
     * quarantined the attempt with evidence; the callback is ACKNOWLEDGED.
     *
     * @return void
     */
    public function testContractMismatchAcknowledgesWithoutOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->orderFinalizer->method('finalizeOrRecover')
            ->willThrowException(new ContractMismatchException(__('quote total changed')));

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertNull($attempt->getOrderId());
    }

    // ---- Round-4 #1/#2: strict callback type identity ----

    /**
     * Round-4 #1: a callback WITHOUT the `type` envelope field is NOT an
     * Order notification — documented "Invalid" (return_code 2), NO lookup,
     * NO mutation, NO PAID, NO order.
     *
     * @return void
     */
    public function testCallbackWithoutTypeIsInvalidWithoutMutation(): void
    {
        $payload = $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000]);
        unset($payload['type']);
        $this->repository->expects($this->never())->method('getByAppTransId');
        $this->repository->expects($this->never())->method('lockByAppTransId');
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->assertSame(IpnProcessor::OUTCOME_INVALID_CALLBACK, $this->processor->process($payload));
    }

    /**
     * Round-4 #2: an Agreement callback (type 2) is NEVER accepted by the
     * order-payment endpoint — documented "Invalid", zero mutation, zero
     * lookup.
     *
     * @return void
     */
    public function testAgreementCallbackTypeIsInvalidWithoutMutation(): void
    {
        $payload = $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000]);
        $payload['type'] = 2;
        $this->repository->expects($this->never())->method('getByAppTransId');
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->assertSame(IpnProcessor::OUTCOME_INVALID_CALLBACK, $this->processor->process($payload));
    }

    /**
     * Round-4 #3: an explicit Order callback (type 1) drives the full
     * canonical lifecycle (companion to #1/#2 — the gate is a real gate).
     *
     * @return void
     */
    public function testOrderCallbackTypeOneIsAccepted(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover');

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
    }

    // ---- Round-4 #4/#5/#6: provider transaction id is REQUIRED ----

    /**
     * Round-4 #4: exact amount but MISSING zp_trans_id can NEVER finalize
     * directly — the authoritative v2/query must prove the identity first;
     * the finalizer runs with the QUERY's provider id, never an empty one.
     *
     * @return void
     */
    public function testExactAmountWithMissingProviderIdRequiresQueryIdentityProof(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => '990801000002',
        ]);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt), '990801000002');

        // payload WITHOUT the zp_trans_id key entirely.
        $outcome = $this->processor->process($this->payload(['amount' => 100000]));

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame('990801000002', $attempt->getProviderTransactionId());
    }

    /**
     * Round-4 #5: exact amount but zp_trans_id = 0 — zero is NOT a provider
     * identity: no direct finalization, the query decides. A still-processing
     * query leaves the attempt untouched (retryable).
     *
     * @return void
     */
    public function testExactAmountWithZeroProviderIdCannotFinalizeDirectly(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubQuery([AbstractResponseValidator::RETURN_CODE => 3]);
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => 0, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_RETRYABLE_FAILURE, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
    }

    /**
     * Round-4 #6: missing callback zp_trans_id + query SUCCESS + exact
     * amount + valid provider id → PAID + exactly one canonical order.
     *
     * @return void
     */
    public function testMissingProviderIdWithSuccessfulExactQueryFinalizes(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($attempt), self::ZP_TRANS_ID);

        $outcome = $this->processor->process($this->payload(['amount' => 100000]));

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
    }

    /**
     * Round-4 #6b: verified money with NO provable identity from EITHER
     * proof (callback and query both id-less) → money-real quarantine
     * (provider_transaction_unavailable), NO order.
     *
     * @return void
     */
    public function testVerifiedMoneyWithoutAnyProviderIdQuarantinesWithoutOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
        ]);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $outcome = $this->processor->process($this->payload(['amount' => 100000]));

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertTrue($this->saved->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_TX_UNAVAILABLE,
            $this->saved->getReconciliationCode()
        );
        $this->assertNull($this->saved->getProviderTransactionId());
        $this->assertNull($this->saved->getOrderId());
    }

    // ---- Round-4 #7: callback vs query provider id conflict ----

    /**
     * Round-4 #7: callback zp_trans_id A vs authoritative query zp_trans_id
     * B (A ≠ B) → provider_transaction_conflict quarantine with BOTH
     * identities preserved in the evidence — neither is silently preferred,
     * no order.
     *
     * @return void
     */
    public function testCallbackProviderIdConflictingWithQueryIdQuarantinesBothIdentities(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => '990801000002',
        ]);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        // No earlier recorded identity: the conflict is callback-vs-query.
        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => '110801000003', 'amount' => 0])
        );

        $this->assertSame(IpnProcessor::OUTCOME_ACK_RECONCILIATION, $outcome);
        $this->assertTrue($this->saved->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT,
            $this->saved->getReconciliationCode()
        );
        $evidence = (string)$this->saved->getLastError();
        // BOTH identities preserved verbatim — neither silently preferred.
        $this->assertStringContainsString('110801000003', $evidence);
        $this->assertStringContainsString('990801000002', $evidence);
        $this->assertNull($this->saved->getOrderId());
    }

    // ---- Round-4 #8: app_id validation ----

    /**
     * Round-4 #8: a MAC-valid payload whose signed app_id does not match
     * the configured application is a configuration/cross-environment error
     * — documented "Invalid" with ZERO mutation (state is never poisoned;
     * the recovery worker / a fixed configuration resolve the money later).
     *
     * @return void
     */
    public function testAppIdMismatchIsInvalidWithoutMutation(): void
    {
        $payload = $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000]);
        $payload['trans_data'][AbstractDataBuilder::APP_ID] = 9999; // wrong application
        $this->repository->expects($this->never())->method('getByAppTransId');
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $this->assertSame(IpnProcessor::OUTCOME_INVALID_CALLBACK, $this->processor->process($payload));
    }

    // ---- Round-4 #9/#10: malformed signed values ----

    /**
     * Round-4 #9: a MALFORMED signed amount inside a MAC-valid payload is a
     * provider anomaly — "Invalid" with zero mutation, and NEVER the
     * blind-cast-to-zero query-fallback path.
     *
     * @return void
     */
    public function testMalformedSignedAmountIsInvalidWithoutMutationOrQuery(): void
    {
        $payload = $this->payload(['zp_trans_id' => self::ZP_TRANS_ID]);
        $payload['trans_data'][AbstractResponseValidator::TOTAL_AMOUNT] = '12abc';
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');
        $this->commandPool->expects($this->never())->method('get');

        $this->assertSame(IpnProcessor::OUTCOME_INVALID_CALLBACK, $this->processor->process($payload));
    }

    /**
     * Round-4 #10: a MALFORMED signed zp_trans_id never reaches the
     * lifecycle — "Invalid", zero mutation, no query fallback.
     *
     * @return void
     */
    public function testMalformedSignedProviderIdIsInvalidWithoutMutation(): void
    {
        $payload = $this->payload(['amount' => 100000]);
        $payload['trans_data'][AbstractResponseValidator::ZP_TRANS_ID] = '24-01zz';
        $this->repository->expects($this->never())->method('save');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');
        $this->commandPool->expects($this->never())->method('get');

        $this->assertSame(IpnProcessor::OUTCOME_INVALID_CALLBACK, $this->processor->process($payload));
    }

    // ---- Round-4 #33: recovery exhaustion never blocks a valid IPN ----

    /**
     * Round-4 #33: an ACTIVE attempt whose proactive recovery budget is
     * exhausted (recovery_exhausted marker set) is still fully resolvable
     * by an exact valid authenticated IPN — exhaustion is operational, not
     * money-real, and never a quarantine.
     *
     * @return void
     */
    public function testRecoveryExhaustedAttemptIsStillResolvedByValidIpn(): void
    {
        $attempt = $this->newActiveAttempt();
        $attempt->setRecoveryExhausted(true);
        $this->stubAttempt($attempt);
        $this->stubSave();
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover');

        $outcome = $this->processor->process(
            $this->payload(['zp_trans_id' => self::ZP_TRANS_ID, 'amount' => 100000])
        );

        $this->assertSame(IpnProcessor::OUTCOME_SUCCESS, $outcome);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertFalse($attempt->getRequiresReconciliation());
    }

    // ---- helpers ----

    /**
     * Official Order callback envelope: `{data, mac, type: 1}` — the helper
     * always produces a VALID Order callback; round-4 tests strip/replace
     * the strict fields to prove the rejection gates.
     *
     * @param array $transData
     * @return array
     */
    private function payload(array $transData): array
    {
        $data = [
            AbstractDataBuilder::APP_ID => self::APP_ID,
            AbstractDataBuilder::APP_TRANS_ID => self::APP_TRANS_ID,
        ];
        if (array_key_exists('amount', $transData)) {
            $data[AbstractResponseValidator::TOTAL_AMOUNT] = $transData['amount'];
        }
        if (array_key_exists('zp_trans_id', $transData)) {
            $data[AbstractResponseValidator::ZP_TRANS_ID] = $transData['zp_trans_id'];
        }

        return [
            'data' => self::DATA_STRING,
            'mac' => self::VALID_MAC,
            'type' => 1,
            'trans_data' => $data,
        ];
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
     * An injected resource keeps _init() away from the (unit-test absent)
     * ObjectManager while providing the entity id field name.
     *
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
