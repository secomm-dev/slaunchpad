<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Creditmemo\RefundOperation;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;
use Secomm\ZaloPay\Model\ResourceModel\RefundResource;
use Secomm\ZaloPay\Service\PendingRefundManager;
use Secomm\ZaloPay\Service\RefundOutcomeMarker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TASK-CG6BM7 BLOCKER 1 (round 7 F29/F32): async refund lifecycle unit
 * matrix.
 *
 * Pins the PendingRefundManager contract the lifecycle correctness rests
 * on: every post-claim transition is an ATOMIC conditional UPDATE
 * (compare-and-set) with the exact expected-state + active-claim guards,
 * a stale cron snapshot can NEVER overwrite a newer owner transition
 * (lost CAS: reload + critical log + customer-safe throw for blocking
 * forward transitions, swallow for terminal ones), budget consumption is
 * a server-side increment that NEVER mutates refund_state (F32), and the
 * finalize runs the NATIVE core accounting exactly once under the row
 * lock through the credit-memo-scoped outcome marker.
 *
 * Complements RefundCronjobTest (retry/reconciliation matrix) and
 * CreditmemoRefundPluginTest (admin entry-point guards).
 */
class PendingRefundManagerTest extends TestCase
{
    private PendingRefundManager $manager;

    private RefundResource|MockObject $refundResource;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface|MockObject
     */
    private $connection;

    private Select|MockObject $select;

    private RefundCollectionFactory|MockObject $refundCollectionFactory;

    /**
     * @var \Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection|MockObject
     */
    private $collection;

    /**
     * @var \Magento\Sales\Api\CreditmemoRepositoryInterface|MockObject
     */
    private $creditmemoRepository;

    /**
     * @var \Magento\Sales\Api\InvoiceRepositoryInterface|MockObject
     */
    private $invoiceRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    private RefundOperation|MockObject $refundOperation;

    private Logger|MockObject $logger;

    /**
     * Test double of the (round 7) outcome marker: records authorize/clear
     * around the native accounting. Extends the real class so the manager
     * typehint holds and the one-shot contract stays exercised.
     */
    private RefundOutcomeMarker $outcomeMarker;

    private Creditmemo|MockObject $creditmemo;

    private Order|MockObject $order;

    private Invoice|MockObject $invoice;

    /**
     * Mutable test state: the creditmemo state (PHPUnit stubs on the same
     * method do not override each other, so tests flip this instead).
     */
    private int $cmState = 1;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->refundResource = $this->getMockBuilder(RefundResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $this->refundResource->method('getMainTable')->willReturn('zalo_pay_refund');

        $this->connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $this->refundResource->method('getConnection')->willReturn($this->connection);

        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('forUpdate')->willReturnSelf();
        $this->connection->method('select')->willReturn($this->select);

        $this->collection = $this->createMock(\Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection::class);
        $this->collection->method('addFieldToFilter')->willReturnSelf();
        $this->refundCollectionFactory = $this->createMock(RefundCollectionFactory::class);
        $this->refundCollectionFactory->method('create')->willReturn($this->collection);

        $this->creditmemoRepository = $this->createMock(\Magento\Sales\Api\CreditmemoRepositoryInterface::class);
        $this->invoiceRepository = $this->createMock(\Magento\Sales\Api\InvoiceRepositoryInterface::class);
        $this->orderRepository = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $this->refundOperation = $this->createMock(RefundOperation::class);
        $this->logger = $this->createMock(Logger::class);

        $this->outcomeMarker = new class extends RefundOutcomeMarker {
            /**
             * @var array<int>
             */
            public array $doubleAuthorized = [];

            /**
             * @var array<int>
             */
            public array $doubleCleared = [];

            /**
             * One-shot consume contract (round 7 marker API).
             *
             * @param int $creditMemoId
             * @return bool
             */
            public function consume(int $creditMemoId): bool
            {
                return true;
            }

            /**
             * @param int $creditMemoId
             * @return void
             */
            public function authorize(int $creditMemoId): void
            {
                $this->doubleAuthorized[] = $creditMemoId;
            }

            /**
             * @param int $creditMemoId
             * @return void
             */
            public function clear(int $creditMemoId): void
            {
                $this->doubleCleared[] = $creditMemoId;
            }
        };

        $this->creditmemo = $this->createMock(Creditmemo::class);
        $this->cmState = 1;
        $this->creditmemo->method('getState')->willReturnCallback(fn (): int => $this->cmState);
        $this->creditmemo->method('getOrderId')->willReturn(77);
        $this->creditmemo->method('getEntityId')->willReturn(33);
        $this->creditmemo->method('getOrder')->willReturn($this->order = $this->createMock(Order::class));
        $this->order->method('getId')->willReturn(77);
        $this->order->method('getIncrementId')->willReturn('000000123');
        $this->creditmemo->method('getInvoiceId')->willReturn(12);
        $this->creditmemo->method('getBaseGrandTotal')->willReturn(25.0);

        $this->invoice = $this->createMock(Invoice::class);
        $this->invoice->method('getBaseTotalRefunded')->willReturn(100.0);

        $this->manager = new PendingRefundManager(
            $this->refundResource,
            $this->refundCollectionFactory,
            $this->creditmemoRepository,
            $this->invoiceRepository,
            $this->orderRepository,
            $this->refundOperation,
            $this->outcomeMarker,
            $this->logger
        );
    }

    /**
     * A data-backed refund row mock (setData/getData/getId mocked against
     * a mutable backing array). NO save() is stubbed on purpose: since
     * round 7 no lifecycle transition may blind-save the model - only
     * acquireClaim (the INSERT) persists through the model.
     */
    private function makeRefund(int $id, array $data): RefundModel|MockObject
    {
        $refund = $this->createMock(RefundModel::class);
        $holder = ['data' => $data];
        $refund->method('getId')->willReturn($id);
        $refund->method('getData')->willReturnCallback(
            function (string $key) use (&$holder) {
                return $holder['data'][$key] ?? null;
            }
        );
        $refund->method('setData')->willReturnCallback(
            function (...$args) use ($refund, &$holder) {
                $first = $args[0] ?? null;
                if (is_array($first)) {
                    $holder['data'] = array_merge($holder['data'], $first);
                } elseif (is_string($first)) {
                    $holder['data'][$first] = $args[1] ?? null;
                }

                return $refund;
            }
        );

        return $refund;
    }

    /**
     * CAS-failure harness: the conditional UPDATE affects 0 rows (another
     * owner won) and the fresh reload returns $persistedRow.
     *
     * @param array $persistedRow What the DB now holds (the winner's state).
     * @param array $updates Captured update calls.
     * @return void
     */
    private function stubLostTransition(array $persistedRow, array &$updates): void
    {
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$updates): int {
                $updates[] = [$table, $bind, $where];

                return 0;
            });
        $this->connection->method('fetchRow')->willReturn($persistedRow);
        $this->logger->expects($this->once())->method('critical')
            ->with($this->stringContains('was not applied - another owner already moved the row'));
    }

    /**
     * Round 3 F14 (semantic blocking + historical safety): in-flight = an
     * UNPROCESSED row (is_processed = 0 - HISTORICAL RESOLVED rows can
     * never block) in a blocking state: initiating /
     * provider_request_started / processing / unknown /
     * provider_success_local_pending.
     * CONFIRMED_FAIL / CONFIRMED_SUCCESS rows fall OUTSIDE this filter
     * set: a provider-confirmed FAIL releases the block (money provably
     * not refunded), a confirmed SUCCESS hands control to the normal
     * refundable-balance checks.
     */
    public function testHasInFlightBlocksProcessingAndUnknownStatesOnly(): void
    {
        $filters = [];
        $this->collection->method('addFieldToFilter')->willReturnCallback(
            function (string $field, $cond) use (&$filters) {
                $filters[] = [$field, $cond];

                return $this->collection;
            }
        );
        $this->collection->method('getSize')->willReturn(1);

        $this->assertTrue($this->manager->hasInFlight(77));
        $this->assertSame(
            [
                [RefundInterface::ORDER_ID, ['eq' => 77]],
                [RefundInterface::IS_PROCESSED, ['eq' => RefundInterface::NOT_PROCESSED]],
                [
                    RefundInterface::REFUND_STATE,
                    ['in' => [
                        RefundInterface::REFUND_STATE_INITIATING,
                        RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                        RefundInterface::REFUND_STATE_PROCESSING,
                        RefundInterface::REFUND_STATE_UNKNOWN,
                        RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
                    ]],
                ],
            ],
            $filters
        );
    }

    /**
     * GATE 1 (round 2): no row in a blocking SEMANTIC state -> not in flight.
     */
    public function testHasInFlightFalseWhenNoUnprocessedRow(): void
    {
        $this->collection->method('getSize')->willReturn(0);

        $this->assertFalse($this->manager->hasInFlight(77));
    }

    /**
     * Round 3 F12: acquireClaim persists THE atomic durable claim in ONE
     * save (autocommit - no explicit transaction, the row commits BEFORE
     * any provider I/O) with refund_state = initiating, active_claim = 1
     * and the stable m_refund_id. The credit memo is NEVER mutated here
     * (no creditmemo parking - F34: it stays OPEN until the finalize).
     */
    public function testAcquireClaimPersistsInitiatingClaim(): void
    {
        $refund = $this->createMock(RefundModel::class);
        $captured = [];
        $this->collection->method('getNewEmptyItem')->willReturn($refund);
        $refund->expects($this->once())->method('setData')
            ->willReturnCallback(function (array $data) use (&$captured, $refund) {
                $captured = $data;

                return $refund;
            });
        $refund->expects($this->once())->method('save');
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->connection->expects($this->never())->method('commit');
        $this->creditmemo->expects($this->never())->method('setState');

        $tracking = new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_777_c1', 50000, '{"app_id":1}');
        $result = $this->manager->acquireClaim($this->creditmemo, $tracking);

        $this->assertSame($refund, $result);
        $this->assertSame(
            [
                RefundInterface::ORDER_ID => 77,
                RefundInterface::CREDIT_MEMO_ID => null,
                RefundInterface::INCREMENT_ID => '000000123',
                RefundInterface::M_REFUND_ID => '260916_1000_777_c1',
                RefundInterface::ADDITIONAL_INFORMATION => '{"app_id":1}',
                RefundInterface::AMOUNT => 50000.0,
                RefundInterface::IS_PROCESSED => RefundInterface::NOT_PROCESSED,
                RefundInterface::QUERY_ATTEMPTS => 0,
                RefundInterface::LAST_ERROR => null,
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::ACTIVE_CLAIM => 1,
            ],
            $captured
        );
    }

    /**
     * Round 3 F12: a concurrent/duplicate ACTIVE claim (unique index
     * violation: SQLSTATE 23000 / driver 1062) surfaces the customer-safe
     * in-flight exception - the provider is NEVER asked for the loser of
     * the INSERT race.
     */
    public function testAcquireClaimDuplicateKeyThrowsInFlightException(): void
    {
        $refund = $this->createMock(RefundModel::class);
        $this->collection->method('getNewEmptyItem')->willReturn($refund);
        $refund->method('setData')->willReturnSelf();
        $refund->expects($this->once())->method('save')
            ->willThrowException(new \RuntimeException(
                'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'77-1\' for key \'ZALO_PAY_REFUND_ORDER_ACTIVE\''
            ));

        $tracking = new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_777_c2', 50000, null);
        try {
            $this->manager->acquireClaim($this->creditmemo, $tracking);
            self::fail('duplicate active claim must be refused');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('active or awaiting reconciliation', $exception->getMessage());
        }
        $this->logger->expects($this->never())->method('error');
    }

    /**
     * Round 3 F12: a NON-duplicate persistence failure aborts SAFE (the
     * provider was NOT asked - the claim commits before I/O) with an error
     * log + retryable customer message.
     */
    public function testAcquireClaimOtherFailureAbortsSafe(): void
    {
        $refund = $this->createMock(RefundModel::class);
        $this->collection->method('getNewEmptyItem')->willReturn($refund);
        $refund->method('setData')->willReturnSelf();
        $refund->expects($this->once())->method('save')
            ->willThrowException(new \RuntimeException('db connection lost'));

        $tracking = new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_777_c3', 50000, null);
        $this->logger->expects($this->once())->method('error');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('could not be recorded locally');
        $this->manager->acquireClaim($this->creditmemo, $tracking);
    }

    /**
     * Round 4 F22: a FOREIGN-KEY violation (SQLSTATE 23000 / driver 1452)
     * must NEVER surface as the misleading "another refund is active"
     * claim conflict - it is a local persistence failure with the safe
     * abort path (and the honest error log).
     */
    public function testAcquireClaimForeignKeyViolationIsNotClaimConflict(): void
    {
        $refund = $this->createMock(RefundModel::class);
        $this->collection->method('getNewEmptyItem')->willReturn($refund);
        $refund->method('setData')->willReturnSelf();
        $fk = new \PDOException(
            'SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row:'
            . ' a foreign key constraint fails (`m2`.`zalo_pay_refund`, CONSTRAINT `ZALO_PAY_REFUND_CREDIT_MEMO_ID`)'
        );
        $fk->errorInfo = ['23000', 1452, 'Cannot add or update a child row: a foreign key constraint fails'];
        $refund->expects($this->once())->method('save')->willThrowException($fk);
        $this->logger->expects($this->once())->method('error');

        $tracking = new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_777_fk', 50000, null);
        try {
            $this->manager->acquireClaim($this->creditmemo, $tracking);
            self::fail('FK violation must not pass as a duplicate-key claim conflict');
        } catch (LocalizedException $exception) {
            $this->assertStringNotContainsString('active or awaiting reconciliation', $exception->getMessage());
            $this->assertStringContainsString('could not be recorded locally', $exception->getMessage());
        }
    }

    /**
     * Round 4 F17: bindCreditMemo writes the REAL credit memo entity_id on
     * the row THAT STILL OWNS THE CLAIM (conditional UPDATE on
     * entity_id + active_claim = 1) - the provider gate last step.
     */
    public function testBindCreditMemoGuardedByActiveClaim(): void
    {
        $refund = $this->makeRefund(9, [RefundInterface::ACTIVE_CLAIM => 1]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->with(
                'zalo_pay_refund',
                [RefundInterface::CREDIT_MEMO_ID => 9012],
                $this->callback(function (array $where) use (&$captured): bool {
                    $captured = $where;

                    return true;
                })
            )
            ->willReturn(1);
        $this->manager->bindCreditMemo($refund, 9012);
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 9,
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ],
            $captured
        );
        $this->assertSame(9012, $refund->getData(RefundInterface::CREDIT_MEMO_ID));
    }

    /**
     * Round 4 F17: when the claim slot was lost between acquire and bind
     * (concurrent stale-claim release / terminal transition), the bind
     * must fail (0 affected rows) - the plugin then NEVER calls the
     * provider.
     */
    public function testBindCreditMemoReturnsFalseWhenClaimReleased(): void
    {
        $refund = $this->makeRefund(9, [RefundInterface::ACTIVE_CLAIM => 1]);
        $this->connection->expects($this->once())->method('update')->willReturn(0);
        $this->assertFalse($this->manager->bindCreditMemo($refund, 9012));
        $this->assertNull($refund->getData(RefundInterface::CREDIT_MEMO_ID));
    }

    /**
     * Round 5 F23 / round 7 F29: markProviderRequestStarted is the explicit
     * LOCAL_READY -> PROVIDER_REQUEST_STARTED boundary: ONE conditional
     * UPDATE (entity + refund_state = initiating + active_claim = 1) that
     * pins the durable state AND the UTC start timestamp. It must NOT be
     * writable on a released row or a row already moved past initiating.
     */
    public function testMarkProviderRequestStartedPinsStateAndTimestamp(): void
    {
        $refund = $this->makeRefund(9, [RefundInterface::ACTIVE_CLAIM => 1]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->with(
                'zalo_pay_refund',
                $this->callback(function (array $data) use (&$captured): bool {
                    $captured = $data;

                    return isset($data[RefundInterface::REFUND_STATE])
                        && $data[RefundInterface::REFUND_STATE]
                            === RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED
                        && is_string($data[RefundInterface::PROVIDER_REQUEST_STARTED_AT]);
                }),
                $this->callback(function (array $where) use (&$captured): bool {
                    $captured['WHERE'] = $where;

                    return $where[RefundInterface::REFUND_STATE . ' = ?']
                        === RefundInterface::REFUND_STATE_INITIATING;
                })
            )
            ->willReturn(1);
        $this->assertTrue($this->manager->markProviderRequestStarted($refund));
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 9,
                RefundInterface::REFUND_STATE . ' = ?' => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ],
            $captured['WHERE']
        );
        $this->assertSame(
            RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertNotEmpty($refund->getData(RefundInterface::PROVIDER_REQUEST_STARTED_AT));
    }

    /**
     * A released/lost claim (0 affected rows) can NEVER cross the
     * provider-start boundary - the plugin then terminates before any
     * provider I/O (F23 construction invariant).
     */
    public function testMarkProviderRequestStartedFailsWhenClaimReleased(): void
    {
        $refund = $this->makeRefund(9, [RefundInterface::ACTIVE_CLAIM => 1]);
        $this->connection->expects($this->once())->method('update')->willReturn(0);
        $this->assertFalse($this->manager->markProviderRequestStarted($refund));
        $this->assertNull($refund->getData(RefundInterface::REFUND_STATE));
        $this->assertNull($refund->getData(RefundInterface::PROVIDER_REQUEST_STARTED_AT));
    }

    /**
     * Round 7 F29: markProcessing is an ATOMIC CAS transition - the
     * conditional UPDATE requires refund_state IN
     * (provider_request_started, processing) AND active_claim = 1, sets
     * refund_state = processing and CLEARS last_error; the in-memory
     * model mirrors the persisted values only on success. NO creditmemo
     * argument, NO parking (F34).
     */
    public function testMarkProcessingCasTransitionSucceeds(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
            RefundInterface::LAST_ERROR => 'stale evidence',
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$captured): int {
                $captured = [$table, $bind, $where];

                return 1;
            });
        $this->creditmemo->expects($this->never())->method('setState');
        $this->creditmemoRepository->expects($this->never())->method('save');

        $this->manager->markProcessing($refund);

        list(, $bind, $where) = $captured;
        $this->assertSame(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
                RefundInterface::LAST_ERROR => null,
            ],
            $bind
        );
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 5,
                RefundInterface::REFUND_STATE . ' IN (?)' => [
                    RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                    RefundInterface::REFUND_STATE_PROCESSING,
                ],
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ],
            $where
        );
        $this->assertSame(RefundInterface::REFUND_STATE_PROCESSING, $refund->getData(RefundInterface::REFUND_STATE));
        $this->assertNull($refund->getData(RefundInterface::LAST_ERROR));
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM), 'claim stays owned');
    }

    /**
     * Round 7 F29 (blocking forward transition, lost CAS): another owner
     * moved the row first (affected = 0). The manager reloads fresh,
     * critical-logs, and throws the customer-safe LocalizedException -
     * WITHOUT overwriting, releasing the claim or downgrading the state.
     */
    public function testMarkProcessingLostCasThrowsCustomerSafeWithoutOverwrite(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $updates = [];
        $this->stubLostTransition(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                RefundInterface::IS_PROCESSED => 1,
                RefundInterface::ACTIVE_CLAIM => null,
            ],
            $updates
        );

        try {
            $this->manager->markProcessing($refund);
            self::fail('lost CAS on a blocking forward transition must throw');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString(
                'The refund is tracked and will be reconciled automatically',
                $exception->getMessage()
            );
        }
        $this->assertCount(1, $updates, 'no second (overwrite) UPDATE may run');
        $this->assertSame(
            RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
            $refund->getData(RefundInterface::REFUND_STATE),
            'stale snapshot must NOT be overwritten in memory either'
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM), 'claim NOT released by the loser');
    }

    /**
     * Round 7 F29: markUnknown is a CAS transition guarded by
     * provider_request_started / processing / unknown AND the live claim.
     */
    public function testMarkUnknownCasTransitionSucceeds(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$captured): int {
                $captured = [$bind, $where];

                return 1;
            });

        $this->manager->markUnknown($refund, 'transport_error: cURL timeout 28');

        list($bind, $where) = $captured;
        $this->assertSame(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN,
                RefundInterface::LAST_ERROR => 'transport_error: cURL timeout 28',
            ],
            $bind
        );
        $this->assertSame(
            RefundInterface::REFUND_STATE_UNKNOWN,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertSame(1, $where[RefundInterface::ACTIVE_CLAIM . ' = ?'], 'claim stays owned');
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * Round 7 F29: a LOST markUnknown CAS throws the customer-safe
     * exception; the winner's state is never overwritten.
     */
    public function testMarkUnknownLostCasThrowsWithoutOverwrite(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $updates = [];
        $this->stubLostTransition(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING],
            $updates
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('will be reconciled automatically');
        $this->manager->markUnknown($refund, 'transport_error: boom');
    }

    /**
     * Round 7 F29: markConfirmedFail CAS-guards the terminal refusal on
     * the four pre-outcome states AND the live claim; the bind releases
     * the claim slot.
     */
    public function testMarkConfirmedFailCasTransitionReleasesClaim(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$captured): int {
                $captured = [$bind, $where];

                return 1;
            });

        $this->manager->markConfirmedFail($refund, 'refund_failed: Refund time has expired.');

        list($bind, $where) = $captured;
        $this->assertSame(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_FAIL,
                RefundInterface::LAST_ERROR => 'refund_failed: Refund time has expired.',
                RefundInterface::ACTIVE_CLAIM => null,
            ],
            $bind
        );
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 5,
                RefundInterface::REFUND_STATE . ' IN (?)' => [
                    RefundInterface::REFUND_STATE_INITIATING,
                    RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                    RefundInterface::REFUND_STATE_PROCESSING,
                    RefundInterface::REFUND_STATE_UNKNOWN,
                ],
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ],
            $where
        );
        $this->assertSame(
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertNull($refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * Round 7 F29: a LOST markConfirmedFail CAS is SWALLOWED (the winner
     * owns the row) - logged, no throw, no overwrite, no release.
     */
    public function testMarkConfirmedFailLostCasIsSwallowed(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $updates = [];
        $this->stubLostTransition(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING],
            $updates
        );

        $this->manager->markConfirmedFail($refund, 'refund_failed: late refusal');

        $this->assertCount(1, $updates);
        $this->assertNotSame(
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL,
            $refund->getData(RefundInterface::REFUND_STATE),
            'loser must not mirror its state over the winner'
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM), 'claim NOT released by the loser');
    }

    /**
     * Round 7 F29: markProviderSuccessLocalPending CAS-guards the PSLP
     * state on the open-outcome states AND the live claim.
     */
    public function testMarkProviderSuccessLocalPendingCasTransitionSucceeds(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$captured): int {
                $captured = [$bind, $where];

                return 1;
            });

        $this->manager->markProviderSuccessLocalPending(
            $refund,
            'reconcile_error: core finalize failed: accounting boom'
        );

        list($bind, $where) = $captured;
        $this->assertSame(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
                RefundInterface::LAST_ERROR => 'reconcile_error: core finalize failed: accounting boom',
            ],
            $bind
        );
        $this->assertSame(
            RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertSame(1, $where[RefundInterface::ACTIVE_CLAIM . ' = ?'], 'claim stays owned until finalize');
    }

    /**
     * Round 7 F29 scenario B (stale cron snapshot, late query result):
     * the cron snapshot says processing, but the owner already moved the
     * row to confirmed_success (is_processed = 1, claim released). The
     * late PSLP transition affects 0 rows - it throws the customer-safe
     * exception WITHOUT overwriting, releasing a claim or downgrading the
     * terminal state: the persisted row stays confirmed_success.
     */
    public function testStaleLateTransitionCannotOverwriteConfirmedSuccess(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $updates = [];
        $this->stubLostTransition(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                RefundInterface::IS_PROCESSED => 1,
                RefundInterface::ACTIVE_CLAIM => null,
            ],
            $updates
        );

        try {
            $this->manager->markProviderSuccessLocalPending($refund, 'reconcile_error: late local failure');
            self::fail('a lost late transition must not silently pass');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('reconciled automatically', $exception->getMessage());
        }
        $this->assertCount(1, $updates);
        $this->assertArrayNotHasKey(
            RefundInterface::ACTIVE_CLAIM,
            $updates[0][1],
            'the late PSLP bind must not touch claim ownership (the winner already released it)'
        );
        $this->assertNotSame(
            RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            $refund->getData(RefundInterface::REFUND_STATE),
            'the stale in-memory snapshot is not overwritten with the losing transition'
        );
    }

    /**
     * Round 7 F29: markConfirmedSuccess CAS-guards the terminal success on
     * is_processed = 0 (exactly one terminal writer).
     */
    public function testMarkConfirmedSuccessCasTransitionResolvesRow(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::ACTIVE_CLAIM => 1,
            RefundInterface::LAST_ERROR => 'stale evidence',
        ]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$captured): int {
                $captured = [$bind, $where];

                return 1;
            });

        $this->manager->markConfirmedSuccess($refund);

        list($bind, $where) = $captured;
        $this->assertSame(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                RefundInterface::IS_PROCESSED => 1,
                RefundInterface::LAST_ERROR => null,
                RefundInterface::ACTIVE_CLAIM => null,
            ],
            $bind
        );
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 5,
                RefundInterface::IS_PROCESSED . ' = ?' => 0,
            ],
            $where
        );
        $this->assertSame(
            RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertSame(RefundInterface::PROCESSED, $refund->getData(RefundInterface::IS_PROCESSED));
        $this->assertNull($refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * Round 7 F29: a LOST markConfirmedSuccess CAS (another run already
     * finalized: is_processed = 1) is SWALLOWED - a completed refund is
     * never failed on bookkeeping.
     */
    public function testMarkConfirmedSuccessLostCasIsSwallowed(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::ACTIVE_CLAIM => 1,
            RefundInterface::IS_PROCESSED => RefundInterface::NOT_PROCESSED,
        ]);
        $updates = [];
        $this->stubLostTransition(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS],
            $updates
        );

        $this->manager->markConfirmedSuccess($refund);

        $this->assertCount(1, $updates);
        $this->assertSame(
            RefundInterface::NOT_PROCESSED,
            $refund->getData(RefundInterface::IS_PROCESSED),
            'the loser must not mirror is_processed = 1 over the winner'
        );
    }

    /**
     * Round 7 F29/F32: consumeQueryBudget is an ATOMIC server-side
     * increment (query_attempts = query_attempts + 1) guarded by
     * is_processed = 0, then reads the new value back - NO blind model
     * save, NO refund_state mutation.
     */
    public function testConsumeQueryBudgetAtomicallyIncrementsAndReadsBack(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::QUERY_ATTEMPTS => 3,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$captured): int {
                $captured = [$bind, $where];

                return 1;
            });
        $this->connection->expects($this->once())->method('fetchOne')->willReturn('4');

        $attempts = $this->manager->consumeQueryBudget($refund, 'transport_error: timeout');

        $this->assertSame(4, $attempts);
        list($bind, $where) = $captured;
        $this->assertSame('transport_error: timeout', $bind[RefundInterface::LAST_ERROR]);
        $this->assertInstanceOf(\Magento\Framework\DB\Sql\Expression::class, $bind[RefundInterface::QUERY_ATTEMPTS]);
        $this->assertSame(
            RefundInterface::QUERY_ATTEMPTS . ' + 1',
            (string)$bind[RefundInterface::QUERY_ATTEMPTS]
        );
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 5,
                RefundInterface::IS_PROCESSED . ' = ?' => 0,
            ],
            $where
        );
        $this->assertSame(4, $refund->getData(RefundInterface::QUERY_ATTEMPTS));
        $this->assertSame('transport_error: timeout', $refund->getData(RefundInterface::LAST_ERROR));
        $this->assertSame(
            RefundInterface::REFUND_STATE_PROCESSING,
            $refund->getData(RefundInterface::REFUND_STATE),
            'budget consumption must NEVER mutate refund_state (F32)'
        );
    }

    /**
     * Round 7 F32 (the core PSLP guarantee): a provider_success_local_
     * pending row at the LAST budget unit (95 -> 96) keeps its PSLP state
     * - budget exhaustion NEVER demotes PSLP to unknown (the provider
     * money is out; only the local finalize remains and it is uncapped).
     */
    public function testConsumeQueryBudgetNeverDemotesPslpAtCap(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::QUERY_ATTEMPTS => PendingRefundManager::MAX_QUERY_ATTEMPTS - 1,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $this->connection->expects($this->once())->method('update')->willReturn(1);
        $this->connection->expects($this->once())->method('fetchOne')
            ->willReturn((string)PendingRefundManager::MAX_QUERY_ATTEMPTS);

        $attempts = $this->manager->consumeQueryBudget(
            $refund,
            PendingRefundManager::EVIDENCE_RECONCILE . 'finalization failed locally: boom'
        );

        $this->assertSame(PendingRefundManager::MAX_QUERY_ATTEMPTS, $attempts);
        $this->assertSame(
            RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            $refund->getData(RefundInterface::REFUND_STATE),
            'PSLP must never become unknown at the budget cap (F32)'
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM));
        $this->assertSame(
            PendingRefundManager::MAX_QUERY_ATTEMPTS,
            $refund->getData(RefundInterface::QUERY_ATTEMPTS)
        );
    }

    /**
     * Round 7 F29: terminate CAS-guards on entity_id AND active_claim = 1
     * (one atomic UPDATE, no model save). Default UNKNOWN keeps the claim
     * slot (quarantine) - no active_claim in the bind.
     */
    public function testTerminateCasDefaultsToUnknownQuarantine(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::QUERY_ATTEMPTS => 4,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$captured): int {
                $captured = [$bind, $where];

                return 1;
            });

        $this->manager->terminate($refund, 'reconcile_error: malformed stored query payload');

        list($bind, $where) = $captured;
        $this->assertSame(
            [
                RefundInterface::QUERY_ATTEMPTS => PendingRefundManager::MAX_QUERY_ATTEMPTS,
                RefundInterface::LAST_ERROR => 'reconcile_error: malformed stored query payload',
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN,
            ],
            $bind
        );
        $this->assertArrayNotHasKey(RefundInterface::ACTIVE_CLAIM, $bind, 'UNKNOWN keeps the claim slot');
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 5,
                RefundInterface::REFUND_STATE . ' = ?' => RefundInterface::REFUND_STATE_PROCESSING,
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ],
            $where
        );
        $this->assertSame(PendingRefundManager::MAX_QUERY_ATTEMPTS, $refund->getData(RefundInterface::QUERY_ATTEMPTS));
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * Round 7 F29: terminate confirmed_fail releases the claim slot in the
     * SAME atomic UPDATE.
     */
    public function testTerminateCasConfirmedFailReleasesClaim(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::QUERY_ATTEMPTS => 4,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$captured): int {
                $captured = [$bind, $where];

                return 1;
            });

        $this->manager->terminate(
            $refund,
            PendingRefundManager::EVIDENCE_REFUND_FAILED . 'Refund time has expired.',
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL
        );

        list($bind, $where) = $captured;
        $this->assertSame(
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL,
            $bind[RefundInterface::REFUND_STATE]
        );
        $this->assertArrayHasKey(RefundInterface::ACTIVE_CLAIM, $bind);
        $this->assertNull($bind[RefundInterface::ACTIVE_CLAIM]);
        $this->assertSame(
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertNull($refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * Round 7 F29 SCENARIO A (stale cron snapshot, stale-claim terminate):
     * the cron snapshot holds initiating, but the owner already crossed
     * the provider-start boundary (persisted provider_request_started).
     * terminate() affects 0 rows - it is SWALLOWED (logged only), the
     * claim is NOT released, the state is NOT downgraded: the persisted
     * row stays provider_request_started and the owner continues.
     */
    public function testStaleTerminateCannotOverwriteProviderRequestStarted(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $updates = [];
        $this->stubLostTransition(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                RefundInterface::ACTIVE_CLAIM => 1,
            ],
            $updates
        );

        $this->manager->terminate(
            $refund,
            PendingRefundManager::EVIDENCE_ABANDONED . 'stale LOCAL_READY claim',
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL
        );

        $this->assertCount(1, $updates, 'exactly one CAS attempt - no overwrite, no claim-release UPDATE');
        $this->assertSame(
            RefundInterface::REFUND_STATE_INITIATING,
            $refund->getData(RefundInterface::REFUND_STATE),
            'the stale snapshot stays untouched (persisted row remains provider_request_started)'
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM), 'claim NOT released by the loser');
    }

    /**
     * Deadline micro-correction Test 1 (exact blocker): the cron snapshot
     * holds initiating + active_claim=1, but the owner has ALREADY crossed
     * the provider-start boundary (persisted provider_request_started,
     * active_claim STILL 1). The claim-only CAS would pass and flip the
     * money-out row to confirmed_fail behind the owner's back; the state
     * guard makes the CAS affect 0 rows.
     */
    public function testStaleInitiatingCannotKillProviderRequestStarted(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $updates = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$updates): int {
                $updates[] = [$table, $bind, $where];

                return 0; // DB holds provider_request_started - no row matches.
            });
        $this->logger->expects($this->once())->method('critical')
            ->with($this->stringContains('was not applied - another owner already moved the row'));

        $this->manager->terminate(
            $refund,
            PendingRefundManager::EVIDENCE_ABANDONED . 'stale LOCAL_READY claim',
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL
        );

        $this->assertCount(1, $updates, 'exactly ONE CAS attempt - no reload, no retry against the newer state');
        list(, , $where) = $updates[0];
        // Confirmed-fail termination STILL tries to release the claim in its
        // own bind - but the state guard makes it affect 0 rows, so nothing
        // is overwritten and no claim is released.
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 5,
                RefundInterface::REFUND_STATE . ' = ?' => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ],
            $where,
            'CAS WHERE must require the SNAPSHOT state (initiating), not just claim ownership'
        );
        $this->assertSame(
            RefundInterface::REFUND_STATE_INITIATING,
            $refund->getData(RefundInterface::REFUND_STATE),
            'no overwrite - the persisted row stays provider_request_started'
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM), 'claim NOT released by the loser');
    }

    /**
     * Deadline micro-correction Test 2: a stale snapshot in processing
     * must not overwrite a newer unknown state.
     */
    public function testStaleProcessingCannotOverwriteNewerUnknownState(): void
    {
        $refund = $this->makeRefund(7, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $updates = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$updates): int {
                $updates[] = [$table, $bind, $where];

                return 0; // DB moved on to unknown - no row matches.
            });
        $this->logger->expects($this->once())->method('critical')
            ->with($this->stringContains('was not applied - another owner already moved the row'));

        $this->manager->terminate($refund, PendingRefundManager::EVIDENCE_RECONCILE . 'credit memo missing');

        $this->assertCount(1, $updates, 'exactly ONE CAS attempt - the newer DB state wins');
        list(, , $where) = $updates[0];
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 7,
                RefundInterface::REFUND_STATE . ' = ?' => RefundInterface::REFUND_STATE_PROCESSING,
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ],
            $where
        );
        $this->assertSame(
            RefundInterface::REFUND_STATE_PROCESSING,
            $refund->getData(RefundInterface::REFUND_STATE),
            'no downgrade to unknown - the persisted row stays unknown'
        );
    }

    /**
     * Deadline micro-correction Test 3: when the snapshot state still
     * matches the DB state and the claim is held, the normal termination
     * persists (affected rows = 1).
     */
    public function testNormalTerminatePersistsWhenSnapshotMatchesDb(): void
    {
        $refund = $this->makeRefund(9, [
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $captured = [];
        $this->connection->expects($this->once())->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$captured): int {
                $captured = [$bind, $where];

                return 1;
            });

        $this->manager->terminate(
            $refund,
            PendingRefundManager::EVIDENCE_REFUND_FAILED . 'Refund time has expired.',
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL
        );

        list($bind, $where) = $captured;
        $this->assertSame(RefundInterface::REFUND_STATE_CONFIRMED_FAIL, $bind[RefundInterface::REFUND_STATE]);
        $this->assertNull($bind[RefundInterface::ACTIVE_CLAIM], 'confirmed_fail releases the claim');
        $this->assertSame(
            [
                RefundInterface::ENTITY_ID . ' = ?' => 9,
                RefundInterface::REFUND_STATE . ' = ?' => RefundInterface::REFUND_STATE_PROCESSING,
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ],
            $where
        );
        $this->assertSame(
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL,
            $refund->getData(RefundInterface::REFUND_STATE),
            'the intended terminal transition persists in memory too'
        );
        $this->assertNull($refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * BLOCKER 1 core (round 7 marker contract): provider-confirmed SUCCESS
     * finalizes through the NATIVE core accounting (invoice +
     * RefundOperation + saves) inside one locked transaction; the marker
     * is authorized credit-memo-scoped around RefundOperation and cleared
     * in a finally; both bookkeeping UPDATEs are CAS (AND is_processed = 0).
     */
    public function testFinalizeSuccessRunsNativeAccountingOnce(): void
    {
        $refund = $this->makeRefund(5, []);
        $this->connection->method('fetchRow')->willReturn(
            [
                RefundInterface::ENTITY_ID => 5,
                RefundInterface::IS_PROCESSED => 0,
                RefundInterface::CREDIT_MEMO_ID => 33,
            ]
        );
        $this->creditmemoRepository->method('get')->with(33)->willReturn($this->creditmemo);
        $this->invoiceRepository->method('get')->with(12)->willReturn($this->invoice);

        $this->invoice->expects($this->once())->method('setIsUsedForRefund')->with(true);
        $this->invoice->expects($this->once())->method('setBaseTotalRefunded')->with(125.0);
        $this->invoiceRepository->expects($this->once())->method('save')->with($this->invoice);
        $this->creditmemo->expects($this->once())->method('setState')->with(Creditmemo::STATE_REFUNDED);
        $this->refundOperation->expects($this->once())->method('execute')->with(
            $this->identicalTo($this->creditmemo),
            $this->identicalTo($this->order),
            true
        );
        $this->creditmemoRepository->expects($this->once())->method('save')->with($this->creditmemo);
        $this->orderRepository->expects($this->once())->method('save')->with($this->order);
        $this->connection->expects($this->once())->method('update')->with(
            'zalo_pay_refund',
            [
                RefundInterface::IS_PROCESSED => 1,
                RefundInterface::LAST_ERROR => null,
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                RefundInterface::ACTIVE_CLAIM => null,
            ],
            [
                RefundInterface::ENTITY_ID . ' = ?' => 5,
                RefundInterface::IS_PROCESSED . ' = ?' => 0,
            ]
        );
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->assertTrue($this->manager->finalizeSuccess($refund));
        $this->assertSame([33], $this->outcomeMarker->doubleAuthorized, 'marker authorized for the credit memo');
        $this->assertSame([33], $this->outcomeMarker->doubleCleared, 'marker cleared in the finally');
    }

    /**
     * Exact-once: a run that loses the row-lock race (row already
     * PROCESSED) re-checks under the lock and never re-runs accounting.
     */
    public function testFinalizeSuccessSkipsAccountingWhenRowAlreadyProcessed(): void
    {
        $refund = $this->makeRefund(5, []);
        $this->connection->method('fetchRow')->willReturn(
            [
                RefundInterface::ENTITY_ID => 5,
                RefundInterface::IS_PROCESSED => 1,
                RefundInterface::CREDIT_MEMO_ID => 33,
            ]
        );
        $this->connection->expects($this->once())->method('commit');
        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundOperation->expects($this->never())->method('execute');

        $this->assertFalse($this->manager->finalizeSuccess($refund));
        $this->assertSame([], $this->outcomeMarker->doubleAuthorized, 'no marker authorization without accounting');
    }

    /**
     * Recovery: the creditmemo is ALREADY REFUNDED (crash between the
     * creditmemo save and the row update) - complete only the CAS
     * bookkeeping (AND is_processed = 0), never re-run the accounting.
     */
    public function testFinalizeSuccessRecoversWhenCreditmemoAlreadyRefunded(): void
    {
        $refund = $this->makeRefund(5, []);
        $this->connection->method('fetchRow')->willReturn(
            [
                RefundInterface::ENTITY_ID => 5,
                RefundInterface::IS_PROCESSED => 0,
                RefundInterface::CREDIT_MEMO_ID => 33,
            ]
        );
        $this->creditmemoRepository->method('get')->with(33)->willReturn($this->creditmemo);
        $this->cmState = Creditmemo::STATE_REFUNDED;

        $this->refundOperation->expects($this->never())->method('execute');
        $this->invoiceRepository->expects($this->never())->method('get');
        $this->connection->expects($this->once())->method('update')->with(
            'zalo_pay_refund',
            [
                RefundInterface::IS_PROCESSED => 1,
                RefundInterface::LAST_ERROR => null,
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                RefundInterface::ACTIVE_CLAIM => null,
            ],
            [
                RefundInterface::ENTITY_ID . ' = ?' => 5,
                RefundInterface::IS_PROCESSED . ' = ?' => 0,
            ]
        );
        $this->connection->expects($this->once())->method('commit');

        $this->assertFalse($this->manager->finalizeSuccess($refund));
    }

    /**
     * Local accounting failure: rollback, remaining budget preserved, and a
     * customer-safe LocalizedException - the money is out at the provider,
     * local accounting is not; the marker is STILL cleared (finally) so no
     * stale skip-state survives the failed run.
     */
    public function testFinalizeSuccessRollsBackOnAccountingFailure(): void
    {
        $refund = $this->makeRefund(5, []);
        $this->connection->method('fetchRow')->willReturn(
            [
                RefundInterface::ENTITY_ID => 5,
                RefundInterface::IS_PROCESSED => 0,
                RefundInterface::CREDIT_MEMO_ID => 33,
            ]
        );
        $this->creditmemoRepository->method('get')->with(33)->willReturn($this->creditmemo);
        $this->invoiceRepository->method('get')->with(12)->willReturn($this->invoice);
        $this->refundOperation->expects($this->once())->method('execute')
            ->willReturnCallback(function () {
                throw new \RuntimeException('accounting boom');
            });

        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->connection->expects($this->never())->method('update');

        try {
            $this->manager->finalizeSuccess($refund);
            self::fail('accounting failure must surface as LocalizedException');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('refund finalization failed locally: accounting boom', $exception->getMessage());
        }
        $this->assertSame([33], $this->outcomeMarker->doubleAuthorized);
        $this->assertSame([33], $this->outcomeMarker->doubleCleared, 'finally must clear even on failure');
    }

    /**
     * Round 7 F29: the terminal bookkeeping lands CONFIRMED_SUCCESS in BOTH
     * CAS update binds (fresh finalize + recovery) - outside the blocking
     * filter set, control hands to the normal refundable-balance checks.
     */
    public function testFinalizeSuccessLandsConfirmedSuccessState(): void
    {
        $refund = $this->makeRefund(5, []);
        $this->connection->method('fetchRow')->willReturn(
            [
                RefundInterface::ENTITY_ID => 5,
                RefundInterface::IS_PROCESSED => 0,
                RefundInterface::CREDIT_MEMO_ID => 33,
            ]
        );
        $this->creditmemoRepository->method('get')->with(33)->willReturn($this->creditmemo);
        $this->invoiceRepository->method('get')->with(12)->willReturn($this->invoice);
        $this->connection->expects($this->once())->method('update')->with(
            'zalo_pay_refund',
            $this->callback(
                function (array $bind): bool {
                    return ($bind[RefundInterface::REFUND_STATE] ?? null)
                        === RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS
                        && array_key_exists(RefundInterface::ACTIVE_CLAIM, $bind)
                        && $bind[RefundInterface::ACTIVE_CLAIM] === null;
                }
            ),
            $this->callback(function (array $where): bool {
                return ($where[RefundInterface::IS_PROCESSED . ' = ?'] ?? null) === 0;
            })
        );

        $this->assertTrue($this->manager->finalizeSuccess($refund));
    }
}
