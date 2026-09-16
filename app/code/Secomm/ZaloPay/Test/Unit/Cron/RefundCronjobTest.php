<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Cron\RefundCronjob;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;

/**
 * REFUND CRON 24-30 (TASK-CG6BM7): the bounded, terminal-explicit refund
 * query loop:
 *
 *  - per-item isolation: a broken item never blocks the batch;
 *  - SUCCESS (1)  -> creditmemo REFUNDED + row PROCESSED + last_error NULL;
 *  - FAIL (2)     -> TERMINAL: budget saturated (query_attempts = 96) + safe
 *    mapped last_error, never queried again, no false success;
 *  - PROCESSING/unknown -> budget consumed by one;
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

    private Creditmemo|MockObject $creditmemo;

    private RefundCronjob $cron;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(RefundCollectionFactory::class);
        $this->creditmemoRepository = $this->createMock(CreditmemoRepositoryInterface::class);
        $this->logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->refundQueryCommand = $this->createMock(RefundQueryCommand::class);
        $this->authorization = $this->createMock(Authorization::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->authorization->method('getMac')->willReturn('STUBBED-MAC');

        $this->creditmemo = $this->createMock(Creditmemo::class);
        $this->creditmemoRepository->method('get')->willReturn($this->creditmemo);

        $this->cron = new RefundCronjob(
            $this->collectionFactory,
            $this->creditmemoRepository,
            $this->logger,
            $this->refundQueryCommand,
            $this->createMock(DateTime::class),
            $this->authorization,
            $this->scopeConfig,
            new Json()
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
                        throw new \RuntimeException('CURL transport error 28');
                    }

                    return ['return_code' => 1, 'return_message' => 'Refund successful.'];
                }
            );

        $this->creditmemo->expects($this->once())->method('setState')->with(Creditmemo::STATE_REFUNDED);
        $this->creditmemoRepository->expects($this->once())->method('save');

        $this->cron->execute();
        $this->assertTrue((bool)$healthy->getIsProcessed());
    }

    /**
     * REFUND CRON 25: SUCCESS -> creditmemo REFUNDED, row PROCESSED,
     * last_error cleared.
     */
    public function testSuccessFlipsCreditmemoAndMarksRowProcessed(): void
    {
        $row = $this->refundRow(
            [RefundInterface::QUERY_ATTEMPTS => 4, RefundInterface::LAST_ERROR => 'stale evidence']
        );
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );

        $this->creditmemo->expects($this->once())->method('setState')->with(Creditmemo::STATE_REFUNDED);
        $this->creditmemoRepository->expects($this->once())->method('save');
        $row->expects($this->once())->method('save');

        $this->cron->execute();

        $this->assertTrue((bool)$row->getIsProcessed());
        $this->assertNull($row->getData(RefundInterface::LAST_ERROR));
    }

    /**
     * REFUND CRON 26: provider FAIL is TERMINAL — the budget saturates, a
     * safe mapped message lands in last_error, the row keeps
     * NOT_PROCESSED (evidence), and the creditmemo is NEVER flipped.
     */
    public function testProviderFailIsTerminalWithEvidence(): void
    {
        $row = $this->refundRow();
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        $this->creditmemo->expects($this->never())->method('setState');
        $this->creditmemoRepository->expects($this->never())->method('save');
        $this->logger->expects($this->once())->method('critical');
        $row->expects($this->once())->method('save');

        $this->cron->execute();

        $this->assertSame(
            RefundCronjob::MAX_QUERY_ATTEMPTS,
            (int)$row->getData(RefundInterface::QUERY_ATTEMPTS)
        );
        $this->assertSame('Refund time has expired.', $row->getData(RefundInterface::LAST_ERROR));
        $this->assertFalse((bool)$row->getIsProcessed());
    }

    /**
     * REFUND CRON 27: PROCESSING consumes exactly one budget unit and keeps
     * the row pending.
     */
    public function testProcessingConsumesOneBudgetUnit(): void
    {
        $row = $this->refundRow([RefundInterface::QUERY_ATTEMPTS => 3]);
        $this->stubCollection([$row]);

        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 3, 'return_message' => 'processing']
        );

        $this->creditmemo->expects($this->never())->method('setState');
        $this->logger->expects($this->never())->method('critical');
        $row->expects($this->once())->method('save');

        $this->cron->execute();

        $this->assertSame(4, (int)$row->getData(RefundInterface::QUERY_ATTEMPTS));
        $this->assertNull($row->getData(RefundInterface::LAST_ERROR));
        $this->assertFalse((bool)$row->getIsProcessed());
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

        $this->logger->expects($this->once())->method('critical');
        $row->expects($this->once())->method('save');

        $this->cron->execute();

        $this->assertSame(
            RefundCronjob::MAX_QUERY_ATTEMPTS,
            (int)$row->getData(RefundInterface::QUERY_ATTEMPTS)
        );
    }

    /**
     * REFUND CRON 30: a malformed stored payload is isolated — the item is
     * logged and skipped, the batch survives.
     */
    public function testMalformedPayloadIsSkippedAndBatchSurvives(): void
    {
        $malformed = $this->refundRow(
            ['entity_id' => 1, RefundInterface::ADDITIONAL_INFORMATION => 'not-json{{{']
        );
        $healthy = $this->refundRow(['entity_id' => 2]);

        $this->stubCollection([$malformed, $healthy]);

        $this->refundQueryCommand->expects($this->once())->method('getRefundQuery')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );
        $this->creditmemo->expects($this->once())->method('setState');
        $this->creditmemoRepository->expects($this->once())->method('save');

        $this->cron->execute();

        $this->assertTrue((bool)$healthy->getIsProcessed());
    }

    /**
     * REFUND CRON 30b: a scalar JSON payload (valid JSON, not an array) is
     * rejected explicitly — no undefined-key access downstream.
     */
    public function testScalarPayloadIsRejectedExplicitly(): void
    {
        $row = $this->refundRow(
            ['entity_id' => 1, RefundInterface::ADDITIONAL_INFORMATION => '"just-a-string"']
        );
        $this->stubCollection([$row]);

        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');
        $this->creditmemo->expects($this->never())->method('setState');
        $this->logger->expects($this->once())->method('error');

        $this->cron->execute();

        $this->assertSame(0, (int)$row->getData(RefundInterface::QUERY_ATTEMPTS));
    }
}
