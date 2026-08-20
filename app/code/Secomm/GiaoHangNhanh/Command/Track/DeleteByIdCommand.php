<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\GiaoHangNhanh\Command\Track;

use Secomm\GiaoHangNhanh\Api\Data\TrackInterface;
use Secomm\GiaoHangNhanh\Model\ResourceModel\TrackResource;
use Secomm\GiaoHangNhanh\Model\TrackModel;
use Secomm\GiaoHangNhanh\Model\TrackModelFactory;
use Exception;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Delete Track by id Command.
 */
class DeleteByIdCommand
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var TrackModelFactory
     */
    private TrackModelFactory $modelFactory;

    /**
     * @var TrackResource
     */
    private TrackResource $resource;

    /**
     * @param LoggerInterface $logger
     * @param TrackModelFactory $modelFactory
     * @param TrackResource $resource
     */
    public function __construct(
        LoggerInterface   $logger,
        TrackModelFactory $modelFactory,
        TrackResource     $resource
    )
    {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
    }

    /**
     * Delete Track.
     *
     * @param int $entityId
     *
     * @return void
     * @throws CouldNotDeleteException
     */
    public function execute(int $entityId): void
    {
        try {
            /** @var TrackModel $model */
            $model = $this->modelFactory->create();
            $this->resource->load($model, $entityId, TrackInterface::ENTITY_ID);

            if (!$model->getData(TrackInterface::ENTITY_ID)) {
                throw new NoSuchEntityException(
                    __('Could not find Track with id: `%id`',
                        [
                            'id' => $entityId
                        ]
                    )
                );
            }

            $this->resource->delete($model);
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not delete Track. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotDeleteException(__('Could not delete Track.'));
        }
    }
}
