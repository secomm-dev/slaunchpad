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
use Secomm\ZaloPay\Plugin\Model\Order\CreditmemoPlugin;
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
 *  - cap reached  -> explicit exhaustion (critical log);
 *  - selection filter: NOT_PROCESSED AND query_attempts < 96.
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
     * (a second ->method() config would not override the first stub).
     */
    private int $cmState = CreditmemoPlugin::STATE_PROCESSING;

    /**
     * Mutable test state: creditmemo id -> resolved mock (null = missing).
     */
    private array $cmMap = [];

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

        $this->cmState = CreditmemoPlugin::STATE_PROCESSING;
        $this->cmMap = [];
        $this->creditmemo = $this->createMock(Creditmemo::class);
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
            $this->createMock(DateTime::class),
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
     * REFUND CRON 29: selection is bounded — only NOT_PROCESSED rows with a
     * non-exhausted query budget are picked up.
     */
    public function testSelectionFiltersUnprocessedRowsWithinBudget(): void
    {
        $collection = $this->stubCollection([]);
        $calls = [];
        $collection->method('addFieldToFilter')->willReturnCallback(
            function (string $field, array $condition) use (&$calls, $collection) {
                $calls[] = [$field, $condition];

                return $collection;
            }
        );

        $this->cron->execute();

        $this->assertCount(2, $calls);
        $this->assertSame('is_processed', $calls[0][0]);
        $this->assertSame(['eq' => RefundInterface::NOT_PROCESSED], $calls[0][1]);
        $this->assertSame(RefundInterface::QUERY_ATTEMPTS, $calls[1][0]);
        $this->assertSame(['lt' => RefundCronjob::MAX_QUERY_ATTEMPTS], $calls[1][1]);
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
     */
    public function testProviderFailIsTerminalWithEvidence(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->creditmemo->expects($this->once())->method('setState')->with(Creditmemo::STATE_OPEN);
        $this->creditmemoRepository->expects($this->once())->method('save')
            ->with($this->identicalTo($this->creditmemo));

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
     * Round 3 (F15): the FAIL release failing to SAVE (repository down)
     * must never mask the terminal confirmed_fail - swallowed with a
     * critical log, manual fix path documented.
     */
    public function testProviderFailReleaseSaveFailureIsSwallowed(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        $this->pendingRefundManager->expects($this->once())
            ->method('terminate')
            ->willReturnCallback(function ($rowArg, string $evidenceArg, ?string $stateArg = null): void {
                self::assertSame('refund_failed: Refund time has expired.', $evidenceArg);
                self::assertSame(RefundInterface::REFUND_STATE_CONFIRMED_FAIL, $stateArg);
            });
        $this->creditmemo->expects($this->once())->method('setState')->with(Creditmemo::STATE_OPEN);
        $this->creditmemoRepository->expects($this->once())->method('save')
            ->willThrowException(new \RuntimeException('repo down'));
        $this->logger->expects($this->atLeastOnce())->method('critical');

        $this->cron->execute();
    }

    /**
     * Round 3 (F15): a provider-CONFIRMED FAIL releases the parked
     * credit memo back to OPEN (Magento-compatible: core validateForRefund
     * requires OPEN for a follow-up refund) - order/invoice totals are
     * never touched by this release.
     */
    public function testProviderFailReleasesCreditmemoToOpen(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        $this->pendingRefundManager->expects($this->once())->method('terminate');
        $this->creditmemo->expects($this->once())->method('setState')->with(Creditmemo::STATE_OPEN);
        $this->creditmemoRepository->expects($this->once())->method('save')
            ->with($this->identicalTo($this->creditmemo));

        $this->cron->execute();
    }

    /**
     * Round 4 F18: an INITIATING row with NO bound credit memo can never
     * have reached provider I/O (provider gate = claim + stable m_refund_id
     * + bound credit_memo_id) - the stale claim is abandoned (confirmed_fail,
     * claim released) and the provider is never asked.
     */
    public function testUnboundInitiatingClaimIsAbandonedBeforeProviderIo(): void
    {
        $row = $this->refundRow(
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::CREDIT_MEMO_ID => null,
            ]
        );
        $this->stubCollection([$row]);

        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->pendingRefundManager->expects($this->once())->method('terminate')
            ->with(
                $this->identicalTo($row),
                $this->stringStartsWith(PendingRefundManager::EVIDENCE_ABANDONED),
                $this->identicalTo(RefundInterface::REFUND_STATE_CONFIRMED_FAIL)
            );

        $this->cron->execute();
    }

    /**
     * Round 4 F18: an INITIATING row WITH a bound credit memo queries the
     * SAME m_refund_id regardless of the creditmemo sitting in OPEN (the
     * legitimate pre-provider bind state) - recovery by identity, never a
     * drift termination.
     */
    public function testInitiatingBoundWithOpenCreditmemoQueriesSameIdentity(): void
    {
        $row = $this->refundRow(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING]
        );
        $this->stubCollection([$row]);

        $this->cmState = Creditmemo::STATE_OPEN;
        $this->refundQueryCommand->expects($this->once())->method('getRefundQuery')
            ->willReturn(['return_code' => 3, 'return_message' => 'processing']);
        $this->pendingRefundManager->expects($this->once())->method('consumeQueryBudget')
            ->with($row, null);
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
