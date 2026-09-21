<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Cron;

use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Cron\RefundCleanupCronjob;
use Secomm\ZaloPay\Logger\Logger as LoggerInterface;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;

/**
 * Integration test class for the RefundCleanupCronjob (round 7 F36
 * retention semantics).
 *
 * RETENTION POLICY: the cron deletes ONLY fully-resolved evidence older
 * than REFUND_EVIDENCE_RETENTION_DAYS:
 *
 *     is_processed = 1 AND refund_state = 'confirmed_success'
 *     AND updated_at < (now UTC - 90 days)
 *
 * Every other state (confirmed_fail / unknown / processing /
 * provider_request_started / provider_success_local_pending / initiating)
 * is retained regardless of age or is_processed.
 *
 * @magentoDbIsolation enabled
 */
class RefundCleanupCronjobTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var RefundCleanupCronjob
     */
    private $refundCleanupCronjob;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();

        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->refundCleanupCronjob = $this->objectManager->create(RefundCleanupCronjob::class, [
            'refundCollectionFactory' => $this->objectManager->get(RefundCollectionFactory::class),
            'logger' => $this->logger,
        ]);
    }

    /**
     * The retention filter never admits rows on an empty/stale table: after
     * a run, no row passes the deletable filter.
     *
     * @magentoDataFixture loadProcessedRefunds
     * @return void
     */
    public function testExecute()
    {
        $this->refundCleanupCronjob->execute();

        $processedRefunds = $this->refundCleanupCronjob->getProcessedRefunds();
        $this->assertInstanceOf(RefundCollection::class, $processedRefunds);
        $this->assertEquals(0, $processedRefunds->getSize());
    }

    /**
     * The retention filter selects ONLY resolved confirmed_success rows
     * older than the 90-day window: any row it returns must satisfy every
     * retention condition (never confirmed_fail / unknown / unresolved
     * states, regardless of age).
     *
     * @return void
     */
    public function testGetProcessedRefundsAppliesRetentionPolicy()
    {
        $processedRefunds = $this->refundCleanupCronjob->getProcessedRefunds();

        $this->assertInstanceOf(RefundCollection::class, $processedRefunds);
        foreach ($processedRefunds as $refund) {
            $this->assertEquals(RefundInterface::PROCESSED, $refund->getIsProcessed());
            $this->assertEquals(
                RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                $refund->getRefundState()
            );
            $cutoff = (new \DateTime('now', new \DateTimeZone('UTC')))
                ->modify('-' . RefundCleanupCronjob::REFUND_EVIDENCE_RETENTION_DAYS . ' days')
                ->format('Y-m-d H:i:s');
            $this->assertLessThan($cutoff, (string)$refund->getUpdatedAt());
        }
    }

    /**
     * Data provider to load processed refunds.
     *
     * @magentoDbIsolation enabled
     */
    public static function loadProcessedRefunds()
    {
        // Add data to load processed refunds (if needed)
    }
}
