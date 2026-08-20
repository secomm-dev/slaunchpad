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
use Magento\Framework\Exception\CouldNotSaveException;
use Psr\Log\LoggerInterface;

/**
 * Save Track Command.
 */
class SaveCommand
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
        TrackResource     $resource,
    )
    {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
    }

    /**
     * Save Track.
     *
     * @param TrackInterface $track
     *
     * @return int
     * @throws CouldNotSaveException
     */
    public function execute(TrackInterface $track): int
    {
        try {
            /** @var TrackModel $model */
            $model = $this->modelFactory->create();
            $model->addData($track->getData());
            $model->setHasDataChanges(true);

            if (!$model->getData(TrackInterface::ENTITY_ID)) {
                $model->isObjectNew(true);
            }
            $this->resource->save($model);
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not save Track. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotSaveException(__('Could not save Track.'));
        }

        return (int)$model->getData(TrackInterface::ENTITY_ID);
    }
}
