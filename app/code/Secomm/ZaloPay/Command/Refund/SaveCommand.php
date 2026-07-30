<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Command\Refund;

use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\RefundModelFactory;
use Secomm\ZaloPay\Model\ResourceModel\RefundResource;
use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Psr\Log\LoggerInterface;

/**
 * Save Refund Command.
 */
class SaveCommand
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var RefundModelFactory
     */
    private RefundModelFactory $modelFactory;

    /**
     * @var RefundResource
     */
    private RefundResource $resource;

    /**
     * @param LoggerInterface $logger
     * @param RefundModelFactory $modelFactory
     * @param RefundResource $resource
     */
    public function __construct(
        LoggerInterface    $logger,
        RefundModelFactory $modelFactory,
        RefundResource     $resource
    ) {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
    }

    /**
     * Save Refund.
     *
     * @param RefundInterface $refund
     *
     * @return int
     * @throws CouldNotSaveException
     */
    public function execute(RefundInterface $refund): int
    {
        try {
            /** @var RefundModel $model */
            $model = $this->modelFactory->create();
            $model->addData($refund->getData());
            $model->setHasDataChanges(true);

            if (!$model->getData(RefundInterface::ENTITY_ID)) {
                $model->isObjectNew(true);
            }
            $this->resource->save($model);
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not save Refund. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotSaveException(__('Could not save Refund.'));
        }

        return (int)$model->getData(RefundInterface::ENTITY_ID);
    }
}
