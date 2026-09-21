<?php
/**
 * Unit test for the bounded MoMo lost-IPN recovery worker (MOMO-03).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select as DbSelect;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\CommandInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Exception\ContractMismatchException;
use Secomm\MoMo\Model\PaymentAttempt;
use Secomm\MoMo\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollection;
use Secomm\MoMo\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollectionFactory;
use Secomm\MoMo\Service\OrderFinalizer;
use Secomm\MoMo\Service\PaymentRecovery;
use Secomm\MoMo\Service\PaymentAttemptLifecycle;

/**
 * Verifies the bounded lost-IPN recovery worker (issue #5): verified PAID
 * routes through the canonical lifecycle + finalizer exactly once, pending
 * and ambiguous outcomes never mutate, the query identity is the attempt's
 * original order_ref, and the budget/exhaustion bounds hold.
 */
class PaymentRecoveryTest extends TestCase
{
    private PaymentAttemptCollectionFactory&MockObject $collectionFactory;

    private PaymentAttemptCollection&MockObject $collection;

    private ResourceConnection&MockObject $resourceConnection;

    private AdapterInterface&MockObject $connection;

    private DbSelect&MockObject $select;

    private CommandPoolInterface&MockObject $commandPool;

    private CommandInterface&MockObject $queryCommand;

    private PaymentAttemptLifecycle&MockObject $lifecycle;

    private OrderFinalizer&MockObject $orderFinalizer;

    private ScopeConfigInterface&MockObject $scopeConfig;

    private LoggerInterface&MockObject $logger;

    private PaymentRecovery $recovery;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(PaymentAttemptCollectionFactory::class);
        $this->collection = $this->createMock(PaymentAttemptCollection::class);
        $this->collectionFactory->method('create')->willReturn($this->collection);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(
            static fn (string $name): string => $name
        );
        $this->select = $this->createMock(DbSelect::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->connection->method('select')->willReturn($this->select);
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->queryCommand = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->with('query_transaction')->willReturn($this->queryCommand);
        $this->lifecycle = $this->createMock(PaymentAttemptLifecycle::class);
        $this->orderFinalizer = $this->createMock(OrderFinalizer::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->recovery = new PaymentRecovery(
            $this->collectionFactory,
            $this->resourceConnection,
            $this->commandPool,
            $this->lifecycle,
            $this->orderFinalizer,
            $this->scopeConfig,
            $this->logger
        );
    }

    /**
     * AC1: a claimed active attempt whose v2/query confirms PAID with the
     * exact amount and a positive transId finalizes exactly one order via
     * the canonical finalizer, with the authoritative transId.
     *
     * @return void
     */
    public function testVerifiedPaidFinalizesViaCanonicalFinalizer(): void
    {
        $candidate = $this->attempt('active');
        $this->candidates([$candidate]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 0, 'amount' => 150000, 'transId' => '987654321']);
        $fresh = $this->attempt('paid', ['provider_transaction_id' => '987654321']);
        $this->lifecycle->expects($this->once())->method('recordVerifiedPaid')
            ->with('MOMOREF', '987654321')->willReturn($fresh);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($fresh, '987654321');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['claimed']);
        $this->assertSame(1, $summary['finalized']);
        $this->assertSame(0, $summary['mismatch']);
    }

    /**
     * AC4: a second recovery pass over an attempt that is already PAID (the
     * IPN resolved it between runs) is idempotent — the lifecycle keeps the
     * money-real state and the finalizer recovers the bound order once.
     *
     * @return void
     */
    public function testRepeatedPaidRecoveryIsIdempotent(): void
    {
        $candidate = $this->attempt('paid');
        $this->candidates([$candidate]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 0, 'amount' => 150000, 'transId' => '987654321']);
        $this->lifecycle->expects($this->once())->method('recordVerifiedPaid')
            ->willReturn($candidate);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['finalized']);
    }

    /**
     * AC2: provider still processing — no mutation at all.
     *
     * @return void
     */
    public function testPendingResultMutatesNothing(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 7002, 'amount' => 150000]);
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['pending']);
    }

    /**
     * AC3: a response without a parseable resultCode supports no payment
     * decision — ambiguous, no mutation, no false failure.
     *
     * @return void
     */
    public function testUnparseableResultCodeIsAmbiguousNoMutation(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['amount' => 150000]);
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['ambiguous']);
    }

    /**
     * AC3: a query transport/timeout failure is ambiguous — logged, no
     * mutation, never a verified failure.
     *
     * @return void
     */
    public function testQueryFailureIsAmbiguousNotFalseFail(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryCommand->method('execute')->willThrowException(
            new CommandException(new Phrase('timeout'))
        );
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['ambiguous']);
    }

    /**
     * Authoritative PAID with a DIFFERENT amount is money-real but never
     * order-able: quarantined via the lifecycle, no finalizer call.
     *
     * @return void
     */
    public function testAmountMismatchQuarantinesWithoutOrder(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 0, 'amount' => 999, 'transId' => '987654321']);
        $this->lifecycle->expects($this->once())->method('recordAmountMismatch')
            ->with('MOMOREF', 999, 'Recovery', '987654321');
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['mismatch']);
    }

    /**
     * Verified PAID without a positive transId lacks a proven provider
     * identity: identity-unavailable quarantine, no order.
     *
     * @return void
     */
    public function testMissingTransIdQuarantinesWithoutOrder(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 0, 'amount' => 150000, 'transId' => '0']);
        $this->lifecycle->expects($this->once())->method('recordProviderIdentityUnavailable')
            ->with('MOMOREF', 'Recovery-query');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['mismatch']);
    }

    /**
     * A code DOCUMENTED as a final payment-transaction failure (1001 —
     * explicitly allowlisted) is an authoritative failure where the fresh
     * state permits — lifecycle only, never an order. Unmapped codes never
     * reach this branch (fail-safe ambiguous below).
     *
     * @return void
     */
    public function testDocumentedFinalFailureRecordsVerifiedFailure(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 1001, 'amount' => 150000]);
        $fresh = $this->attempt('failed');
        $this->lifecycle->expects($this->once())->method('recordVerifiedFailure')
            ->with(
                'MOMOREF',
                'Recovery v2/query resultCode 1001.',
                'failed'
            )->willReturn($fresh);
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['failed']);
    }

    /**
     * AC3: `1000` (initiated, waiting for user confirmation — Final Status
     * = No) is non-final: no mutation, no false failure.
     *
     * @return void
     */
    public function testInitiated1000IsPendingNoMutation(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 1000, 'amount' => 150000]);
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['pending']);
    }

    /**
     * `9000` (authorized; Final Status = No but documented "mark this
     * transaction as success" for the module's 1-step captureWallet /
     * default autoCapture contract) finalizes through the SAME paid path —
     * still guarded by amount + positive transId.
     *
     * @return void
     */
    public function testAuthorized9000FinalizesThroughPaidPath(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 9000, 'amount' => 150000, 'transId' => '987654321']);
        $fresh = $this->attempt('paid', ['provider_transaction_id' => '987654321']);
        $this->lifecycle->expects($this->once())->method('recordVerifiedPaid')
            ->with('MOMOREF', '987654321')->willReturn($fresh);
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover')
            ->with($fresh, '987654321');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['claimed']);
        $this->assertSame(1, $summary['finalized']);
        $this->assertSame(0, $summary['mismatch']);
    }

    /**
     * AC3: a request/system-level non-final code (`10` — maintenance,
     * Final Status = No) is NOT a transaction outcome: ambiguous, no
     * mutation, no false failure.
     *
     * @return void
     */
    public function testRequestLevelCode10IsAmbiguousNoMutation(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 10, 'amount' => 150000]);
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['ambiguous']);
    }

    /**
     * AC3 fail-safe: an unmapped/undocumented resultCode is never a
     * failure proof — ambiguous, no mutation, no false failure.
     *
     * @return void
     */
    public function testUnknownResultCodeIsAmbiguousNoMutation(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 424242, 'amount' => 150000]);
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');
        $this->lifecycle->expects($this->never())->method('recordVerifiedFailure');
        $this->orderFinalizer->expects($this->never())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['ambiguous']);
    }

    /**
     * A finalizer contract refusal is terminal for recovery: counted as
     * mismatch (the lifecycle already persisted the evidence), no retry
     * storm.
     *
     * @return void
     */
    public function testContractMismatchRefusalCountsAsMismatch(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->claimSucceeds();
        $this->queryReturns(['resultCode' => 0, 'amount' => 150000, 'transId' => '987654321']);
        $fresh = $this->attempt('paid', ['provider_transaction_id' => '987654321']);
        $this->lifecycle->method('recordVerifiedPaid')->willReturn($fresh);
        $this->orderFinalizer->method('finalizeOrRecover')->willThrowException(
            new ContractMismatchException(new Phrase('quote contract changed'))
        );
        $this->orderFinalizer->expects($this->once())->method('finalizeOrRecover');

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['mismatch']);
    }

    /**
     * A lost claim race (another writer mutated the row first) skips the
     * attempt entirely — no HTTP, no lifecycle call.
     *
     * @return void
     */
    public function testLostClaimRaceSkipsWithoutHttp(): void
    {
        $this->candidates([$this->attempt('active')]);
        $this->connection->method('update')->willReturn(0);
        $this->commandPool->expects($this->never())->method('get');
        $this->lifecycle->expects($this->never())->method('recordVerifiedPaid');

        $summary = $this->recovery->execute();

        $this->assertSame(0, $summary['claimed']);
    }

    /**
     * The claim that consumes the last permitted query persists the
     * exhaustion marker and logs it critically — durable evidence.
     *
     * @return void
     */
    public function testBudgetExhaustionPersistsMarkerAndLogsCritical(): void
    {
        $this->candidates([$this->attempt('active')]);
        $capturedWhere = null;
        $this->connection->method('update')->willReturnCallback(
            function ($table, array $bind, $where) use (&$capturedWhere): int {
                $capturedWhere = (string)$where;

                return 1;
            }
        );
        $this->connection->method('fetchOne')->willReturn('5');
        $this->queryCommand->method('execute')->willReturn($this->arrayResult(['resultCode' => 7000]));
        $this->logger->expects($this->once())->method('critical')
            ->with(
                $this->stringContains('recovery_exhausted'),
                $this->anything()
            );

        $summary = $this->recovery->execute();

        $this->assertSame(1, $summary['claimed']);
        $this->assertNotNull($capturedWhere);
        $this->assertStringContainsString('recovery_attempts < 5', $capturedWhere);
    }

    /**
     * Selection is bounded and deterministic: only non-terminal (active/
     * paid), unbound, un-quarantined, un-exhausted, in-budget, past-window
     * rows, oldest first, page-limited (AC5).
     *
     * @return void
     */
    public function testSelectionFiltersAreBoundedAndDeterministic(): void
    {
        $filters = [];
        $this->collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$filters) {
                $filters[(string)$field] = $condition;

                return $this->collection;
            }
        );
        $order = null;
        $this->collection->method('setOrder')->willReturnCallback(
            function ($field, $direction) use (&$order) {
                $order = [(string)$field, (string)$direction];

                return $this->collection;
            }
        );
        $pageSize = null;
        $this->collection->method('setPageSize')->willReturnCallback(
            function ($size) use (&$pageSize) {
                $pageSize = (int)$size;

                return $this->collection;
            }
        );
        $this->collection->method('getItems')->willReturn([]);

        $this->recovery->execute();

        $this->assertSame(
            ['in' => [
                PaymentAttemptInterface::STATUS_ACTIVE,
                PaymentAttemptInterface::STATUS_PAID,
            ]],
            $filters[PaymentAttemptInterface::PAYMENT_STATUS]
        );
        $this->assertSame(['null' => true], $filters[PaymentAttemptInterface::ORDER_ID]);
        $this->assertSame(['neq' => 1], $filters[PaymentAttemptInterface::REQ_RECONCILIATION]);
        $this->assertSame(['lt' => 5], $filters[PaymentAttemptInterface::RECOVERY_ATTEMPTS]);
        $this->assertSame(['neq' => 1], $filters[PaymentAttemptInterface::RECOVERY_EXHAUSTED]);
        $this->assertArrayHasKey('lteq', $filters[PaymentAttemptInterface::CREATED_AT]);
        $this->assertSame([PaymentAttemptInterface::ENTITY_ID, 'ASC'], $order);
        $this->assertSame(25, $pageSize);
    }

    /**
     * Real attempt model (mocked persistence context only), matching the
     * PaymentAttemptLifecycleTest fixture style.
     *
     * @param string $status
     * @param array $extra
     * @return PaymentAttempt
     */
    private function attempt(string $status, array $extra = []): PaymentAttempt
    {
        $attempt = new PaymentAttempt(
            $this->createMock(Context::class),
            $this->createMock(Registry::class)
        );
        $attempt->setEntityId(7);
        $attempt->setQuoteId(42);
        $attempt->setOrderRef('MOMOREF');
        $attempt->setAmount(150000);
        $attempt->setPaymentStatus($status);
        foreach ($extra as $field => $value) {
            $attempt->setData($field, $value);
        }

        return $attempt;
    }

    /**
     * Wire the candidate page the selection returns.
     *
     * @param array $items
     * @return void
     */
    private function candidates(array $items): void
    {
        $this->collection->method('getItems')->willReturn($items);
    }

    /**
     * The atomic claim wins (1 affected row) below the exhaustion cap.
     *
     * @return void
     */
    private function claimSucceeds(): void
    {
        $this->connection->method('update')->willReturn(1);
        $this->connection->method('fetchOne')->willReturn('1');
    }

    /**
     * The v2/query command resolves with the given response array.
     *
     * @param array $response
     * @return void
     */
    private function queryReturns(array $response): void
    {
        $this->queryCommand->method('execute')->willReturn($this->arrayResult($response));
    }

    /**
     * @param array $data
     * @return ArrayResult&MockObject
     */
    private function arrayResult(array $data): ArrayResult&MockObject
    {
        $result = $this->createMock(ArrayResult::class);
        $result->method('get')->willReturn($data);

        return $result;
    }
}
