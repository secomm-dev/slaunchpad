<?php
/**
 * RefundCronjobTest.php
 *
 * Integration test for the RefundCronjob class.
 *
 * @copyright Copyright (c) 2023. Secomm All rights reserved.
 * @see COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Cron;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Cron\RefundCronjob;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Command\SaveCommand;
use Secomm\ZaloPay\Model\Data\RefundData;
use Magento\Payment\Gateway\ConfigInterface as PaymentConfigInterface;
use Magento\Payment\Gateway\ConfigInterface;

/**
 * Integration test class for the RefundCronjob.
 *
 * @magentoDbIsolation enabled
 */
class RefundCronjobTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var RefundCronjob
     */
    private $refundCronjob;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RefundQueryCommand|\PHPUnit\Framework\MockObject\MockObject
     */
    private $refundQueryCommandMock;

    /**
     * @var SaveCommand|\PHPUnit\Framework\MockObject\MockObject
     */
    private $saveCommandMock;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    /**
     * @var PaymentConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $paymentConfigMock;

    /**
     * @var ConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configMock;

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

        // Instantiate the RefundQueryCommand mock
        $this->refundQueryCommandMock = $this->getMockBuilder(RefundQueryCommand::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Instantiate the SaveCommand mock
        $this->saveCommandMock = $this->getMockBuilder(SaveCommand::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configMock = $this->getMockBuilder(ConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Instantiate the PaymentConfigInterface mock
        $this->paymentConfigMock = $this->createMock(PaymentConfigInterface::class);

        // Instantiate the CreditmemoRepository
        $this->creditmemoRepository = $this->objectManager->get(CreditmemoRepositoryInterface::class);

        // Instantiate the RefundCronjob with the mocked logger, RefundQueryCommand, and SaveCommand
        $this->refundCronjob = $this->objectManager->create(RefundCronjob::class, [
            'refundCollectionFactory' => $this->objectManager->get(\Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory::class),
            'creditmemoRepository' => $this->creditmemoRepository,
            'logger' => $this->logger,
            'refundQueryCommand' => $this->refundQueryCommandMock,
            'saveCommand' => $this->saveCommandMock,
            'paymentConfig' => $this->configMock,  // Pass the config mock here
        ]);
    }

    /**
     * Test the execution of the RefundCronjob.
     *
     * @return void
     */
    public function testExecute()
    {
        $this->refundCronjob->execute();
        $refundCollection = $this->refundCronjob->getUnprocessedRefunds();
        $this->assertEquals(0, $refundCollection->getSize());
    }

    /**
     * Test the getUnprocessedRefunds method.
     *
     * @return void
     */
    public function testGetUnprocessedRefunds()
    {
        // Additional setup if needed

        // Execute the getUnprocessedRefunds method
        $refundCollection = $this->refundCronjob->getUnprocessedRefunds();

        // Assertions
        foreach ($refundCollection as $refund) {
            /** @var RefundData $refund */
            $this->assertEquals(RefundInterface::NOT_PROCESSED, $refund->getIsProcessed());
        }
    }
}
