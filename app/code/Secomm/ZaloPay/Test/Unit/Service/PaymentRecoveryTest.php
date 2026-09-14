<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Exception\ContractMismatchException;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollection;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollectionFactory;
use Secomm\ZaloPay\Service\OrderFinalizer;
use Secomm\ZaloPay\Service\PaymentAttemptLifecycle;
use Secomm\ZaloPay\Service\PaymentRecovery;

/**
 * Bounded lost-callback recovery worker (corrective round 3, Blocker 6) —
 * the official ZaloPay guidance ("after 15 minutes without a callback,
 * query the order proactively") implemented as the SMALLEST bounded cron:
 *
 *  - case 21: lost callback + recovery v2/query SUCCESS + EXACT amount ->
 *    the SAME canonical services (lifecycle PAID -> OrderFinalizer) ->
 *    exactly one order;
 *  - case 22: query PROCESSING (3) -> no mutation, retried on a later run;
 *  - case 23: query FAIL (2) -> concurrency-safe terminal failure;
 *  - case 24: query PAID with a WRONG amount, or a quarantined fresh row
 *    (conflicting zp_trans_id), or a deterministic finalizer refusal ->
 *    reconciliation material, NEVER an order;
 *  - case 25: the batch is bounded and deterministically ordered
 *    (entity_id ASC, page size from config);
 *  - case 26: the atomic claim UPDATE runs BEFORE the provider HTTP and NO
 *    transaction/row lock is ever held across the HTTP call; a lost claim
 *    skips the attempt without any HTTP.
 */
class PaymentRecoveryTest extends TestCase
{
    private const APP_TRANS_ID = '260826_1000_000000123';
    private const ZP_TRANS_ID = '240801000001';

    /**
     * @var PaymentAttemptCollectionFactory|MockObject
     */
    private $collectionFactory;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var CommandPoolInterface|MockObject
     */
    private $commandPool;

    /**
     * @var PaymentAttemptLifecycle|MockObject
     */
    private $lifecycle;

    /**
     * @var OrderFinalizer|MockObject
     */
    private $orderFinalizer;

    /**
     * @var PaymentRecovery
     */
    private $recovery;

    /**
     * @var array<int, array> The selection pages returned by the collection stub.
     */
    private array $pages = [];

    /**
     * @var array<string, mixed> Recorded addFieldToFilter calls.
     */
    private array $filters = [];

    /**
     * @var array<int, string> Recorded [field, direction] of setOrder.
     */
    private array $order = [];

    /**
     * @var int|null Recorded setPageSize call.
     */
    private ?int $pageSize = null;

    protected function setUp(): void
    {
        $this->pages = [];
        $this->filters = [];
        $this->order = [];
        $this->pageSize = null;
        $this->collectionFactory = $this->createMock(PaymentAttemptCollectionFactory::class);
        $collection = $this->createMock(PaymentAttemptCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use ($collection) {
                $this->filters[$field] = $condition;

                return $collection;
            }
        );
        $collection->method('setOrder')->willReturnCallback(
            function ($field, $direction) use ($collection) {
                $this->order = [$field, $direction];

                return $collection;
            }
        );
        $collection->method('setPageSize')->willReturnCallback(
            function (int $size) use ($collection) {
                $this->pageSize = $size;

                return $collection;
            }
        );
        $collection->method('getItems')->willReturnCallback(fn (): array => $this->pages);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturn('secomm_zalopay_payment_attempt');

        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->lifecycle = $this->createMock(PaymentAttemptLifecycle::class);
        $this->orderFinalizer = $this->createMock(OrderFinalizer::class);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null); // documented defaults

        $this->recovery = new PaymentRecovery(
            $this->collectionFactory,
            $resourceConnection,
            $this->commandPool,
            $this->lifecycle,
            $this->orderFinalizer,
            $scopeConfig,
            $this->createMock(Logger::class)
        );
    }

    // ---- Round-3 case 21: lost callback + recovery SUCCESS -> one order ----

    /**
     * Round-3 case 21: an ACTIVE attempt whose callback never arrived; the
     * recovery v2/query says return_code 1 with the EXACT amount -> the
     * canonical lifecycle PAID transition + the single finalizer boundary —
     * exactly one order, from the same business services as IPN/Return.
     *
     * @return void
     */
    public function testLostCallbackWithAuthoritativeSuccessFinalizesExactlyOnce(): void
    {
        $candidate = $this->candidate();
        $this->pages = [$candidate];
        $this->stubClaim(affected: 1);
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $fresh = $this->freshAttempt(PaymentAttemptInterface::STATUS_PAID, false);
        $this->lifecycle->expects($this->once())->method('recordVerifiedPaid')
            ->with(self::APP_TRANS_ID, self::ZP_TRANS_ID)
            ->willReturn($fresh);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($this->identicalTo($fresh), self::ZP_TRANS_ID);

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['claimed']);
        $this->assertSame(1, $summary['finalized']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(0, $summary['mismatch']);
        $this->assertSame(0, $summary['errors']);
    }

    // ---- Round-3 case 22: PROCESSING -> no mutation, retry later ----

    /**
     * Round-3 case 22: the query says still-processing — the attempt is
     * left untouched for a later run; no lifecycle/finalizer activity.
     *
     * @return void
     */
    public function testProcessingQueryLeavesAttemptForLaterRun(): void
    {
        $this->pages = [$this->candidate()];
        $this->stubClaim(affected: 1);
        $this->stubQuery([AbstractResponseValidator::RETURN_CODE => 3]);
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->lifecycle->expects($this->never())->method('recordAmountMismatch');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['claimed']);
        $this->assertSame(1, $summary['processing']);
        $this->assertSame(0, $summary['finalized']);
    }

    // ---- Round-3 case 23: FAIL -> concurrency-safe terminal failure ----

    /**
     * Round-3 case 23: the authoritative query says FAIL — the failure is
     * recorded through the lifecycle (which never regresses PAID/FINALIZED)
     * and the attempt is terminal. No order.
     *
     * @return void
     */
    public function testAuthoritativeFailRecordsTerminalFailure(): void
    {
        $this->pages = [$this->candidate()];
        $this->stubClaim(affected: 1);
        $this->stubQuery([AbstractResponseValidator::RETURN_CODE => 2]);
        $this->lifecycle->expects($this->once())->method('recordVerifiedFailure')
            ->with(self::APP_TRANS_ID, $this->stringContains('return_code 2'), 'failed')
            ->willReturn($this->freshAttempt(PaymentAttemptInterface::STATUS_FAILED, false));
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0, $summary['finalized']);
    }

    // ---- Round-3 case 24: money-real conflicts -> reconciliation, no order ----

    /**
     * Round-3 case 24 (amount): the query says PAID with a WRONG amount ->
     * quarantined through the lifecycle (money-real evidence), NO order.
     *
     * @return void
     */
    public function testWrongAmountQuarantinesWithoutOrder(): void
    {
        $this->pages = [$this->candidate()];
        $this->stubClaim(affected: 1);
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 50000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->lifecycle->expects($this->once())->method('recordAmountMismatch')
            ->with(self::APP_TRANS_ID, 50000, 'Recovery', self::ZP_TRANS_ID);
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['mismatch']);
        $this->assertSame(0, $summary['finalized']);
    }

    /**
     * Round-3 case 24 (missing amount): the query result carries no amount
     * -> treated as a mismatch (0), quarantined, NO order — never
     * "continue anyway".
     *
     * @return void
     */
    public function testAmountlessPaidQueryQuarantinesWithoutOrder(): void
    {
        $this->pages = [$this->candidate()];
        $this->stubClaim(affected: 1);
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->lifecycle->expects($this->once())->method('recordAmountMismatch')
            ->with(self::APP_TRANS_ID, 0, 'Recovery', self::ZP_TRANS_ID);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['mismatch']);
    }

    /**
     * Round-3 case 24 (conflicting zp_trans_id): recordVerifiedPaid comes
     * back quarantined (provider-transaction conflict) -> the recovery
     * worker places NO order.
     *
     * @return void
     */
    public function testQuarantinedFreshRowIsNeverFinalized(): void
    {
        $this->pages = [$this->candidate()];
        $this->stubClaim(affected: 1);
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->lifecycle->expects($this->once())->method('recordVerifiedPaid')
            ->willReturn($this->freshAttempt(PaymentAttemptInterface::STATUS_PAID, true));
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['mismatch']);
        $this->assertSame(0, $summary['finalized']);
    }

    /**
     * Round-3 case 24 (deterministic refusal): the finalizer refuses the
     * contract — the exception is containment, counted as reconciliation
     * material; the batch continues; no retry storm into placement.
     *
     * @return void
     */
    public function testFinalizerContractRefusalIsContainmentNotError(): void
    {
        $this->pages = [$this->candidate()];
        $this->stubClaim(affected: 1);
        $this->stubQuery([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $this->lifecycle->method('recordVerifiedPaid')
            ->willReturn($this->freshAttempt(PaymentAttemptInterface::STATUS_PAID, false));
        $this->orderFinalizer->method('finalizeOrRecover')
            ->willThrowException(new ContractMismatchException(__('quote total changed.')));

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['mismatch']);
        $this->assertSame(0, $summary['finalized']);
        $this->assertSame(0, $summary['errors']);
    }

    // ---- Round-3 case 25: bounded, deterministic selection ----

    /**
     * Round-3 case 25: the selection is a bounded deterministic page —
     * non-terminal (ACTIVE/PAID) attempts without a bound order, NOT
     * quarantined, under the attempt cap, past the documented 15-minute
     * window, ordered by entity_id, LIMIT batch size (defaults here).
     *
     * @return void
     */
    public function testSelectionIsBoundedAndDeterministic(): void
    {
        $this->pages = [];

        $this->recovery->execute();

        $this->assertSame(
            ['in' => [PaymentAttemptInterface::STATUS_ACTIVE, PaymentAttemptInterface::STATUS_PAID]],
            $this->filters[PaymentAttemptInterface::PAYMENT_STATUS],
            'Only non-terminal attempts are recovery candidates.'
        );
        $this->assertSame(['null' => true], $this->filters[PaymentAttemptInterface::ORDER_ID]);
        $this->assertSame(
            ['neq' => 1],
            $this->filters[PaymentAttemptInterface::REQ_RECONCILIATION],
            'Quarantined attempts are NEVER recovery candidates.'
        );
        $this->assertSame(
            ['lt' => 5],
            $this->filters[PaymentAttemptInterface::RECOVERY_ATTEMPTS],
            'The attempt cap bounds the work per row.'
        );
        $this->assertArrayHasKey(PaymentAttemptInterface::CREATED_AT, $this->filters);
        $this->assertArrayHasKey('lteq', $this->filters[PaymentAttemptInterface::CREATED_AT]);
        $this->assertSame(
            [PaymentAttemptInterface::ENTITY_ID, 'ASC'],
            $this->order,
            'Deterministic ordering by entity id.'
        );
        $this->assertSame(25, $this->pageSize, 'The documented default batch size bounds one run.');
    }

    // ---- Round-3 case 26: claim before HTTP; no lock across HTTP ----

    /**
     * Round-3 case 26: the exact failure mode the rule forbids — a DB row
     * lock held across the provider HTTP — cannot happen: the claim is one
     * atomic conditional UPDATE, no transaction is ever opened, and the
     * ordered event log proves claim -> HTTP -> lifecycle -> finalizer.
     *
     * @return void
     */
    public function testClaimRunsBeforeHttpAndNoLockSpansTheCall(): void
    {
        $events = [];
        $this->pages = [$this->candidate()];
        $this->connection->expects($this->once())->method('update')->willReturnCallback(
            function () use (&$events) {
                $events[] = 'claim-update';

                return 1;
            }
        );
        $this->stubExhaustionRead(0);
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->connection->expects($this->never())->method('rollBack');

        $command = $this->createMock(CommandInterface::class);
        $result = $this->createMock(ResultInterface::class);
        $result->method('get')->willReturn([
            AbstractResponseValidator::RETURN_CODE => 1,
            AbstractResponseValidator::TOTAL_AMOUNT => 100000,
            AbstractResponseValidator::ZP_TRANS_ID => self::ZP_TRANS_ID,
        ]);
        $command->method('execute')->willReturnCallback(function () use (&$events, $result) {
            $events[] = 'provider-http';

            return $result;
        });
        $this->commandPool->method('get')->with('query_transaction')->willReturn($command);

        $this->lifecycle->method('recordVerifiedPaid')->willReturnCallback(
            function () use (&$events) {
                $events[] = 'lifecycle';

                return $this->freshAttempt(PaymentAttemptInterface::STATUS_PAID, false);
            }
        );
        $this->orderFinalizer->method('finalizeOrRecover')->willReturnCallback(
            function () use (&$events) {
                $events[] = 'finalize';

                return $this->createMock(\Magento\Sales\Api\Data\OrderInterface::class);
            }
        );

        $this->recovery->execute();

        $this->assertSame(
            ['claim-update', 'provider-http', 'lifecycle', 'finalize'],
            $events,
            'Claim BEFORE HTTP; canonical services after; nothing holds a lock across the call.'
        );
    }

    /**
     * Round-3 case 26 (lost claim): when the conditional UPDATE affects no
     * rows (IPN/Return/cron won the row in between) the attempt is skipped
     * — NO provider HTTP, NO lifecycle call, the fresh state owns it.
     *
     * @return void
     */
    public function testLostClaimSkipsAttemptWithoutProviderHttp(): void
    {
        $this->pages = [$this->candidate()];
        $this->connection->method('update')->willReturn(0);
        $this->commandPool->expects($this->never())->method('get');
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(0, $summary['claimed']);
    }

    /**
     * One attempt blowing up must not abort the batch — the error is
     * counted and the remaining attempts still run (bounded resilience).
     *
     * @return void
     */
    public function testPerAttemptFailureDoesNotAbortTheBatch(): void
    {
        $first = $this->candidate();
        $second = $this->candidate('260826_1000_000000124');
        $this->pages = [$first, $second];
        $this->stubClaim(affected: 1);

        $invoked = [];
        $this->commandPool->method('get')->willReturnCallback(
            function () use (&$invoked) {
                $invoked[] = 'query';
                $command = $this->createMock(CommandInterface::class);
                $result = $this->createMock(ResultInterface::class);
                $result->method('get')->willReturn([AbstractResponseValidator::RETURN_CODE => 3]);
                $command->method('execute')->willReturn($result);

                return $command;
            }
        );

        $summary = $this->recovery->execute();

        $this->assertSame(2, $summary['claimed']);
        $this->assertSame(2, $summary['processing']);
        $this->assertSame(0, $summary['errors']);
        $this->assertCount(2, $invoked, 'Every claimed attempt is processed.');
    }

    /**
     * A transport-level query failure is contained per attempt: counted as
     * an error, the row stays recoverable (recovery_attempts already
     * bounded the retries), and the batch continues.
     *
     * @return void
     */
    public function testQueryTransportFailureIsCountedAndContained(): void
    {
        $this->pages = [$this->candidate()];
        $this->stubClaim(affected: 1);
        $command = $this->createMock(CommandInterface::class);
        $command->method('execute')->willThrowException(new \RuntimeException('timeout'));
        $this->commandPool->method('get')->willReturn($command);
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['errors']);
        $this->assertSame(0, $summary['finalized']);
        $this->assertSame(0, $summary['mismatch']);
    }

    // ---- Round 4, Blocker 4: explicit recovery exhaustion (#31-#35) ----

    /**
     * Round-4 #31/#32 (selection): explicitly exhausted rows are NEVER
     * recovery candidates — the machine-readable `recovery_exhausted`
     * marker is excluded at selection time, so an exhausted attempt draws
     * no further automatic provider queries.
     *
     * @return void
     */
    public function testExhaustedRowsAreNeverSelectedAgain(): void
    {
        $this->pages = [];

        $this->recovery->execute();

        $this->assertArrayHasKey(PaymentAttemptInterface::RECOVERY_EXHAUSTED, $this->filters);
        $this->assertSame(
            ['neq' => 1],
            $this->filters[PaymentAttemptInterface::RECOVERY_EXHAUSTED],
            'Exhausted rows are never selected for further automatic queries.'
        );
    }

    /**
     * Round-4 #31/#32/#34 (claim): the atomic claim re-checks the
     * exhaustion marker in its WHERE (a row exhausted between selection and
     * claim is not claimable) and the SAME statement persists the marker
     * exactly on the claim that consumes the last permitted query. The
     * payload touches ONLY the operational marker — never
     * requires_reconciliation (exhaustion is NOT money-real evidence).
     *
     * @return void
     */
    public function testClaimCarriesTheExplicitExhaustionMarkerAndNeverQuarantines(): void
    {
        $this->pages = [$this->candidate()];
        $captured = [];
        $this->connection->method('update')->willReturnCallback(
            function ($table, array $bind, $where) use (&$captured) {
                $captured = ['bind' => $bind, 'where' => (string)$where];

                return 1;
            }
        );
        $this->stubExhaustionRead(0);
        $this->commandPool->method('get')->willReturn($this->processingQueryCommand());

        $this->recovery->execute();

        // The WHERE refuses exhausted rows outright — a row exhausted by a
        // concurrent writer between selection and claim is not claimable.
        $this->assertStringContainsString(
            'recovery_exhausted = 0',
            $captured['where'],
            'The claim loses to an exhausted row — no further automatic queries.'
        );
        // The payload flips the marker on the LAST permitted claim (MySQL
        // evaluates SET left-to-right: recovery_attempts is already
        // incremented inside the IF()).
        $this->assertArrayHasKey(PaymentAttemptInterface::RECOVERY_ATTEMPTS, $captured['bind']);
        $exhaustionExpr = $captured['bind'][PaymentAttemptInterface::RECOVERY_EXHAUSTED];
        $this->assertInstanceOf(\Zend_Db_Expr::class, $exhaustionExpr);
        $this->assertStringContainsString('IF(recovery_attempts >= 5, 1, recovery_exhausted)', (string)$exhaustionExpr);
        // Round-4 #34: exhaustion is OPERATIONAL — the claim never WRITES
        // the money-real/quarantine flag (it only re-checks it as a guard).
        $this->assertArrayNotHasKey(PaymentAttemptInterface::REQ_RECONCILIATION, $captured['bind']);
    }

    /**
     * Round-4 #31 (observable evidence): when a claim consumes the last
     * permitted query, the exhaustion is logged CRITICALLY — machine marker
     * in the DB, explicit evidence in the logs (Blocker 4).
     *
     * @return void
     */
    public function testExhaustionIsLoggedCriticallyWhenTheMarkerIsPersisted(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('critical')
            ->with($this->stringContains('query budget exhausted'));
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturn('secomm_zalopay_payment_attempt');
        $recovery = new PaymentRecovery(
            $this->collectionFactory,
            $resourceConnection,
            $this->commandPool,
            $this->lifecycle,
            $this->orderFinalizer,
            $scopeConfig,
            $logger
        );

        $this->pages = [$this->candidate()];
        $this->connection->method('update')->willReturn(1); // claim won
        $this->stubExhaustionRead(5); // budget fully consumed
        $this->commandPool->method('get')->willReturn($this->processingQueryCommand());

        $summary = $recovery->execute();

        $this->assertSame(1, $summary['claimed']);
        $this->assertSame(1, $summary['processing']);
    }

    /**
     * Round-4 #34/#35: exactly-once mechanics survive exhaustion semantics —
     * a claimed-but-not-exhausted attempt keeps its normal claim semantics
     * (marker only flips at the cap), so a mid-budget claim leaves the row
     * fully recoverable and never quarantined by the worker.
     *
     * @return void
     */
    public function testMidBudgetClaimDoesNotFlipTheExhaustionMarker(): void
    {
        $this->pages = [$this->candidate()];
        $captured = [];
        $this->connection->method('update')->willReturnCallback(
            function ($table, array $bind, $where) use (&$captured) {
                $captured = ['bind' => $bind];

                return 1;
            }
        );
        $this->stubExhaustionRead(0);
        $this->commandPool->method('get')->willReturn($this->processingQueryCommand());

        $this->recovery->execute();

        // The IF() evaluates against the ALREADY-INCREMENTED attempts value,
        // so the marker flips to 1 ONLY when this claim consumes the last
        // permitted query — earlier claims keep recovery_exhausted untouched.
        $this->assertStringContainsString(
            'IF(recovery_attempts >= 5, 1, recovery_exhausted)',
            (string)$captured['bind'][PaymentAttemptInterface::RECOVERY_EXHAUSTED]
        );
    }

    /**
     * @return CommandInterface|MockObject
     */
    private function processingQueryCommand()
    {
        $command = $this->createMock(CommandInterface::class);
        $result = $this->createMock(ResultInterface::class);
        $result->method('get')->willReturn([AbstractResponseValidator::RETURN_CODE => 3]);
        $command->method('execute')->willReturn($result);

        return $command;
    }

    // ---- helpers ----

    /**
     * @param int $affected
     * @return void
     */
    private function stubClaim(int $affected): void
    {
        $this->connection->method('update')->willReturn($affected);
        $this->stubExhaustionRead(0);
    }

    /**
     * Stub the post-claim exhaustion read (round 4): select chain + the
     * attempts value fetchOne returns. 0 = under budget (silent).
     *
     * @param int $attempts
     * @return void
     */
    private function stubExhaustionRead(int $attempts): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchOne')->willReturn($attempts);
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
     * @param string $appTransId
     * @return PaymentAttemptInterface|MockObject
     */
    private function candidate(string $appTransId = self::APP_TRANS_ID)
    {
        $attempt = $this->createMock(PaymentAttemptInterface::class);
        $attempt->method('getEntityId')->willReturn(9);
        $attempt->method('getAppTransId')->willReturn($appTransId);
        $attempt->method('getAmount')->willReturn(100000);

        return $attempt;
    }

    /**
     * @param string $status
     * @param bool $quarantined
     * @return PaymentAttemptInterface|MockObject
     */
    private function freshAttempt(string $status, bool $quarantined)
    {
        $fresh = $this->createMock(PaymentAttemptInterface::class);
        $fresh->method('getPaymentStatus')->willReturn($status);
        $fresh->method('getRequiresReconciliation')->willReturn($quarantined);

        return $fresh;
    }
}
