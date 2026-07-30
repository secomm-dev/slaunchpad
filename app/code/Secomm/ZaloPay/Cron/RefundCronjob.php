<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger as LoggerInterface;
use Secomm\ZaloPay\Model\Data\RefundData;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;

/**
 * RefundCronjob Class
 *
 * This class represents the cron job responsible for processing refunds where the 'is_processed' field is set to false.
 *
 */
class RefundCronjob
{
    /**
     * @var RefundCollectionFactory
     */
    private RefundCollectionFactory $refundCollectionFactory;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private CreditmemoRepositoryInterface $creditmemoRepository;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var RefundQueryCommand
     */
    private RefundQueryCommand $refundQueryCommand;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var Authorization
     */
    private Authorization $authorization;

    /**
     * RefundCronjob constructor.
     *
     * @param RefundCollectionFactory $refundCollectionFactory
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param LoggerInterface $logger
     * @param RefundQueryCommand $refundQueryCommand
     * @param DateTime $dateTime
     * @param Authorization $authorization
     */
    public function __construct(
        RefundCollectionFactory       $refundCollectionFactory,
        CreditmemoRepositoryInterface $creditmemoRepository,
        LoggerInterface               $logger,
        RefundQueryCommand            $refundQueryCommand,
        DateTime                      $dateTime,
        Authorization                 $authorization
    ) {
        $this->refundCollectionFactory = $refundCollectionFactory;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->logger = $logger;
        $this->refundQueryCommand = $refundQueryCommand;
        $this->dateTime = $dateTime;
        $this->authorization = $authorization;
    }

    /**
     * Execute Refund Cron Job
     *
     * This method is triggered by the cron scheduler to process refunds with 'is_processed' set to false.
     */
    public function execute()
    {
        try {
            $this->logger->info('Cron Start');
            // Get a collection of refunds where 'is_processed' is false
            $refundCollection = $this->getUnprocessedRefunds();
            // Process the result as needed
            foreach ($refundCollection as $refund) {
                /** @var RefundData $refund */
                $creditMemoId = $refund->getCreditMemoId();
                $creditMemo = $this->creditmemoRepository->get($creditMemoId);
                $timestamp = $this->dateTime->timestamp() * 1000;
                $commandSubject = json_decode($refund->getAdditionalInformation(), true);
                $commandSubject[AbstractDataBuilder::TIMESTAMP] = $timestamp;
                //Remove old Mac
                unset($commandSubject[AbstractDataBuilder::MAC]);
                $commandSubject[AbstractDataBuilder::MAC] = $this->authorization->getMac($commandSubject);
                $response = $this->refundQueryCommand->getRefundQuery($commandSubject);

                $statusCode = $response[AbstractResponseValidator::RETURN_CODE];
                if ($statusCode === AbstractResponseValidator::RETURN_CODE_ACCEPT
                ) {
                    $creditMemo->setState(\Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED);
                    $this->creditmemoRepository->save($creditMemo);
                    // Set is_processed to true
                    $refund->setIsProcessed(true);
                    $refund->save();
                } else {
                    continue;
                }
                // Log the processing
                $this->logger->info('Refund processed for credit memo ID ' . $creditMemoId);
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * Get unprocessed refund collection
     *
     * @return RefundCollection
     */
    public function getUnprocessedRefunds(): RefundCollection
    {
        // Get a collection of refunds where 'is_processed' is false
        $refundCollection = $this->refundCollectionFactory->create();
        $refundCollection->addFieldToFilter('is_processed', ['eq' => RefundInterface::NOT_PROCESSED]);
        return $refundCollection;
    }
}
