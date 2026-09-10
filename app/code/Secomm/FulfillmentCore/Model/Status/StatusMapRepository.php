<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Secomm\FulfillmentCore\Api\Data\StatusMapInterface;
use Secomm\FulfillmentCore\Api\StatusMapRepositoryInterface;
use Secomm\FulfillmentCore\Model\FulfillmentStatusMap;
use Secomm\FulfillmentCore\Model\FulfillmentStatusMapFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap as StatusMapResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap\CollectionFactory;

/**
 * Persist Magento status ↔ POS status maps with UNIQUE conflict checks per service_code.
 */
class StatusMapRepository implements StatusMapRepositoryInterface
{
    public function __construct(
        private readonly FulfillmentStatusMapFactory $factory,
        private readonly StatusMapResource $resource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param int $entityId Map row id
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): StatusMapInterface
    {
        /** @var FulfillmentStatusMap $model */
        $model = $this->factory->create();
        $this->resource->load($model, $entityId);
        if (!$model->getEntityId()) {
            throw new NoSuchEntityException(
                new Phrase('Status map with id %1 does not exist.', [$entityId])
            );
        }

        return $model;
    }

    /**
     * @param StatusMapInterface $map Map to persist
     * @throws StatusMapConflictException
     * @throws LocalizedException
     */
    public function save(StatusMapInterface $map): StatusMapInterface
    {
        if ($map->getMagentoOrderStatus() === '' || $map->getExternalStatusCode() === '') {
            throw new LocalizedException(__('Magento order status and POS status code are required.'));
        }
        $this->assertNoConflict($map);

        if ($map instanceof FulfillmentStatusMap) {
            $this->resource->save($map);
            return $map;
        }

        /** @var FulfillmentStatusMap $model */
        $model = $map->getEntityId()
            ? $this->getById((int) $map->getEntityId())
            : $this->factory->create();

        $model->setServiceCode($map->getServiceCode());
        $model->setMagentoOrderStatus($map->getMagentoOrderStatus());
        $model->setExternalStatusCode($map->getExternalStatusCode());
        $model->setExternalStatusLabel($map->getExternalStatusLabel());
        $model->setIsActive($map->getIsActive());
        $this->resource->save($model);

        return $model;
    }

    /**
     * @param StatusMapInterface $map Existing map row
     */
    public function delete(StatusMapInterface $map): void
    {
        if (!$map instanceof FulfillmentStatusMap) {
            $map = $this->getById((int) $map->getEntityId());
        }
        $this->resource->delete($map);
    }

    /**
     * @param string $serviceCode Adapter service_code
     * @return StatusMapInterface[]
     */
    public function getListByService(string $serviceCode): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('service_code', $serviceCode);
        $collection->setOrder('external_status_code', 'ASC');

        $items = [];
        foreach ($collection as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @throws StatusMapConflictException
     */
    private function assertNoConflict(StatusMapInterface $map): void
    {
        $excludeId = $map->getEntityId();

        $byCode = $this->collectionFactory->create();
        $byCode->addFieldToFilter('service_code', $map->getServiceCode());
        $byCode->addFieldToFilter('external_status_code', $map->getExternalStatusCode());
        if ($excludeId) {
            $byCode->addFieldToFilter('entity_id', ['neq' => $excludeId]);
        }
        if ($byCode->getSize() > 0) {
            throw new StatusMapConflictException(
                __('POS status code already mapped for this service.')
            );
        }
    }
}
