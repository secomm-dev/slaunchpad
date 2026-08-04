<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Cron;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Logger\Logger as LoggerInterface;
use Secomm\ZaloPay\Cron\RefundCleanupCronjob;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;

/**
 * Integration test class for the RefundCleanupCronjob.
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
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        // Retrieve the Object Manager instance
        $this->objectManager = Bootstrap::getObjectManager();

        // Instantiate the logger mock
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Instantiate the CreditmemoRepository
        $this->creditmemoRepository = $this->objectManager->get(CreditmemoRepositoryInterface::class);

        // Instantiate the RefundCleanupCronjob with the mocked logger
        $this->refundCleanupCronjob = $this->objectManager->create(RefundCleanupCronjob::class, [
            'refundCollectionFactory' => $this->objectManager->get(\Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory::class),
            'creditmemoRepository' => $this->creditmemoRepository,
            'logger' => $this->logger,
        ]);
    }

    /**
     * Test the execution of the RefundCleanupCronjob.
     *
     * @magentoDataFixture loadProcessedRefunds
     * @return void
     */
    public function testExecute()
    {
        // Execute the RefundCleanupCronjob
        $this->refundCleanupCronjob->execute();

        // Assertions
        $processedRefunds = $this->refundCleanupCronjob->getProcessedRefunds();
        $this->assertInstanceOf(RefundCollection::class, $processedRefunds);
        $this->assertEquals(0, $processedRefunds->getSize());
    }

    /**
     * Test the getProcessedRefunds method.
     *
     * @return void
     */
    public function testGetProcessedRefunds()
    {
        // Additional setup if needed

        // Execute the getProcessedRefunds method
        $processedRefunds = $this->refundCleanupCronjob->getProcessedRefunds();

        // Assertions
        $this->assertInstanceOf(RefundCollection::class, $processedRefunds);
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
