<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Command\City;

use Exception;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Model\CityModel;
use Secomm\AddressDropdown\Model\CityModelFactory;
use Secomm\AddressDropdown\Model\ResourceModel\CityResource;

/**
 * Delete City by id Command.
 */
class DeleteByIdCommand
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var CityModelFactory
     */
    private CityModelFactory $modelFactory;

    /**
     * @var CityResource
     */
    private CityResource $resource;

    /**
     * @param LoggerInterface $logger
     * @param CityModelFactory $modelFactory
     * @param CityResource $resource
     */
    public function __construct(
        LoggerInterface  $logger,
        CityModelFactory $modelFactory,
        CityResource     $resource
    )
    {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
    }

    /**
     * Delete City.
     *
     * @param int $entityId
     *
     * @return void
     * @throws CouldNotDeleteException
     */
    public function execute(int $entityId): void
    {
        try {
            /** @var CityModel $model */
            $model = $this->modelFactory->create();
            $this->resource->load($model, $entityId, CityInterface::CITY_ID);

            if (!$model->getData(CityInterface::CITY_ID)) {
                throw new NoSuchEntityException(
                    __('Could not find City with id: `%id`',
                        [
                            'id' => $entityId
                        ]
                    )
                );
            }

            $this->resource->delete($model);
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not delete City. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotDeleteException(__('Could not delete City.'));
        }
    }
}
