<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Command\Region;

use Exception;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Model\CanonicalDataGuard;
use Secomm\AddressDropdown\Model\Region;
use Secomm\AddressDropdown\Model\RegionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel as RegionModelResourceModel;

/**
 * Delete Region by id Command.
 */
class DeleteByIdCommand
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var RegionFactory
     */
    private RegionFactory $modelFactory;

    /**
     * @var RegionModelResourceModel
     */
    private RegionModelResourceModel $resource;

    /**
     * @param LoggerInterface $logger
     * @param RegionFactory $modelFactory
     * @param RegionModelResourceModel $resource
     */
    public function __construct(
        LoggerInterface          $logger,
        RegionFactory            $modelFactory,
        RegionModelResourceModel $resource,
        private readonly CanonicalDataGuard $canonicalDataGuard
    )
    {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
    }

    /**
     * Delete Region.
     *
     * @param int $entityId
     *
     * @return void
     * @throws CouldNotDeleteException
     */
    public function execute(int $entityId): void
    {
        try {
            /** @var Region $model */
            $model = $this->modelFactory->create();
            $this->resource->load($model, $entityId, RegionInterface::REGION_ID);

            if (!$model->getData(RegionInterface::REGION_ID)) {
                throw new NoSuchEntityException(
                    __('Could not find Region with id: `%id`',
                        [
                            'id' => $entityId
                        ]
                    )
                );
            }

            if ($model->getData(RegionInterface::IS_DEFAULT)) {
                throw new NoSuchEntityException(
                    __('Could not delete Region with id: `%id`',
                        [
                            'id' => $entityId
                        ]
                    )
                );
            }

            // TASK-SEC-A4: canonical VN rows are import-workflow-only. Thrown as a
            // CouldNotDeleteException so the explicit reason reaches the admin UI verbatim.
            if ((string) $model->getData('country_id') === 'VN') {
                $this->canonicalDataGuard->assertRegionMutatable($entityId, null);
            }

            $this->resource->delete($model);
        } catch (CouldNotDeleteException $guardedException) {
            throw $guardedException;
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not delete Region. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotDeleteException(__('Could not delete Region.'));
        }
    }
}
