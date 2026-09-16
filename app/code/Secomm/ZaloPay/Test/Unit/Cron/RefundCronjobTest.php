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
     */
    public function testProviderFailIsTerminalWithEvidence(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        $evidence = [];
        $this->pendingRefundManager->expects($this->once())
            ->method('terminate')
            ->willReturnCallback(function ($rowArg, string $evidenceArg) use (&$evidence) {
                $evidence[] = $evidenceArg;
            });
        $this->pendingRefundManager->expects($this->never())->method('finalizeSuccess');
        $this->logger->expects($this->once())->method('critical');

        $this->cron->execute();

        $this->assertSame('refund_failed: Refund time has expired.', $evidence[0] ?? 'NONE');
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
     * REFUND CRON 30d: a creditmemo whose state drifted away from PROCESSING
     * is TERMINAL reconcile — never double-finalize a refund resolved
     * outside this lifecycle.
     */
    public function testStateDriftIsTerminal(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->cmState = Creditmemo::STATE_REFUNDED;

        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $evidence = [];
        $this->pendingRefundManager->expects($this->once())
            ->method('terminate')
            ->willReturnCallback(function ($rowArg, string $evidenceArg) use (&$evidence) {
                $evidence[] = $evidenceArg;
            });
        $this->logger->expects($this->once())->method('critical');

        $this->cron->execute();

        $this->assertSame(
            'reconcile_error: credit memo state is ' . Creditmemo::STATE_REFUNDED
            . ' (expected ' . CreditmemoPlugin::STATE_PROCESSING . ')',
            $evidence[0] ?? 'NONE'
        );
    }
}
