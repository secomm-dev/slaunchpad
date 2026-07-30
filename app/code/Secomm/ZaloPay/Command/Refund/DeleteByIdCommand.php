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
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Delete Refund by id Command.
 */
class DeleteByIdCommand
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
     * Delete Refund.
     *
     * @param int $entityId
     *
     * @return void
     * @throws CouldNotDeleteException
     */
    public function execute(int $entityId): void
    {
        try {
            /** @var RefundModel $model */
            $model = $this->modelFactory->create();
            $this->resource->load($model, $entityId, RefundInterface::ENTITY_ID);

            if (!$model->getData(RefundInterface::ENTITY_ID)) {
                throw new NoSuchEntityException(
                    __(
                        'Could not find Refund with id: `%id`',
                        [
                            'id' => $entityId
                        ]
                    )
                );
            }

            $this->resource->delete($model);
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not delete Refund. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotDeleteException(__('Could not delete Refund.'));
        }
    }
}
