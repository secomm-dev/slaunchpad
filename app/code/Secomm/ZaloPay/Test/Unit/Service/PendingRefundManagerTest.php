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
use Secomm\ZaloPay\Plugin\Model\Order\CreditmemoPlugin;
use Secomm\ZaloPay\Service\PendingRefundManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TASK-CG6BM7 BLOCKER 1: async refund lifecycle unit matrix.
 *
 * Pins the PendingRefundManager contract the lifecycle correctness rests
 * on: the pending track persists WITHOUT mutating order refund totals, the
 * finalize runs the NATIVE core accounting exactly once under the row lock
 * (race + already-refunded recovery never re-run accounting), and local
 * failures roll back cleanly with a customer-safe LocalizedException.
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
     * Real marker: the finalize marks it, the test asserts the gateway skip
     * flag the no-second-provider-refund guarantee relies on.
     */
    private \Secomm\ZaloPay\Service\RefundOutcomeMarker $outcomeMarker;

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

        $this->outcomeMarker = new \Secomm\ZaloPay\Service\RefundOutcomeMarker();

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
     * A data-backed refund row mock (setData/getData/getId/save mocked
     * against a mutable backing array).
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
     * Round 3 F14 (semantic blocking + historical safety): in-flight = an
     * UNPROCESSED row (is_processed = 0 - HISTORICAL RESOLVED rows can
     * never block) in a blocking state: initiating / processing / unknown /
     * provider_success_local_pending. Budget saturation alone still NEVER
     * implies "safe to refund again": the exhausted UNKNOWN quarantine
     * keeps blocking until deliberately resolved.
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
     * (no creditmemo parking - the cron owns that).
     */
    public function testAcquireClaimPersistsInitiatingClaim(): void
    {
        $refund = $this->makeRefund(0, []);
        $this->collection->method('getNewEmptyItem')->willReturn($refund);

        $captured = [];
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
                RefundInterface::CREDIT_MEMO_ID => 33,
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
        $refund = $this->makeRefund(0, []);
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
        $refund = $this->makeRefund(0, []);
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
     * markProcessing: durable PROCESSING (blocking), claim slot intact.
     */
    public function testMarkProcessingPersistsBlockingState(): void
    {
        $refund = $this->makeRefund(5, [RefundInterface::ACTIVE_CLAIM => 1]);
        $refund->expects($this->once())->method('save');

        $this->manager->markProcessing($refund);

        $this->assertSame(
            RefundInterface::REFUND_STATE_PROCESSING,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * Round 3 F16: markUnknown lands the SEMANTIC UNKNOWN state -
     * explicitly NOT processing - with safe evidence; UNKNOWN keeps BOTH
     * the block and the atomic claim slot (quarantine).
     */
    public function testMarkUnknownLandsSemanticUnknown(): void
    {
        $refund = $this->makeRefund(5, [RefundInterface::ACTIVE_CLAIM => 1]);
        $refund->expects($this->once())->method('save');

        $this->manager->markUnknown($refund, 'transport_error: cURL timeout 28');

        $this->assertSame(
            RefundInterface::REFUND_STATE_UNKNOWN,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertSame('transport_error: cURL timeout 28', $refund->getData(RefundInterface::LAST_ERROR));
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * Provider-confirmed refusal: CONFIRMED_FAIL releases the atomic claim
     * slot (the money provably never left - a corrected retry may proceed).
     */
    public function testMarkConfirmedFailReleasesClaimSlot(): void
    {
        $refund = $this->makeRefund(5, [RefundInterface::ACTIVE_CLAIM => 1]);
        $refund->expects($this->once())->method('save');

        $this->manager->markConfirmedFail($refund, 'refund_failed: Refund time has expired.');

        $this->assertSame(
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertNull($refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * Round 3 F13: PSLP persists the durable CONFIRMED-PROVIDER-SUCCESS /
     * LOCAL-PENDING state (blocking; slot intact until finalize lands).
     */
    public function testMarkProviderSuccessLocalPendingPersistsBlockingState(): void
    {
        $refund = $this->makeRefund(5, [RefundInterface::ACTIVE_CLAIM => 1]);
        $refund->expects($this->once())->method('save');

        $this->manager->markProviderSuccessLocalPending(
            $refund,
            'reconcile_error: core finalize failed: accounting boom'
        );

        $this->assertSame(
            RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertStringStartsWith(
            'reconcile_error: core finalize failed:',
            (string)$refund->getData(RefundInterface::LAST_ERROR)
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * Fully finalized SUCCESS: CONFIRMED_SUCCESS + PROCESSED + claim slot
     * released (never blocks a future refund).
     */
    public function testMarkConfirmedSuccessResolvesRowReleasingClaim(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::ACTIVE_CLAIM => 1,
            RefundInterface::LAST_ERROR => 'stale evidence',
        ]);
        $refund->expects($this->once())->method('save');

        $this->manager->markConfirmedSuccess($refund);

        $this->assertSame(
            RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertSame(RefundInterface::PROCESSED, $refund->getData(RefundInterface::IS_PROCESSED));
        $this->assertNull($refund->getData(RefundInterface::LAST_ERROR));
        $this->assertNull($refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * A persistence failure on a BLOCKING transition is rethrown as a
     * customer-safe LocalizedException (the claim row keeps the order
     * blocked either way) with a critical log.
     */
    public function testBlockingTransitionPersistFailureRethrows(): void
    {
        $refund = $this->makeRefund(5, [RefundInterface::ACTIVE_CLAIM => 1]);
        $refund->expects($this->once())->method('save')
            ->willThrowException(new \RuntimeException('db down'));

        $this->logger->expects($this->once())->method('critical');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('could not be recorded locally. The refund is tracked');
        $this->manager->markProcessing($refund);
    }

    /**
     * A persistence failure on a TERMINAL transition is critical-logged and
     * swallowed: a completed refund is never failed on bookkeeping - the
     * blocking row self-heals via the cron (creditmemo REFUNDED -> resolve).
     */
    public function testTerminalTransitionPersistFailureSwallowed(): void
    {
        $refund = $this->makeRefund(5, [RefundInterface::ACTIVE_CLAIM => 1]);
        $refund->expects($this->once())->method('save')
            ->willThrowException(new \RuntimeException('db down'));

        $this->logger->expects($this->once())->method('critical');
        $this->manager->markConfirmedSuccess($refund);
    }

    /**
     * Round 3 F13: a PSLP-row budget consumption BELOW the cap keeps the
     * PSLP state (never demoted toward a fresh provider ask - the provider
     * money is out, only local finalize remains).
     */
    public function testConsumeQueryBudgetKeepsPslpStateBelowCap(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::QUERY_ATTEMPTS => 0,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $refund->expects($this->once())->method('save');

        $attempts = $this->manager->consumeQueryBudget(
            $refund,
            PendingRefundManager::EVIDENCE_RECONCILE . 'finalization failed locally: boom'
        );

        $this->assertSame(1, $attempts);
        $this->assertSame(
            RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM));
    }

    /**
     * BLOCKER 2: consuming the query budget is observable state progression.
     */
    public function testConsumeQueryBudgetIncrementsAndRecordsEvidence(): void
    {
        $refund = $this->makeRefund(5, [RefundInterface::QUERY_ATTEMPTS => 3]);
        $refund->expects($this->once())->method('save');

        $attempts = $this->manager->consumeQueryBudget($refund, 'transport_error: timeout');

        $this->assertSame(4, $attempts);
        $this->assertSame(4, $refund->getData(RefundInterface::QUERY_ATTEMPTS));
        $this->assertSame('transport_error: timeout', $refund->getData(RefundInterface::LAST_ERROR));
    }

    /**
     * BLOCKER 2: terminal outcomes saturate the budget (row drops out of
     * the cron selection) and keep the safe evidence.
     */
    public function testTerminateSaturatesBudgetAndKeepsEvidence(): void
    {
        $refund = $this->makeRefund(5, [RefundInterface::QUERY_ATTEMPTS => 10]);
        $refund->expects($this->once())->method('save');

        $this->manager->terminate($refund, 'refund_failed: Refund time has expired.');

        $this->assertSame(PendingRefundManager::MAX_QUERY_ATTEMPTS, $refund->getData(RefundInterface::QUERY_ATTEMPTS));
        $this->assertSame('refund_failed: Refund time has expired.', $refund->getData(RefundInterface::LAST_ERROR));
    }

    /**
     * BLOCKER 1 core: provider-confirmed SUCCESS finalizes through the
     * NATIVE core accounting (invoice + RefundOperation + saves) inside one
     * locked transaction, marks the outcome marker (gateway provider call
     * skipped), and flips the row to PROCESSED.
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
            [RefundInterface::ENTITY_ID . ' = ?' => 5]
        );
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->assertTrue($this->manager->finalizeSuccess($refund));
        $this->assertTrue($this->outcomeMarker->isProviderAlreadyAsked(77));
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
    }

    /**
     * Recovery: the creditmemo is ALREADY REFUNDED (crash between the
     * creditmemo save and the row update) - complete only the bookkeeping,
     * never re-run the accounting.
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
            [RefundInterface::ENTITY_ID . ' = ?' => 5]
        );
        $this->connection->expects($this->once())->method('commit');

        $this->assertFalse($this->manager->finalizeSuccess($refund));
    }

    /**
     * Local accounting failure: rollback, remaining budget preserved, and a
     * customer-safe LocalizedException - the money is out at the provider,
     * local accounting is not; evidence + budget survive for the next cron.
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
            ->willThrowException(new \RuntimeException('accounting boom'));

        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->connection->expects($this->never())->method('update');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('refund finalization failed locally: accounting boom');
        $this->manager->finalizeSuccess($refund);
    }

    /**
     * BLOCKER 2 round 2: budget exhaustion QUARANTINES the row as UNKNOWN -
     * it must KEEP blocking new refunds (exhaustion is never "safe to
     * refund"). Transport-exhausted case shown; the quarantine is
     * evidence-agnostic (protocol anomaly / finalize-failure exhaustion
     * land UNKNOWN identically - same code path, attempts >= MAX).
     */
    public function testConsumeQueryBudgetQuarantinesToUnknownAtCap(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::QUERY_ATTEMPTS => PendingRefundManager::MAX_QUERY_ATTEMPTS - 1,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $refund->expects($this->once())->method('save');

        $attempts = $this->manager->consumeQueryBudget(
            $refund,
            PendingRefundManager::EVIDENCE_TRANSPORT . 'cURL timeout 28'
        );

        $this->assertSame(PendingRefundManager::MAX_QUERY_ATTEMPTS, $attempts);
        $this->assertSame(
            RefundInterface::REFUND_STATE_UNKNOWN,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM), 'quarantine keeps the claim slot');
    }

    /**
     * BLOCKER 2 round 2: below the cap the row stays PROCESSING (provider
     * accepted the refund - the outcome is still open, still blocking).
     */
    public function testConsumeQueryBudgetBelowCapKeepsProcessing(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::QUERY_ATTEMPTS => 0,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
        ]);

        $attempts = $this->manager->consumeQueryBudget(
            $refund,
            PendingRefundManager::EVIDENCE_ANOMALY . 'missing return_code'
        );

        $this->assertSame(1, $attempts);
        $this->assertSame(
            RefundInterface::REFUND_STATE_PROCESSING,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
    }

    /**
     * BLOCKER 2 round 2: terminate WITHOUT an explicit semantic state
     * defaults to UNKNOWN quarantine (malformed payload / missing
     * creditmemo / state drift - an unconfirmed outcome is never assumed
     * safe to refund over).
     */
    public function testTerminateDefaultsToUnknownQuarantine(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::QUERY_ATTEMPTS => 4,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $refund->expects($this->once())->method('save');

        $this->manager->terminate($refund, 'reconcile_error: malformed stored query payload');

        $this->assertSame(
            PendingRefundManager::MAX_QUERY_ATTEMPTS,
            $refund->getData(RefundInterface::QUERY_ATTEMPTS)
        );
        $this->assertSame(
            RefundInterface::REFUND_STATE_UNKNOWN,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertStringStartsWith(
            'reconcile_error:',
            (string)$refund->getData(RefundInterface::LAST_ERROR)
        );
        $this->assertSame(1, $refund->getData(RefundInterface::ACTIVE_CLAIM), 'UNKNOWN keeps the claim slot');
    }

    /**
     * BLOCKER 2 round 2: a provider-CONFIRMED FAIL terminates as
     * CONFIRMED_FAIL - the money provably never left the provider, so the
     * row does NOT keep blocking a corrected refund attempt.
     */
    public function testTerminateConfirmedFailReleasesBlockingState(): void
    {
        $refund = $this->makeRefund(5, [
            RefundInterface::QUERY_ATTEMPTS => 4,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        $refund->expects($this->once())->method('save');

        $this->manager->terminate(
            $refund,
            PendingRefundManager::EVIDENCE_REFUND_FAILED . 'Refund time has expired.',
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL
        );

        $this->assertSame(
            RefundInterface::REFUND_STATE_CONFIRMED_FAIL,
            $refund->getData(RefundInterface::REFUND_STATE)
        );
        $this->assertNull($refund->getData(RefundInterface::ACTIVE_CLAIM), 'confirmed fail releases the claim slot');
        $this->assertSame(
            PendingRefundManager::MAX_QUERY_ATTEMPTS,
            $refund->getData(RefundInterface::QUERY_ATTEMPTS)
        );
    }

    /**
     * BLOCKER 2 round 2: a confirmed SUCCESS finalization flips the row to
     * CONFIRMED_SUCCESS in BOTH update binds (fresh finalize + recovery) -
     * outside the blocking filter set, control hands to the normal
     * refundable-balance checks.
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
            [RefundInterface::ENTITY_ID . ' = ?' => 5]
        );

        $this->assertTrue($this->manager->finalizeSuccess($refund));
    }
}
