<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Cron\RefundCronjob;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;
use Secomm\ZaloPay\Service\PendingRefundManager;

/**
 * REFUND CRON 24-30 (TASK-CG6BM7 corrective round): the bounded,
 * terminal-explicit refund query loop:
 *
 *  - per-item isolation: a broken item never blocks the batch;
 *  - SUCCESS (1)  -> PendingRefundManager::finalizeSuccess (native core
 *    accounting, exactly once via the row lock);
 *  - FAIL (2)     -> TERMINAL: budget saturated + safe mapped evidence;
 *  - transport    -> RETRYABLE: consumes exactly one budget unit (BLOCKER 2
 *    fix - every genuine attempt progresses observable state);
 *  - malformed payload / missing creditmemo / state drift -> TERMINAL
 *    reconcile (budget saturated with safe evidence, never queried again);
 *  - cap reached  -> explicit exhaustion (critical log, state NEVER mutated
 *    - F32);
 *  - selection filter (F32): NOT_PROCESSED AND the five unresolved states
 *    AND (query_attempts < 96 OR provider_success_local_pending) - a PSLP
 *    row stays selectable at/after the cap, terminal rows are excluded by
 *    STATE;
 *  - terminal rows (confirmed_success / confirmed_fail) never re-enter
 *    the query flow even when handed to processRefund directly (F33);
 *  - provider FAIL never touches the credit memo (F34 - no custom
 *    PROCESSING parking exists anymore; the CM is OPEN pre-success).
 */
class RefundCronjobTest extends TestCase
{
    private const M_REFUND_ID = '260916_1000_777';

    private RefundCollectionFactory|MockObject $collectionFactory;

    private CreditmemoRepositoryInterface|MockObject $creditmemoRepository;

    private Logger|MockObject $logger;

    private RefundQueryCommand|MockObject $refundQueryCommand;

    private Authorization|MockObject $authorization;

    private ScopeConfigInterface|MockObject $scopeConfig;

    private PendingRefundManager|MockObject $pendingRefundManager;

    private Creditmemo|MockObject $creditmemo;

    /**
     * Mutable test state: the creditmemo state served by the repository mock
     * (a second ->method() config would not override the first stub). OPEN
     * (1) is the only legitimate parked creditmemo state now (round 7 F34).
     */
    private int $cmState = Creditmemo::STATE_OPEN;

    /**
     * Mutable test state: creditmemo id -> resolved mock (null = missing).
     */
    private array $cmMap = [];

    /**
     * Mutable test state: the cron clock (DateTime::timestamp stub), so the
     * reconciliation-grace tests control "now" (round 5 F23).
     */
    private int $nowTs = 1800000000;

    private DateTime|MockObject $dateTime;

    private RefundCronjob $cron;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(RefundCollectionFactory::class);
        $this->creditmemoRepository = $this->createMock(CreditmemoRepositoryInterface::class);
        $this->logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->refundQueryCommand = $this->createMock(RefundQueryCommand::class);
        $this->authorization = $this->createMock(Authorization::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->pendingRefundManager = $this->createMock(PendingRefundManager::class);

        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->authorization->method('getMac')->willReturn('STUBBED-MAC');

        $this->cmState = Creditmemo::STATE_OPEN;
        $this->cmMap = [];
        $this->creditmemo = $this->createMock(Creditmemo::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->dateTime->method('timestamp')->willReturnCallback(fn (): int => $this->nowTs);
        $this->creditmemo->method('getState')->willReturnCallback(
            function (): int {
                return $this->cmState;
            }
        );
        $this->creditmemoRepository->method('get')->willReturnCallback(
            function (int $creditmemoId) {
                if (array_key_exists($creditmemoId, $this->cmMap) && $this->cmMap[$creditmemoId] === null) {
                    throw NoSuchEntityException::singleField('entity_id', $creditmemoId);
                }

                return $this->cmMap[$creditmemoId] ?? $this->creditmemo;
            }
        );

        $this->cron = new RefundCronjob(
            $this->collectionFactory,
            $this->creditmemoRepository,
            $this->logger,
            $this->refundQueryCommand,
            $this->dateTime,
            $this->authorization,
            $this->scopeConfig,
            new Json(),
            $this->pendingRefundManager
        );
    }

    /**
     * @param array $rowFields
     * @return RefundModel|MockObject
     */
    private function refundRow(array $rowFields = []): RefundModel|MockObject
    {
        $row = $this->getMockBuilder(RefundModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save'])
            ->getMock();
        $row->setId((int)($rowFields['entity_id'] ?? 1));
        $row->setData(
            array_merge(
                [
                    RefundInterface::CREDIT_MEMO_ID => 55,
                    RefundInterface::ADDITIONAL_INFORMATION => $this->queryPayload(),
                    RefundInterface::QUERY_ATTEMPTS => 0,
                    RefundInterface::LAST_ERROR => null,
                ],
                $rowFields
            )
        );

        return $row;
    }

    /**
     * A stored v2/query_refund payload (exactly the 3 official MAC keys).
     *
     * @return string
     */
    private function queryPayload(): string
    {
        return json_encode(
            [
                'app_id' => '1000',
                RefundInterface::M_REFUND_ID => self::M_REFUND_ID,
                'timestamp' => 1690000000000,
            ]
        );
    }

    /**
     * @param array $rows
     * @return RefundCollection|MockObject
     */
    private function stubCollection(array $rows): RefundCollection|MockObject
    {
        $collection = $this->createMock(RefundCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($rows));
        $this->collectionFactory->method('create')->willReturn($collection);

        return $collection;
    }

    /**
     * Capture the getUnprocessedRefunds() filters and evaluate them with
     * Magento collection semantics against synthetic rows: addFieldToFilter
     * calls AND together; the two-array form
     * ([f1, f2], [[c1], [c2]]) ORs f1-matches-c1 against f2-matches-c2.
     *
     * @param array $row Synthetic row fields (field => value).
     * @return bool Whether a row with these fields would be selected.
     */
    private function rowIsSelected(array $row): bool
    {
        $filters = [];
        $collection = $this->createMock(RefundCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$filters, $collection) {
                $filters[] = [$field, $condition];

                return $collection;
            }
        );
        $this->collectionFactory->method('create')->willReturn($collection);
        $this->cron->getUnprocessedRefunds();

        foreach ($filters as [$field, $condition]) {
            if (!$this->filterMatches($field, $condition, $row)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate ONE addFieldToFilter call against a synthetic row.
     *
     * @param string|array $field
     * @param array $condition
     * @param array $row
     * @return bool
     */
    private function filterMatches(string|array $field, array $condition, array $row): bool
    {
        if (is_array($field)) {
            // Two-array form: OR across the field/condition pairs.
            foreach ($field as $i => $singleField) {
                if ($this->filterMatches($singleField, $condition[$i], $row)) {
                    return true;
                }
            }

            return false;
        }

        // A condition may be an operator map (['eq' => x]) or a LIST of
        // operator maps ([['lt' => x]] - the two-array form wraps each
        // field's condition); a list ORs its groups.
        if (isset($condition[0]) && is_array($condition[0])) {
            foreach ($condition as $group) {
                if ($this->matchesOperators($field, $group, $row)) {
                    return true;
                }
            }

            return false;
        }

        return $this->matchesOperators($field, $condition, $row);
    }

    /**
     * Evaluate one operator map (eq / in / lt) against a synthetic row.
     *
     * @param string $field
     * @param array $condition
     * @param array $row
     * @return bool
     */
    private function matchesOperators(string $field, array $condition, array $row): bool
    {
        if (array_key_exists('in', $condition)) {
            return in_array($row[$field] ?? null, $condition['in'], true);
        }
        if (array_key_exists('eq', $condition)) {
            return ($row[$field] ?? null) === $condition['eq'];
        }
        if (array_key_exists('lt', $condition)) {
            return ($row[$field] ?? null) < $condition['lt'];
        }

        return false;
    }

    /**
     * REFUND CRON 29 (round 7 F32): selection is explicit AND bounded -
     * NOT_PROCESSED, the five unresolved states only, and (budget left OR
     * provider_success_local_pending). Terminal rows are excluded by the
     * STATE filter (never by budget saturation), and budget exhaustion
     * never implies a state change.
     */
    public function testSelectionFiltersUnprocessedRowsWithinBudget(): void
    {
        $collection = $this->stubCollection([]);
        $calls = [];
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$calls, $collection) {
                $calls[] = [$field, $condition];

                return $collection;
            }
        );

        $this->cron->execute();

        $this->assertCount(3, $calls);
        $this->assertSame(RefundInterface::IS_PROCESSED, $calls[0][0]);
        $this->assertSame(['eq' => RefundInterface::NOT_PROCESSED], $calls[0][1]);
        $this->assertSame(
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
            $calls[1]
        );
        // The two-array form ORs budget-left against the uncapped PSLP.
        $this->assertSame(
            [RefundInterface::QUERY_ATTEMPTS, RefundInterface::REFUND_STATE],
            $calls[2][0]
        );
        $this->assertSame(
            [[['lt' => RefundCronjob::MAX_QUERY_ATTEMPTS]], [['eq' => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING]]],
            $calls[2][1]
        );
    }

    /**
     * Round 7 F32 selection matrix, row level: PSLP at the cap is STILL
     * selected; a non-PSLP row at the cap is NOT; terminal rows are NOT
     * (regardless of budget); every unresolved state below the cap IS.
     *
     * @dataProvider selectionMatrixProvider
     * @param array $row
     * @param bool $expectedSelected
     */
    public function testSelectionMatrix(array $row, bool $expectedSelected): void
    {
        $this->assertSame(
            $expectedSelected,
            $this->rowIsSelected($row),
            'Selection mismatch for row: ' . json_encode($row)
        );
    }

    /**
     * @return array
     */
    public static function selectionMatrixProvider(): array
    {
        $unresolved = [
            RefundInterface::REFUND_STATE_INITIATING,
            RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
            RefundInterface::REFUND_STATE_PROCESSING,
            RefundInterface::REFUND_STATE_UNKNOWN,
        ];

        $cases = [];
        foreach ($unresolved as $state) {
            $cases['below cap selected ' . $state] = [
                ['is_processed' => false, 'refund_state' => $state, 'query_attempts' => 0],
                true,
            ];
            $cases['at cap dropped ' . $state] = [
                ['is_processed' => false, 'refund_state' => $state, 'query_attempts' => RefundCronjob::MAX_QUERY_ATTEMPTS],
                false,
            ];
        }
        $cases['PSLP at cap STILL selected'] = [
            ['is_processed' => false, 'refund_state' => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING, 'query_attempts' => RefundCronjob::MAX_QUERY_ATTEMPTS],
            true,
        ];
        $cases['PSLP below cap selected'] = [
            ['is_processed' => false, 'refund_state' => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING, 'query_attempts' => 95],
            true,
        ];
        $cases['confirmed_fail never selected'] = [
            ['is_processed' => false, 'refund_state' => RefundInterface::REFUND_STATE_CONFIRMED_FAIL, 'query_attempts' => 0],
            false,
        ];
        $cases['confirmed_success never selected'] = [
            ['is_processed' => true, 'refund_state' => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS, 'query_attempts' => 0],
            false,
        ];

        return $cases;
    }

    /**
     * Round 7 F32: a PSLP row AT the cap still runs the cron loop - and the
     * loop finalizes LOCALLY ONLY: no provider query (/query_refund) and no
     * credit memo load. The provider is never re-asked for PSLP money.
     */
    public function testPslpRowAtCapIsStillFinalizedWithoutProviderQuery(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
                RefundInterface::QUERY_ATTEMPTS => RefundCronjob::MAX_QUERY_ATTEMPTS,
            ]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->once())
            ->method('finalizeSuccess')
            ->with($this->identicalTo($row))
            ->willReturn(true);

        $this->cron->execute();
    }

    /**
     * Round 7 F33 defense-in-depth: a CONFIRMED_FAIL row handed to the loop
     * returns immediately - never queried, never finalized, never
     * re-terminated, no credit memo load, no budget consumed.
     */
    public function testConfirmedFailRowIsSkippedBeforeAnyProviderQuery(): void
    {
        $row = $this->refundRow(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_FAIL]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->never())->method('finalizeSuccess');
        $this->pendingRefundManager->expects($this->never())->method('terminate');
        $this->pendingRefundManager->expects($this->never())->method('consumeQueryBudget');

        $this->cron->execute();
    }

    /**
     * Round 7 F33 defense-in-depth: a CONFIRMED_SUCCESS row handed to the
     * loop returns immediately with the same guarantees.
     */
    public function testConfirmedSuccessRowIsSkippedBeforeAnyProviderQuery(): void
    {
        $row = $this->refundRow(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->never())->method('finalizeSuccess');
        $this->pendingRefundManager->expects($this->never())->method('terminate');
        $this->pendingRefundManager->expects($this->never())->method('consumeQueryBudget');

        $this->cron->execute();
    }

    /**
     * REFUND CRON 24: one broken item (query transport explosion) does not
     * stop the batch — the second row is still resolved to terminal SUCCESS.
     */
    public function testBrokenItemDoesNotBlockTheBatch(): void
    {
        $broken = $this->refundRow(['entity_id' => 1]);
        $healthy = $this->refundRow(['entity_id' => 2]);

        $this->stubCollection([$broken, $healthy]);

        $callIndex = 0;
        $this->refundQueryCommand->expects($this->exactly(2))
            ->method('getRefundQuery')
            ->willReturnCallback(
                function () use (&$callIndex) {
                    if (++$callIndex === 1) {
                        throw new RefundTransportException(__('Zalopay: Refund status could not be confirmed.'));
                    }

                    return ['return_code' => 1, 'return_message' => 'Refund successful.'];
                }
            );

        // The broken row consumes one budget unit (observable progression).
        $this->pendingRefundManager->expects($this->once())
            ->method('consumeQueryBudget')
            ->with($broken, $this->stringStartsWith('transport_error: '))
            ->willReturn(1);
        // The healthy row finalizes through the native accounting manager.
        $this->pendingRefundManager->expects($this->once())
            ->method('finalizeSuccess')
            ->with($healthy)
            ->willReturn(true);

        $this->cron->execute();
    }

    /**
     * REFUND CRON 25: SUCCESS delegates to the finalize manager exactly once
     * (native core accounting, row-lock exact-once inside the manager).
     */
    public function testSuccessFinalizesThroughTheManager(): void
    {
        $row = $this->refundRow(
            [RefundInterface::QUERY_ATTEMPTS => 4, RefundInterface::LAST_ERROR => 'stale evidence']
        );
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );

        $this->pendingRefundManager->expects($this->once())
            ->method('finalizeSuccess')
            ->with($row)
            ->willReturn(true);
        $this->pendingRefundManager->expects($this->never())->method('terminate');

        $this->logger->expects($this->atLeastOnce())->method('info');

        $this->cron->execute();
    }

    /**
     * REFUND CRON 25b: a duplicate SUCCESS (another run finalized it first —
     * the manager's row-lock re-check returns false) is a no-op, never a
     * second Magento or provider refund.
     */
    public function testDuplicateSuccessIsANoOp(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );

        $this->pendingRefundManager->expects($this->once())
            ->method('finalizeSuccess')
            ->with($row)
            ->willReturn(false);
        $this->pendingRefundManager->expects($this->never())->method('consumeQueryBudget');

        $this->cron->execute();
    }

    /**
     * REFUND CRON 25c: a finalize failure consumes one budget unit with safe
     * reconcile evidence (observable progression, bounded retry).
     */
    public function testFinalizeFailureConsumesBudgetWithEvidence(): void
    {
        $row = $this->refundRow([RefundInterface::QUERY_ATTEMPTS => 10]);
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );

        $this->pendingRefundManager->expects($this->once())
            ->method('finalizeSuccess')
            ->willThrowException(new \RuntimeException('lock timeout'));
        $this->pendingRefundManager->expects($this->once())
            ->method('consumeQueryBudget')
            ->with($row, $this->stringStartsWith('reconcile_error: '))
            ->willReturn(11);
        $this->logger->expects($this->once())->method('critical');

        $this->cron->execute();
    }

    /**
     * REFUND CRON 26: provider FAIL is TERMINAL — the budget saturates, a
     * safe mapped message lands in evidence, the row keeps NOT_PROCESSED
     * (evidence), and NO accounting manager call ever happens.
     * Round 2: the refusal carries the EXPLICIT semantic state
     * CONFIRMED_FAIL - the block on future refunds is RELEASED.
     * Round 7 F34: the credit memo is NEVER touched on a FAIL (no custom
     * PROCESSING parking exists anymore; the CM stays OPEN, accounting
     * untouched).
     */
    public function testProviderFailIsTerminalWithEvidence(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->creditmemo->expects($this->never())->method('setState');
        $this->creditmemoRepository->expects($this->never())->method('save');

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        $calls = [];
        $this->pendingRefundManager->expects($this->once())
            ->method('terminate')
            ->willReturnCallback(function ($rowArg, string $evidenceArg, ?string $stateArg = null) use (&$calls) {
                $calls[] = [$evidenceArg, $stateArg];
            });
        $this->pendingRefundManager->expects($this->never())->method('finalizeSuccess');
        $this->logger->expects($this->once())->method('critical');

        $this->cron->execute();

        $this->assertSame('refund_failed: Refund time has expired.', $calls[0][0] ?? 'NONE');
        // Round 2: CONFIRMED_FAIL - the money provably never left.
        $this->assertSame(RefundInterface::REFUND_STATE_CONFIRMED_FAIL, $calls[0][1] ?? 'NONE');
    }

    /**
     * REFUND CRON 27: PROCESSING consumes exactly one budget unit and keeps
     * the row pending with cleared evidence.
     */
    public function testProcessingConsumesOneBudgetUnit(): void
    {
        $row = $this->refundRow([RefundInterface::QUERY_ATTEMPTS => 3]);
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 3, 'return_message' => 'processing']
        );

        $this->pendingRefundManager->expects($this->once())
            ->method('consumeQueryBudget')
            ->with($row, null)
            ->willReturn(4);
        $this->logger->expects($this->never())->method('critical');

        $this->cron->execute();
    }

    /**
     * REFUND CRON 27b: a query transport failure consumes exactly one budget
     * unit with safe transport evidence (BLOCKER 2: the row can no longer
     * sit selected-forever without state progression).
     */
    public function testQueryTransportFailureConsumesOneBudgetUnit(): void
    {
        $row = $this->refundRow([RefundInterface::QUERY_ATTEMPTS => 2]);
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willThrowException(
            new RefundTransportException(__('Zalopay: Refund status could not be confirmed.'))
        );

        $this->pendingRefundManager->expects($this->once())
            ->method('consumeQueryBudget')
            ->with($row, 'transport_error: Zalopay: Refund status could not be confirmed.')
            ->willReturn(3);
        $this->pendingRefundManager->expects($this->never())->method('finalizeSuccess');
        $this->pendingRefundManager->expects($this->never())->method('terminate');

        $this->cron->execute();
    }

    /**
     * REFUND CRON 28: hitting the cap exhausts the row explicitly with a
     * critical log (the row then drops out of the selection filter).
     */
    public function testBudgetCapExhaustsRowExplicitly(): void
    {
        $row = $this->refundRow([RefundInterface::QUERY_ATTEMPTS => RefundCronjob::MAX_QUERY_ATTEMPTS - 1]);
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 3, 'return_message' => 'processing']
        );

        $this->pendingRefundManager->expects($this->once())
            ->method('consumeQueryBudget')
            ->with($row, null)
            ->willReturn(RefundCronjob::MAX_QUERY_ATTEMPTS);
        $this->logger->expects($this->once())->method('critical');

        $this->cron->execute();
    }

    /**
     * REFUND CRON 30: a malformed stored payload is TERMINAL reconcile (the
     * row can never be resolved without it) — budget saturated with safe
     * evidence, never queried, batch survives.
     */
    public function testMalformedPayloadIsTerminalAndBatchSurvives(): void
    {
        $malformed = $this->refundRow(
            ['entity_id' => 1, RefundInterface::ADDITIONAL_INFORMATION => 'not-json{{{']
        );
        $healthy = $this->refundRow(['entity_id' => 2]);

        $this->stubCollection([$malformed, $healthy]);

        $this->refundQueryCommand->expects($this->once())->method('getRefundQuery')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );
        $this->pendingRefundManager->expects($this->once())
            ->method('terminate')
            ->with($malformed, 'reconcile_error: malformed stored query payload');
        $this->pendingRefundManager->expects($this->once())->method('finalizeSuccess')->willReturn(true);
        $this->logger->expects($this->atLeastOnce())->method('critical');

        $this->cron->execute();
    }

    /**
     * REFUND CRON 30b: a payload without m_refund_id is malformed too —
     * terminal reconcile (missing identity is non-retryable).
     */
    public function testPayloadWithoutRefundIdIsTerminal(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::ADDITIONAL_INFORMATION => (string)json_encode(
                    ['app_id' => '1000', 'timestamp' => 1690000000000]
                ),
            ]
        );
        $this->stubCollection([$row]);

        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->once())
            ->method('terminate')
            ->with($row, 'reconcile_error: malformed stored query payload');
        $this->logger->expects($this->once())->method('critical');

        $this->cron->execute();
    }

    /**
     * REFUND CRON 30c: a missing creditmemo is TERMINAL reconcile — the row
     * is saturated with evidence and never blocks the batch.
     */
    public function testMissingCreditmemoIsTerminal(): void
    {
        $row = $this->refundRow(
            ['entity_id' => 1, RefundInterface::CREDIT_MEMO_ID => 999]
        );
        $healthy = $this->refundRow(['entity_id' => 2]);
        $this->stubCollection([$row, $healthy]);

        $this->cmMap[999] = null;

        $this->refundQueryCommand->expects($this->once())->method('getRefundQuery')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );
        $this->pendingRefundManager->expects($this->once())
            ->method('terminate')
            ->with($row, 'reconcile_error: credit memo missing');
        $this->pendingRefundManager->expects($this->once())->method('finalizeSuccess')->willReturn(true);
        $this->logger->expects($this->atLeastOnce())->method('critical');

        $this->cron->execute();
    }

    /**
     * Round 3 (step 2a): creditmemo already REFUNDED in Magento (e.g. crash
     * between the core accounting and the local row update after a sync
     * SUCCESS) - the row lands confirmed_success WITHOUT any provider
     * interaction: accounting was already applied, bookkeeping only.
     */
    public function testCreditmemoRefundedResolvesRowAsSuccess(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->cmState = Creditmemo::STATE_REFUNDED;

        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->once())
            ->method('markConfirmedSuccess')
            ->with($this->identicalTo($row));
        $this->pendingRefundManager->expects($this->never())->method('terminate');
        $this->pendingRefundManager->expects($this->never())->method('finalizeSuccess');

        $this->cron->execute();
    }

    /**
     * Round 3 (step 2b): a creditmemo state drift to CANCELED is TERMINAL
     * reconcile (resolved outside this lifecycle) - terminate defaults to
     * the UNKNOWN quarantine (outcome never confirmed, keeps blocking).
     */
    public function testCreditmemoStateDriftToCanceledIsTerminalUnknown(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->cmState = Creditmemo::STATE_CANCELED;

        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->once())
            ->method('terminate')
            ->with($row, $this->stringStartsWith('reconcile_error: credit memo state is 3'));
        $this->pendingRefundManager->expects($this->never())->method('finalizeSuccess');

        $this->cron->execute();
    }

    /**
     * Round 3 F13 (cron step 0): a PROVIDER_SUCCESS_LOCAL_PENDING row
     * finalizes LOCALLY ONLY - the provider is never contacted (no /refund,
     * no query_refund): the provider money is already out.
     */
    public function testPslpRowFinalizesLocallyWithoutProviderQuery(): void
    {
        $row = $this->refundRow(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->once())
            ->method('finalizeSuccess')
            ->with($this->identicalTo($row));
        $this->pendingRefundManager->expects($this->never())->method('terminate');
        $this->pendingRefundManager->expects($this->never())->method('consumeQueryBudget');

        $this->cron->execute();
    }

    /**
     * Round 3 F13: a PSLP finalize failure consumes one budget unit with
     * reconcile evidence - bounded, retried next run (provider money is
     * already out; never re-asked).
     */
    public function testPslpFinalizeFailureConsumesBudget(): void
    {
        $row = $this->refundRow(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->once())
            ->method('finalizeSuccess')
            ->willThrowException(new \Magento\Framework\Exception\LocalizedException(
                new \Magento\Framework\Phrase('Zalopay: The refund finalization failed locally.')
            ));
        $this->pendingRefundManager->expects($this->once())
            ->method('consumeQueryBudget')
            ->with($row, $this->stringStartsWith('reconcile_error: '))
            ->willReturn(5);
        $this->logger->expects($this->once())->method('critical');

        $this->cron->execute();
    }

    /**
     * Round 7 F34 (replaces the round 3 F15 release test): even a credit
     * memo sitting in the LEGACY parked display state 4 (custom PROCESSING,
     * written by the removed CreditmemoPlugin) is left completely untouched
     * by a provider-CONFIRMED FAIL - no setState, no save, no exception
     * masking: the row lands confirmed_fail and the CM needs manual
     * follow-up ONLY through its own lifecycle, never this cron.
     */
    public function testProviderFailLeavesLegacyParkedCreditmemoUntouched(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->cmState = 4; // legacy CreditmemoPlugin::STATE_PROCESSING display state

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        $this->pendingRefundManager->expects($this->once())
            ->method('terminate')
            ->willReturnCallback(function ($rowArg, string $evidenceArg, ?string $stateArg = null): void {
                self::assertSame('refund_failed: Refund time has expired.', $evidenceArg);
                self::assertSame(RefundInterface::REFUND_STATE_CONFIRMED_FAIL, $stateArg);
            });
        $this->creditmemo->expects($this->never())->method('setState');
        $this->creditmemoRepository->expects($this->never())->method('save');
        $this->logger->expects($this->once())->method('critical');

        $this->cron->execute();
    }

    /**
     * Round 7 F34 (replaces the round 3 F15 release test): a
     * provider-CONFIRMED FAIL never mutates the credit memo in any state -
     * order/invoice/Magento accounting stays untouched (the money provably
     * never left) and a corrected future refund sees the natural OPEN CM.
     */
    public function testProviderFailNeverTouchesCreditmemo(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->cmState = Creditmemo::STATE_OPEN;

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        $this->pendingRefundManager->expects($this->once())->method('terminate');
        $this->creditmemo->expects($this->never())->method('setState');
        $this->creditmemoRepository->expects($this->never())->method('save');

        $this->cron->execute();
    }

    /**
     * Round 6 F26: a FRESH UNBOUND LOCAL_READY row (created_at within
     * LOCAL_READY_GRACE_SECONDS) may still be owned by a live request
     * inside the local bind phase - the cron does NOTHING: no query, no
     * terminate, no budget consumption, no release. The claim is
     * retained for its owner.
     */
    public function testFreshUnboundLocalReadyIsNeverTouched(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::CREDIT_MEMO_ID => null,
                RefundInterface::CREATED_AT => gmdate('Y-m-d H:i:s', $this->nowTs - 30),
            ]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->never())->method('consumeQueryBudget');
        $this->pendingRefundManager->expects($this->never())->method('terminate');

        $this->cron->execute();

        $this->assertSame(RefundInterface::REFUND_STATE_INITIATING, (string)$row->getData(RefundInterface::REFUND_STATE));
    }

    /**
     * Round 6 F26: a FRESH BOUND LOCAL_READY row is likewise untouched -
     * even with a Credit Memo bound, the provider-start boundary has NOT
     * been crossed, so the local bind phase may still be mid-flight.
     */
    public function testFreshBoundLocalReadyIsNeverTouched(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::CREDIT_MEMO_ID => 55,
                RefundInterface::ACTIVE_CLAIM => 1,
                RefundInterface::CREATED_AT => gmdate('Y-m-d H:i:s', $this->nowTs - 30),
            ]
        );
        $this->stubCollection([$row]);

        $this->cmState = Creditmemo::STATE_OPEN;
        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->never())->method('consumeQueryBudget');
        $this->pendingRefundManager->expects($this->never())->method('terminate');

        $this->cron->execute();

        $this->assertSame(RefundInterface::REFUND_STATE_INITIATING, (string)$row->getData(RefundInterface::REFUND_STATE));
        $this->assertSame(1, (int)$row->getData(RefundInterface::ACTIVE_CLAIM));
        $this->assertSame(55, (int)$row->getData(RefundInterface::CREDIT_MEMO_ID));
    }

    /**
     * Round 6 F26: a STALE UNBOUND LOCAL_READY row (age >= grace) proves
     * the owner crashed BEFORE provider-start - provider I/O was
     * impossible by construction, so the release is truthful: terminate
     * confirmed_fail + claim released, provider never asked.
     */
    public function testStaleUnboundLocalReadyIsReleasedWithoutQuery(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::CREDIT_MEMO_ID => null,
                RefundInterface::CREATED_AT => gmdate('Y-m-d H:i:s', $this->nowTs - 301),
            ]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->never())->method('consumeQueryBudget');
        $this->pendingRefundManager->expects($this->once())->method('terminate')
            ->with(
                $this->identicalTo($row),
                $this->identicalTo(
                    PendingRefundManager::EVIDENCE_ABANDONED
                    . 'stale LOCAL_READY claim - provider I/O impossible by construction'
                ),
                $this->identicalTo(RefundInterface::REFUND_STATE_CONFIRMED_FAIL)
            );

        $this->cron->execute();
    }

    /**
     * Round 6 F26: a STALE BOUND LOCAL_READY row releases exactly the
     * same way, BEFORE the step-1 Credit Memo lookup: the CM is never
     * loaded, the provider never asked.
     */
    public function testStaleBoundLocalReadyIsReleasedWithoutQuery(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::CREDIT_MEMO_ID => 55,
                RefundInterface::CREATED_AT => gmdate('Y-m-d H:i:s', $this->nowTs - 301),
            ]
        );
        $this->stubCollection([$row]);

        $this->cmState = Creditmemo::STATE_OPEN;
        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->once())->method('terminate')
            ->with(
                $this->identicalTo($row),
                $this->identicalTo(
                    PendingRefundManager::EVIDENCE_ABANDONED
                    . 'stale LOCAL_READY claim - provider I/O impossible by construction'
                ),
                $this->identicalTo(RefundInterface::REFUND_STATE_CONFIRMED_FAIL)
            );

        $this->cron->execute();
    }

    /**
     * Round 6 F26: a LOCAL_READY row whose created_at is missing cannot
     * be age-checked - the cron consumes one bounded budget unit with
     * reconcile evidence and NEVER releases a possibly-live claim on a
     * data glitch, never queries.
     */
    public function testLocalReadyMissingTimestampConsumesBudgetNeverReleases(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::CREDIT_MEMO_ID => null,
                RefundInterface::CREATED_AT => null,
            ]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->never())->method('terminate');
        $this->pendingRefundManager->expects($this->once())->method('consumeQueryBudget')
            ->with(
                $this->identicalTo($row),
                $this->identicalTo(
                    PendingRefundManager::EVIDENCE_RECONCILE . 'local-ready timestamp missing'
                )
            );

        $this->cron->execute();
    }

    /**
     * Round 6 F26 race proof (unit half): while request A is inside the
     * local bind phase (claim acquired, LOCAL_READY, fresh created_at),
     * a cron run leaves the row COMPLETELY untouched; afterwards A's
     * markProviderRequestStarted still succeeds on the SAME row (claim
     * retained). The continuation ordering ['save','bind','start',
     * 'provider'] + provider-exactly-once is pinned by the plugin
     * REQUIRED test testRealAdminUnsavedCreditmemoBindBeforeProvider.
     */
    public function testRaceCronDuringLocalBindLeavesOwnerFullContinuation(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::CREDIT_MEMO_ID => 55,
                RefundInterface::ACTIVE_CLAIM => 1,
                RefundInterface::CREATED_AT => gmdate('Y-m-d H:i:s', $this->nowTs - 30),
            ]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->never())->method('consumeQueryBudget');
        $this->pendingRefundManager->expects($this->never())->method('terminate');

        $this->cron->execute();

        $this->assertSame(RefundInterface::REFUND_STATE_INITIATING, (string)$row->getData(RefundInterface::REFUND_STATE));
        $this->assertSame(1, (int)$row->getData(RefundInterface::ACTIVE_CLAIM));

        $this->pendingRefundManager->expects($this->once())->method('markProviderRequestStarted')
            ->with($this->identicalTo($row))
            ->willReturn(true);
        static::assertTrue($this->pendingRefundManager->markProviderRequestStarted($row));
    }

    /**
     * Round 5 F23: a freshly-started provider request (started_at within
     * the reconciliation grace, grace 120s > HTTP timeout 10s) is NEVER
     * queried - request A owns its claim until its provider request has
     * completed/timed out. The cron does nothing to the row at all.
     */
    public function testFreshProviderStartWithinGraceIsNeverQueried(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                RefundInterface::PROVIDER_REQUEST_STARTED_AT => gmdate('Y-m-d H:i:s', $this->nowTs - 30),
            ]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->never())->method('consumeQueryBudget');
        $this->pendingRefundManager->expects($this->never())->method('terminate');
        $this->pendingRefundManager->expects($this->never())->method('finalizeSuccess');

        $this->cron->execute();
    }

    /**
     * Round 5 F23: after the grace elapsed, crash recovery queries the SAME
     * m_refund_id (the stale provider-start row takes the identity query
     * path).
     */
    public function testStaleProviderStartQueriesSameMRefundId(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                RefundInterface::PROVIDER_REQUEST_STARTED_AT => gmdate('Y-m-d H:i:s', $this->nowTs - 400),
            ]
        );
        $this->stubCollection([$row]);

        $this->cmState = Creditmemo::STATE_OPEN;
        $this->refundQueryCommand->expects($this->once())->method('getRefundQuery')
            ->willReturn(['return_code' => 3, 'return_message' => 'processing']);
        $this->pendingRefundManager->expects($this->once())->method('consumeQueryBudget')
            ->with($row, null);
        $this->pendingRefundManager->expects($this->never())->method('terminate');

        $this->cron->execute();
    }

    /**
     * Round 5 F23: a provider-start row with a MISSING timestamp cannot be
     * grace-checked - bounded budget consumption with reconcile evidence,
     * never a query and never a release.
     */
    public function testProviderStartMissingTimestampConsumesBudget(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                RefundInterface::PROVIDER_REQUEST_STARTED_AT => null,
            ]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->once())->method('consumeQueryBudget')
            ->with(
                $this->identicalTo($row),
                $this->stringStartsWith(PendingRefundManager::EVIDENCE_RECONCILE)
            );
        $this->pendingRefundManager->expects($this->never())->method('terminate');
        $this->pendingRefundManager->expects($this->never())->method('finalizeSuccess');

        $this->cron->execute();
    }

    /**
     * Round 4 F18: an UNKNOWN row with an OPEN creditmemo still queries the
     * SAME m_refund_id (state-driven recovery) - OPEN is the legitimate
     * pre-provider/parked state, NOT drift.
     */
    public function testUnknownRowWithOpenCreditmemoQueriesInsteadOfDrift(): void
    {
        $row = $this->refundRow(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN]
        );
        $this->stubCollection([$row]);

        $this->cmState = Creditmemo::STATE_OPEN;
        $this->refundQueryCommand->expects($this->once())->method('getRefundQuery')
            ->willReturn(['return_code' => 3, 'return_message' => 'processing']);
        $this->pendingRefundManager->expects($this->once())->method('consumeQueryBudget')
            ->with($row, null);
        $this->pendingRefundManager->expects($this->never())->method('terminate');

        $this->cron->execute();
    }
}
