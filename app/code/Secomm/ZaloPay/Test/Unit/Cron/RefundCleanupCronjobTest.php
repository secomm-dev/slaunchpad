<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Cron\RefundCleanupCronjob;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;

/**
 * Round 7 F36: refund evidence retention.
 *
 * The cleanup may delete ONLY rows that are fully resolved AND older than
 * the 90-day retention window:
 *
 *     is_processed = 1 AND refund_state = 'confirmed_success'
 *     AND updated_at < (now UTC - 90 days)
 *
 * The retention matrix below pins that NOTHING else is ever deletable:
 * confirmed_fail / unknown / processing / provider_request_started /
 * provider_success_local_pending / initiating rows are retained regardless
 * of age or is_processed (financial evidence for manual reconciliation).
 */
class RefundCleanupCronjobTest extends TestCase
{
    private RefundCollectionFactory|MockObject $collectionFactory;

    private Logger|MockObject $logger;

    private ScopeConfigInterface|MockObject $scopeConfig;

    private RefundCleanupCronjob $cron;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(RefundCollectionFactory::class);
        $this->logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);

        $this->cron = new RefundCleanupCronjob(
            $this->collectionFactory,
            $this->logger,
            $this->scopeConfig
        );
    }

    /**
     * The exact retention cutoff string: now UTC minus 90 days, 'Y-m-d H:i:s'
     * (comparable against the Magento-managed UTC updated_at column).
     */
    public function testRetentionCutoffIs90DaysAgoInUtcFormat(): void
    {
        $cutoff = $this->captureCutoff();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cutoff);

        $expectedMin = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('-91 days');
        $expectedMax = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('-89 days');
        $parsed = new \DateTime($cutoff, new \DateTimeZone('UTC'));
        $this->assertGreaterThanOrEqual($expectedMin, $parsed, 'cutoff must be ~90 days in the past');
        $this->assertLessThanOrEqual($expectedMax, $parsed);
    }

    /**
     * The three retention filters are pinned exactly (AND semantics).
     */
    public function testRetentionFiltersArePinned(): void
    {
        $filters = [];
        $this->stubFilterCapturingCollection($filters);

        $this->cron->getProcessedRefunds();

        $this->assertCount(3, $filters);
        $this->assertSame(RefundInterface::IS_PROCESSED, $filters[0][0]);
        $this->assertSame(['eq' => RefundInterface::PROCESSED], $filters[0][1]);
        $this->assertSame(RefundInterface::REFUND_STATE, $filters[1][0]);
        $this->assertSame(['eq' => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS], $filters[1][1]);
        $this->assertSame('updated_at', $filters[2][0]);
        // The cutoff is the captured 'lt' operand: a UTC timestamp ~90 days
        // in the past (the retention window).
        $cutoff = (string)($filters[2][1]['lt'] ?? '');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cutoff);
        $expectedMin = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('-91 days');
        $expectedMax = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('-89 days');
        $parsed = new \DateTime($cutoff, new \DateTimeZone('UTC'));
        $this->assertGreaterThanOrEqual($expectedMin, $parsed);
        $this->assertLessThanOrEqual($expectedMax, $parsed);
    }

    /**
     * Round 7 F36 retention matrix (row level, evaluated with Magento
     * collection AND semantics against the exact filters the cron issues):
     * ONLY confirmed_success past the window is deletable; every other
     * state is retained at ANY age, and fresh confirmed_success is retained.
     *
     * @dataProvider retentionMatrixProvider
     * @param array $row
     * @param bool $expectedDeletable
     */
    public function testRetentionMatrix(array $row, bool $expectedDeletable): void
    {
        $this->assertSame(
            $expectedDeletable,
            $this->rowMatchesRetentionFilters($row),
            'Retention mismatch for row: ' . json_encode($row)
        );
    }

    /**
     * @return array
     */
    public static function retentionMatrixProvider(): array
    {
        $old = gmdate('Y-m-d H:i:s', time() - 91 * 86400);
        $fresh = gmdate('Y-m-d H:i:s', time() - 1 * 86400);
        $ancient = gmdate('Y-m-d H:i:s', time() - 365 * 86400);

        // [row, deletable]
        return [
            'confirmed_success age 91d -> DELETABLE' => [
                ['is_processed' => true, 'refund_state' => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS, 'updated_at' => $old],
                true,
            ],
            'confirmed_success age 1d -> retained' => [
                ['is_processed' => true, 'refund_state' => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS, 'updated_at' => $fresh],
                false,
            ],
            'confirmed_fail ancient -> retained' => [
                ['is_processed' => true, 'refund_state' => RefundInterface::REFUND_STATE_CONFIRMED_FAIL, 'updated_at' => $ancient],
                false,
            ],
            'confirmed_fail unprocessed -> retained' => [
                ['is_processed' => false, 'refund_state' => RefundInterface::REFUND_STATE_CONFIRMED_FAIL, 'updated_at' => $ancient],
                false,
            ],
            'unknown ancient -> retained' => [
                ['is_processed' => false, 'refund_state' => RefundInterface::REFUND_STATE_UNKNOWN, 'updated_at' => $ancient],
                false,
            ],
            'processing ancient -> retained' => [
                ['is_processed' => false, 'refund_state' => RefundInterface::REFUND_STATE_PROCESSING, 'updated_at' => $ancient],
                false,
            ],
            'provider_request_started ancient -> retained' => [
                ['is_processed' => false, 'refund_state' => RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED, 'updated_at' => $ancient],
                false,
            ],
            'provider_success_local_pending ancient -> retained' => [
                ['is_processed' => false, 'refund_state' => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING, 'updated_at' => $ancient],
                false,
            ],
            'initiating ancient -> retained' => [
                ['is_processed' => false, 'refund_state' => RefundInterface::REFUND_STATE_INITIATING, 'updated_at' => $ancient],
                false,
            ],
            'processed-but-wrong-state (unknown) -> retained' => [
                ['is_processed' => true, 'refund_state' => RefundInterface::REFUND_STATE_UNKNOWN, 'updated_at' => $ancient],
                false,
            ],
        ];
    }

    /**
     * execute() deletes ONLY the rows the retention filter admits (the
     * mocked collection simulates the DB selection): the eligible
     * confirmed_success row is deleted, the ineligible one is never
     * touched, and the deletion is logged.
     */
    public function testExecuteDeletesOnlyEligibleRows(): void
    {
        $old = gmdate('Y-m-d H:i:s', time() - 91 * 86400);
        $eligible = $this->makeRow(['entity_id' => 1]);
        $retained = $this->makeRow(['entity_id' => 2]);

        $collection = $this->createMock(RefundCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        // Simulate the DB: only the eligible row comes back.
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$eligible]));
        $this->collectionFactory->method('create')->willReturn($collection);

        $eligible->expects($this->once())->method('delete');
        $retained->expects($this->never())->method('delete');
        $this->logger->expects($this->once())->method('info')
            ->with($this->stringContains('1 confirmed_success row(s)'));

        $this->cron->execute();
    }

    /**
     * When nothing is eligible, NOTHING is deleted and the run is logged as
     * a no-op (never an error).
     */
    public function testExecuteWithNothingEligibleDeletesNothing(): void
    {
        $collection = $this->createMock(RefundCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->logger->expects($this->once())->method('info')
            ->with($this->stringContains('nothing eligible'));
        $this->logger->expects($this->never())->method('error');

        $this->cron->execute();
    }

    /**
     * A deletion failure on ONE row surfaces as an error log and must never
     * crash the cron (the try/catch contract).
     */
    public function testExecuteSwallowsAndLogsDeletionFailure(): void
    {
        $row = $this->makeRow(['entity_id' => 1]);
        $collection = $this->createMock(RefundCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$row]));
        $this->collectionFactory->method('create')->willReturn($collection);

        $row->method('delete')->willThrowException(new \RuntimeException('db locked'));
        $this->logger->expects($this->once())->method('error')
            ->with($this->stringContains('db locked'));

        $this->cron->execute();
    }

    /**
     * A disabled payment method short-circuits the cleanup: no collection
     * is created, nothing is deleted.
     */
    public function testInactiveMethodShortCircuits(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(false);
        $this->cron = new RefundCleanupCronjob($this->collectionFactory, $this->logger, $scopeConfig);

        $this->collectionFactory->expects($this->never())->method('create');
        $this->cron->execute();
    }

    /**
     * @param array $fields
     * @return RefundModel|MockObject
     */
    private function makeRow(array $fields): RefundModel|MockObject
    {
        $row = $this->getMockBuilder(RefundModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $row->setId((int)$fields['entity_id']);

        return $row;
    }

    /**
     * @param array $filters
     * @return void
     */
    private function stubFilterCapturingCollection(array &$filters): void
    {
        $collection = $this->createMock(RefundCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$filters, $collection) {
                $filters[] = [$field, $condition];

                return $collection;
            }
        );
        $this->collectionFactory->method('create')->willReturn($collection);
    }

    /**
     * Evaluate the captured getProcessedRefunds() filters (AND) against a
     * synthetic row - a faithful in-memory model of the SQL selection.
     *
     * @param array $row
     * @return bool
     */
    private function rowMatchesRetentionFilters(array $row): bool
    {
        $filters = [];
        $this->stubFilterCapturingCollection($filters);
        $this->cron->getProcessedRefunds();

        foreach ($filters as [$field, $condition]) {
            $actual = $row[$field] ?? null;
            if (isset($condition['eq']) && $actual !== $condition['eq']) {
                return false;
            }
            if (isset($condition['lt']) && !($actual < $condition['lt'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run getProcessedRefunds() once and return the updated_at cutoff it
     * filtered with.
     *
     * @return string
     */
    private function captureCutoff(): string
    {
        $filters = [];
        $this->stubFilterCapturingCollection($filters);
        $this->cron->getProcessedRefunds();

        return (string)($filters[2][1]['lt'] ?? '');
    }
}
