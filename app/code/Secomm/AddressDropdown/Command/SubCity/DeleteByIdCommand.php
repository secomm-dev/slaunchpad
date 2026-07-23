<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Command\SubCity;

use Exception;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityResource;
use Secomm\AddressDropdown\Model\SubCityModel;
use Secomm\AddressDropdown\Model\SubCityModelFactory;

/**
 * Delete SubCity by id Command.
 */
class DeleteByIdCommand
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var SubCityModelFactory
     */
    private SubCityModelFactory $modelFactory;

    /**
     * @var SubCityResource
     */
    private SubCityResource $resource;

    /**
     * @param LoggerInterface $logger
     * @param SubCityModelFactory $modelFactory
     * @param SubCityResource $resource
     */
    public function __construct(
        LoggerInterface     $logger,
        SubCityModelFactory $modelFactory,
        SubCityResource     $resource
    )
    {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
    }

    /**
     * Delete SubCity.
     *
     * @param int $entityId
     *
     * @return void
     * @throws CouldNotDeleteException
     */
    public function execute(int $entityId): void
    {
        try {
            /** @var SubCityModel $model */
            $model = $this->modelFactory->create();
            $this->resource->load($model, $entityId, SubCityInterface::SUB_CITY_ID);

            if (!$model->getData(SubCityInterface::SUB_CITY_ID)) {
                throw new NoSuchEntityException(
                    __('Could not find SubCity with id: `%id`',
                        [
                            'id' => $entityId
                        ]
                    )
                );
            }

            $this->resource->delete($model);
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not delete SubCity. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotDeleteException(__('Could not delete SubCity.'));
        }
    }
}
