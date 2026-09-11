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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Service\PaymentAttemptLifecycle;

/**
 * PaymentAttemptLifecycle — the CANONICAL payment-state mutation service
 * (corrective Blocker 2). Contract proven here:
 *  - every operation opens a SHORT transaction, locks the exact row
 *    (lockByAppTransId inside begin/commit) and re-evaluates the FRESH
 *    persisted status — a stale in-memory copy never decides;
 *  - ACTIVE -> PAID on verified money; idempotent no-op on PAID/FINALIZED
 *    (duplicate callbacks never re-mutate);
 *  - authoritative failure transitions ONLY where the state permits;
 *    PAID/FINALIZED are never regressed;
 *  - money-real late PAID evidence on FAILED/STALE/EXPIRED: evidence +
 *    provider transaction identity preserved, status NOT broadened;
 *  - amount mismatch keeps the attempt money-real with exact evidence;
 *  - unknown row -> rollback + LocalizedException; save failure -> rollback;
 *  - no mutation -> no save (still committed/locked).
 *
 * Corrective round 3 additions (Blockers 2 + 5):
 *  - recordContractMismatch() re-locks and mutates the FRESH row — the
 *    exact stale-writer race (finalizer's pre-rollback copy vs a racing
 *    FINALIZED binding) cannot regress FINALIZED, erase order_id or erase
 *    provider_transaction_id;
 *  - non-finalized refusals set the STRUCTURED quarantine
 *    (requires_reconciliation + reconciliation_code contract_mismatch);
 *  - a conflicting zp_trans_id (same app_trans_id, different authoritative
 *    id) quarantines money-real, keeping the FIRST recorded identity; the
 *    exact duplicate stays idempotent; the first reason code is kept.
 */
class PaymentAttemptLifecycleTest extends TestCase
{
    private const APP_TRANS_ID = '260826_1000_000000123';
    private const ZP_TRANS_ID = '240801000001';

    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var PaymentAttemptLifecycle
     */
    private $lifecycle;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->lifecycle = new PaymentAttemptLifecycle(
            $this->repository,
            $resourceConnection,
            $this->createMock(Logger::class)
        );
    }

    // ---- recordVerifiedPaid ----

    /**
     * ACTIVE + verified PAID: locked row transitions under a short
     * transaction (begin -> lock -> mutate -> save -> commit).
     *
     * @return void
     */
    public function testRecordVerifiedPaidTransitionsActiveRowUnderLock(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay.zalopay.vn/order/abc');
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save')->with($this->identicalTo($attempt));
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame($attempt, $fresh);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * Duplicate PAID callback: idempotent — the row is locked and
     * re-inspected but nothing is saved again.
     *
     * @return void
     */
    public function testRecordVerifiedPaidOnAlreadyPaidRowIsIdempotentNoSave(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->repository->expects($this->never())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
    }

    /**
     * PAID row missing its provider id (e.g. an earlier verification without
     * zp_trans_id): the id is backfilled exactly once.
     *
     * @return void
     */
    public function testRecordVerifiedPaidBackfillsMissingProviderTransactionId(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(null);
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
    }

    /**
     * FINALIZED row (e.g. the IPN finalized first, a Return arrives):
     * terminal success is never re-mutated, never saved.
     *
     * @return void
     */
    public function testRecordVerifiedPaidOnFinalizedRowNeverMutates(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID)->markFinalized(77);
        $this->stubLock($attempt);
        $this->repository->expects($this->never())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $fresh->getPaymentStatus());
        $this->assertSame(77, $fresh->getOrderId());
    }

    /**
     * Money-real late callback: authoritative PAID evidence on a STALE
     * (terminal) attempt preserves the evidence AND the provider transaction
     * identity WITHOUT broadening the terminal state — no order may follow.
     *
     * @return void
     */
    public function testLatePaidEvidenceOnStaleRowPreservesEvidenceAndProviderIdentity(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markStale();
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $fresh->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
        $this->assertStringContainsString(
            'Authoritative PAID evidence received while attempt is "stale"',
            (string)$fresh->getLastError()
        );
        $this->assertStringContainsString('zp_trans_id 240801000001', (string)$fresh->getLastError());
    }

    /**
     * Same evidence on FAILED — identical semantics (terminal not broadened).
     *
     * @return void
     */
    public function testLatePaidEvidenceOnFailedRowKeepsTerminalState(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markFailed('query said no.');
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $fresh->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
    }

    // ---- recordVerifiedFailure ----

    /**
     * ACTIVE + authoritative failure: FAILED transition with the reason.
     *
     * @return void
     */
    public function testRecordVerifiedFailureTransitionsActiveRow(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay');
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedFailure(self::APP_TRANS_ID, 'v2/query return_code 2.', 'failed');

        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $fresh->getPaymentStatus());
        $this->assertStringContainsString('return_code 2', (string)$fresh->getLastError());
        $this->assertSame('failed', $fresh->getProviderStatus());
    }

    /**
     * A failure claim NEVER regresses a money-real PAID row: state kept,
     * conflict recorded as reconciliation evidence.
     *
     * @return void
     */
    public function testRecordVerifiedFailureNeverRegressesPaidRow(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedFailure(self::APP_TRANS_ID, 'v2/query return_code 2.');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertStringContainsString('kept money-real for manual reconciliation', (string)$fresh->getLastError());
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * FINALIZED row + failure claim: untouched (an order exists; the claim
     * is stale) — never a backward transition.
     *
     * @return void
     */
    public function testRecordVerifiedFailureOnFinalizedRowIsNoop(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID)->markFinalized(77);
        $this->stubLock($attempt);
        $this->repository->expects($this->never())->method('save');

        $fresh = $this->lifecycle->recordVerifiedFailure(self::APP_TRANS_ID, 'v2/query return_code 2.');

        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $fresh->getPaymentStatus());
        $this->assertSame(77, $fresh->getOrderId());
    }

    /**
     * FAILED row + failure claim: idempotent, nothing saved.
     *
     * @return void
     */
    public function testRecordVerifiedFailureOnFailedRowIsIdempotent(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markFailed('earlier failure.');
        $this->stubLock($attempt);
        $this->repository->expects($this->never())->method('save');

        $fresh = $this->lifecycle->recordVerifiedFailure(self::APP_TRANS_ID, 'v2/query return_code 2.');

        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $fresh->getPaymentStatus());
        $this->assertSame('earlier failure.', $fresh->getLastError());
    }

    // ---- recordAmountMismatch ----

    /**
     * Amount mismatch: the attempt is kept money-real (PAID) with the exact
     * mismatch evidence — never auto-placed.
     *
     * @return void
     */
    public function testRecordAmountMismatchKeepsMoneyRealWithEvidence(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay');
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordAmountMismatch(self::APP_TRANS_ID, 50000, 'IPN', self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertStringContainsString('IPN amount mismatch: paid 50000, snapshot 100000', (string)$fresh->getLastError());
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * A stale caller copy compared against a row that actually matches: the
     * locked row decides — normal verified-paid handling, no mismatch
     * evidence.
     *
     * @return void
     */
    public function testRecordAmountMismatchOnMatchingLockedRowAppliesVerifiedPaid(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay'); // amount 100000
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordAmountMismatch(self::APP_TRANS_ID, 100000, 'IPN', self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertStringNotContainsString('mismatch', (string)$fresh->getLastError());
    }

    /**
     * Mismatch evidence on an already-PAID row: idempotent state, refreshed
     * evidence, still no order.
     *
     * @return void
     */
    public function testRecordAmountMismatchOnPaidRowKeepsPaidState(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordAmountMismatch(self::APP_TRANS_ID, 50000, 'Return', null);

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertStringContainsString('Return amount mismatch: paid 50000, snapshot 100000', (string)$fresh->getLastError());
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
    }

    // ---- recordContractMismatch (round 3, Blocker 2) ----

    /**
     * Round-3 case 5: a refused finalization on a non-finalized row sets the
     * STRUCTURED quarantine (requires_reconciliation + machine-readable
     * contract_mismatch code) with the exact reason — never free text alone.
     *
     * @return void
     */
    public function testRecordContractMismatchQuarantinesNonFinalizedRow(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordContractMismatch(self::APP_TRANS_ID, 'quote total changed.');

        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(PaymentAttemptInterface::RECON_CONTRACT_MISMATCH, $fresh->getReconciliationCode());
        $this->assertStringContainsString(
            'Contract mismatch — no automatic order creation: quote total changed.',
            (string)$fresh->getLastError()
        );
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * Round-3 case 6 — the EXACT stale-writer race model: the finalizer
     * released its lock believing the row was PAID; a racing writer bound
     * the order (FINALIZED) in between; THEN the refused finalizer records
     * its mismatch. The FRESH locked row decides: FINALIZED is never
     * regressed, the row is quarantined by NOTHING (a bound order must not
     * be poisoned), evidence only.
     *
     * @return void
     */
    public function testStaleMismatchWriterCannotRegressFinalizedRow(): void
    {
        // The row as the finalizer's stale copy saw it (pre-rollback).
        $staleCopy = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        // The row as it NOW exists after the racing FINALIZED binding.
        $freshRow = $this->newAttempt()->markActive('https://pay')
            ->markPaid(self::ZP_TRANS_ID)->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($freshRow);
        $this->repository->expects($this->once())->method('save')->with($this->identicalTo($freshRow));
        unset($staleCopy); // the stale copy plays no part — the lock re-reads

        $fresh = $this->lifecycle->recordContractMismatch(self::APP_TRANS_ID, 'quote total changed.');

        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $fresh->getPaymentStatus());
        $this->assertSame(77, $fresh->getOrderId());
        // Evidence only: NOT quarantined (a bound order must stay finalizable),
        // reason preserved for manual review.
        $this->assertFalse($fresh->getRequiresReconciliation());
        $this->assertNull($fresh->getReconciliationCode());
        $this->assertStringContainsString(
            'Contract mismatch (order already bound; manual review): quote total changed.',
            (string)$fresh->getLastError()
        );
    }

    /**
     * Round-3 case 7: recordContractMismatch NEVER clears order_id or
     * provider_transaction_id — proven on a FINALIZED row (the dangerous
     * stale-writer outcome: the evidence write lands on a bound order's
     * row, and the binding must survive intact).
     *
     * @return void
     */
    public function testMismatchEvidenceNeverErasesOrderOrProviderIdentity(): void
    {
        $finalized = $this->newAttempt()->markActive('https://pay')
            ->markPaid(self::ZP_TRANS_ID)->markFinalized(77);
        $this->stubLock($finalized);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordContractMismatch(self::APP_TRANS_ID, 'reason one.');

        $this->assertSame(77, $fresh->getOrderId());
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $fresh->getPaymentStatus());
    }

    /**
     * Round-3 case 5 (idempotence): a repeated refusal on an already
     * quarantined row keeps the FIRST reason code and does not save again
     * when nothing changed.
     *
     * @return void
     */
    public function testRecordContractMismatchIsIdempotentOnQuarantinedRow(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->lifecycle->recordContractMismatch(self::APP_TRANS_ID, 'first reason.');

        $this->repository->expects($this->never())->method('save');
        $fresh = $this->lifecycle->recordContractMismatch(self::APP_TRANS_ID, 'first reason.');

        $this->assertSame(
            PaymentAttemptInterface::RECON_CONTRACT_MISMATCH,
            $fresh->getReconciliationCode()
        );
        $this->assertSame(
            'Contract mismatch — no automatic order creation: first reason.',
            $fresh->getLastError()
        );
    }

    // ---- provider transaction identity conflicts (round 3, Blocker 5) ----

    /**
     * Round-3 case 19: same app_trans_id, DIFFERENT authoritative
     * zp_trans_id — the FIRST recorded identity is never overwritten, the
     * attempt is kept money-real and quarantined (not auto-finalizable).
     *
     * @return void
     */
    public function testConflictingProviderTransactionIdQuarantinesKeepingFirstIdentity(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, '999999999999');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT,
            $fresh->getReconciliationCode()
        );
        $this->assertStringContainsString(
            'recorded zp_trans_id 240801000001, authoritative proof claims 999999999999',
            (string)$fresh->getLastError()
        );
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * Round-3 case 20: the EXACT duplicate zp_trans_id is idempotent — NO
     * quarantine, no save, money-real state untouched.
     *
     * @return void
     */
    public function testExactDuplicateProviderTransactionIdNeverQuarantines(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->repository->expects($this->never())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertFalse($fresh->getRequiresReconciliation());
        $this->assertNull($fresh->getReconciliationCode());
    }

    /**
     * Round-3 case 19 on a terminal row: a conflicting proof on STALE keeps
     * the terminal state, keeps the first identity, and still quarantines —
     * a conflict is a conflict even when no transition is possible.
     *
     * @return void
     */
    public function testConflictingProviderTransactionIdOnStaleRowQuarantines(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markStale();
        $attempt->setProviderTransactionId(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, '999999999999');

        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $fresh->getPaymentStatus());
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT,
            $fresh->getReconciliationCode()
        );
    }

    /**
     * Round-3 case 18 support: the FIRST reconciliation reason code is kept
     * — a later different conflict cannot rewrite the quarantine code.
     *
     * @return void
     */
    public function testFirstReconciliationCodeIsKept(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        // First quarantine: amount mismatch.
        $this->lifecycle->recordAmountMismatch(self::APP_TRANS_ID, 50000, 'IPN', self::ZP_TRANS_ID);
        $this->assertSame(PaymentAttemptInterface::RECON_AMOUNT_MISMATCH, $attempt->getReconciliationCode());

        // Later conflicting identity: evidence appended, code unchanged.
        $this->repository->expects($this->once())->method('save');
        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, '999999999999');

        $this->assertSame(PaymentAttemptInterface::RECON_AMOUNT_MISMATCH, $fresh->getReconciliationCode());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertStringContainsString(
            'authoritative proof claims 999999999999',
            (string)$fresh->getLastError()
        );
    }

    // ---- round 4, Blocker 3: structured + STICKY conflicts ----

    /**
     * Round-4 #23/#24/#25: PAID + authoritative FAIL — the money-real PAID
     * state is NEVER regressed and the contradiction is recorded as a
     * STRUCTURED, machine-readable provider_state_conflict quarantine (not
     * free text alone).
     *
     * @return void
     */
    public function testPaidRowPlusAuthoritativeFailureIsStructuredQuarantine(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedFailure(self::APP_TRANS_ID, 'v2/query return_code 2.', 'failed');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_STATE_CONFLICT,
            $fresh->getReconciliationCode()
        );
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * Corrective round 5 (sticky-quarantine change-flag bug): the PAID +
     * authoritative-failure branch must AGGREGATE the quarantine-flag flip
     * with the last_error write. When requires_reconciliation flips
     * false -> true while last_error already holds the exact evidence
     * message, the flag mutation alone must still persist — the idempotent
     * "message unchanged" check returning false must never discard the
     * flag flip (a lost save here silently loses the quarantine, and a
     * second provider transaction becomes possible).
     *
     * @return void
     */
    public function testPaidQuarantineFlagFlipPersistsEvenWhenMessageAlreadyMatches(): void
    {
        $message = sprintf(
            'Authoritative failure evidence while attempt is "%s"; kept money-real for '
            . 'manual reconciliation (%s).',
            PaymentAttemptInterface::STATUS_PAID,
            'v2/query return_code 2.'
        );
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $attempt->setLastError($message);
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save')->with($this->identicalTo($attempt));

        $fresh = $this->lifecycle->recordVerifiedFailure(self::APP_TRANS_ID, 'v2/query return_code 2.', 'failed');

        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_STATE_CONFLICT,
            $fresh->getReconciliationCode()
        );
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertSame($message, $fresh->getLastError());
    }

    /**
     * Round-4 #26: a later authoritative SUCCESS can NOT auto-finalize a
     * provider_state_conflict-quarantined row — the flag is STICKY, the
     * first code is kept, the status never broadens into FINALIZED, and a
     * no-op re-verification does not even save.
     *
     * @return void
     */
    public function testLaterSuccessCannotAutoFinalizeProviderStateConflictRow(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID);
        $this->stubLock($attempt);
        $this->lifecycle->recordVerifiedFailure(self::APP_TRANS_ID, 'v2/query return_code 2.', 'failed');
        $this->assertTrue($attempt->getRequiresReconciliation());

        // The same app_trans_id later reports SUCCESS with the SAME identity.
        $this->repository->expects($this->never())->method('save');
        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertNull($fresh->getOrderId());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_STATE_CONFLICT,
            $fresh->getReconciliationCode()
        );
    }

    /**
     * Round-4 #27: late authoritative PAID evidence on a FAILED attempt is
     * recorded as the STRUCTURED late_paid_terminal_state quarantine — the
     * terminal state is not broadened, the provider identity is preserved,
     * no order can follow.
     *
     * @return void
     */
    public function testLatePaidOnFailedRowSetsStructuredQuarantine(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markFailed('query said no.');
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_LATE_PAID_TERMINAL_STATE,
            $fresh->getReconciliationCode()
        );
        $this->assertSame(self::ZP_TRANS_ID, $fresh->getProviderTransactionId());
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * Round-4 #28: identical structured semantics on STALE.
     *
     * @return void
     */
    public function testLatePaidOnStaleRowSetsStructuredQuarantine(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markStale();
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_LATE_PAID_TERMINAL_STATE,
            $fresh->getReconciliationCode()
        );
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * Round-4 #29: identical structured semantics on EXPIRED.
     *
     * @return void
     */
    public function testLatePaidOnExpiredRowSetsStructuredQuarantine(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay');
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_EXPIRED);
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame(PaymentAttemptInterface::STATUS_EXPIRED, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_LATE_PAID_TERMINAL_STATE,
            $fresh->getReconciliationCode()
        );
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * Round-4 #30: quarantine STICKINESS — once requires_reconciliation is
     * set, normal callbacks (verified paid, verified failure) NEVER clear it
     * or rewrite the code; they only append evidence. No order path exists
     * through any of them.
     *
     * @return void
     */
    public function testNormalCallbacksNeverClearTheReconciliationQuarantine(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay')->markFailed('query said no.');
        $this->stubLock($attempt);
        $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);
        $this->assertTrue($attempt->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_LATE_PAID_TERMINAL_STATE,
            $attempt->getReconciliationCode()
        );

        // Later normal traffic: the duplicate success is a no-op (nothing
        // saved); the verified failure on the already-FAILED row is equally
        // idempotent. Neither clears the flag nor rewrites the code.
        $this->repository->expects($this->never())->method('save');
        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_LATE_PAID_TERMINAL_STATE,
            $fresh->getReconciliationCode()
        );

        $afterFailure = $this->lifecycle->recordVerifiedFailure(self::APP_TRANS_ID, 'v2/query return_code 2.');
        $this->assertTrue($afterFailure->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_LATE_PAID_TERMINAL_STATE,
            $afterFailure->getReconciliationCode()
        );
    }

    /**
     * Round-4 #7 (lifecycle level): a provider transaction IDENTITY CONFLICT
     * between two authoritative proofs quarantines money-real with BOTH
     * identities preserved verbatim — neither silently preferred, no order.
     *
     * @return void
     */
    public function testProviderIdentityConflictPreservesBothIdentities(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay');
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordProviderIdentityConflict(
            self::APP_TRANS_ID,
            '110801000003',
            '990801000002',
            'IPN'
        );

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT,
            $fresh->getReconciliationCode()
        );
        $evidence = (string)$fresh->getLastError();
        $this->assertStringContainsString('110801000003', $evidence);
        $this->assertStringContainsString('990801000002', $evidence);
        $this->assertNull($fresh->getOrderId());
    }

    /**
     * Round-4 #4/#5 (lifecycle level): verified money whose provider
     * identity could NOT be proven quarantines money-real WITHOUT a provider
     * id — provider_transaction_unavailable, structurally un-finalizable.
     *
     * @return void
     */
    public function testProviderIdentityUnavailableQuarantinesWithoutOrder(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay');
        $this->stubLock($attempt);
        $this->repository->expects($this->once())->method('save');

        $fresh = $this->lifecycle->recordProviderIdentityUnavailable(self::APP_TRANS_ID, 'IPN-query');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_TX_UNAVAILABLE,
            $fresh->getReconciliationCode()
        );
        $this->assertNull($fresh->getProviderTransactionId());
        $this->assertNull($fresh->getOrderId());
    }

    // ---- transaction discipline ----

    /**
     * Unknown app_trans_id under the lock: rollback, LocalizedException,
     * no save.
     *
     * @return void
     */
    public function testUnknownRowRollsBackAndThrows(): void
    {
        $this->repository->method('lockByAppTransId')->willReturn(null);
        $this->repository->expects($this->never())->method('save');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('no longer exists');
        $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);
    }

    /**
     * A save failure inside the locked unit rolls the transaction back and
     * surfaces the original exception.
     *
     * @return void
     */
    public function testSaveFailureRollsBackAndRethrows(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay');
        $this->stubLock($attempt);
        $this->repository->method('save')
            ->willThrowException(new \Magento\Framework\Exception\CouldNotSaveException(__('DB down.')));
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\Magento\Framework\Exception\CouldNotSaveException::class);
        $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);
    }

    /**
     * The FRESH row decides — not the caller's stale copy: the lifecycle
     * locks by app_trans_id and the returned attempt is the locked one.
     *
     * @return void
     */
    public function testDecisionIsMadeOnTheLockedFreshRow(): void
    {
        $staleCopy = $this->newAttempt()->markActive('https://pay'); // caller believes ACTIVE
        $freshRow = $this->newAttempt()->markActive('https://pay')->markPaid(self::ZP_TRANS_ID)->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($freshRow);
        $this->repository->expects($this->never())->method('save');
        unset($staleCopy); // prove it plays no part

        $fresh = $this->lifecycle->recordVerifiedPaid(self::APP_TRANS_ID, self::ZP_TRANS_ID);

        $this->assertSame($freshRow, $fresh);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $fresh->getPaymentStatus());
    }

    // ---- helpers ----

    /**
     * @param PaymentAttempt $attempt
     * @return void
     */
    private function stubLock(PaymentAttempt $attempt): void
    {
        $this->repository->method('lockByAppTransId')->with(self::APP_TRANS_ID)->willReturn($attempt);
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
        $attempt->setAppTransId(self::APP_TRANS_ID);
        $attempt->setAmount(100000);
        $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
        $attempt->setExpiresAt('2099-01-01 00:00:00');
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_INITIATED);

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
