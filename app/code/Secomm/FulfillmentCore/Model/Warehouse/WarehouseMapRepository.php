<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Warehouse;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Secomm\FulfillmentCore\Api\Data\WarehouseMapInterface;
use Secomm\FulfillmentCore\Api\WarehouseMapRepositoryInterface;
use Secomm\FulfillmentCore\Model\FulfillmentWarehouseMap;
use Secomm\FulfillmentCore\Model\FulfillmentWarehouseMapFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap as WarehouseMapResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap\CollectionFactory;

/**
 * Persist warehouse maps with UNIQUE conflict checks per service_code.
 */
class WarehouseMapRepository implements WarehouseMapRepositoryInterface
{
    public function __construct(
        private readonly FulfillmentWarehouseMapFactory $factory,
        private readonly WarehouseMapResource $resource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param int $entityId Map row id
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): WarehouseMapInterface
    {
        /** @var FulfillmentWarehouseMap $model */
        $model = $this->factory->create();
        $this->resource->load($model, $entityId);
        if (!$model->getEntityId()) {
            throw new NoSuchEntityException(
                new Phrase('Warehouse map with id %1 does not exist.', [$entityId])
            );
        }

        return $model;
    }

    /**
     * @param WarehouseMapInterface $map Map to persist
     * @throws WarehouseMapConflictException
     */
    public function save(WarehouseMapInterface $map): WarehouseMapInterface
    {
        $this->assertNoConflict($map);

        if ($map instanceof FulfillmentWarehouseMap) {
            $this->resource->save($map);
            return $map;
        }

        /** @var FulfillmentWarehouseMap $model */
        $model = $map->getEntityId()
            ? $this->getById((int) $map->getEntityId())
            : $this->factory->create();

        $model->setServiceCode($map->getServiceCode());
        $model->setMagentoSourceCode($map->getMagentoSourceCode());
        $model->setExternalWarehouseId($map->getExternalWarehouseId());
        $model->setExternalWarehouseLabel($map->getExternalWarehouseLabel());
        $model->setIsActive($map->getIsActive());
        $this->resource->save($model);

        return $model;
    }

    /**
     * @param WarehouseMapInterface $map Existing map row
     */
    public function delete(WarehouseMapInterface $map): void
    {
        if (!$map instanceof FulfillmentWarehouseMap) {
            $map = $this->getById((int) $map->getEntityId());
        }
        $this->resource->delete($map);
    }

    /**
     * @param string $serviceCode Adapter service_code
     * @return WarehouseMapInterface[]
     */
    public function getListByService(string $serviceCode): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('service_code', $serviceCode);
        $collection->setOrder('magento_source_code', 'ASC');

        $items = [];
        foreach ($collection as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @throws WarehouseMapConflictException
     */
    private function assertNoConflict(WarehouseMapInterface $map): void
    {
        $excludeId = $map->getEntityId();

        $bySource = $this->collectionFactory->create();
        $bySource->addFieldToFilter('service_code', $map->getServiceCode());
        $bySource->addFieldToFilter('magento_source_code', $map->getMagentoSourceCode());
        if ($excludeId) {
            $bySource->addFieldToFilter('entity_id', ['neq' => $excludeId]);
        }
        if ($bySource->getSize() > 0) {
            throw new WarehouseMapConflictException(
                __('MSI source already mapped for this service.')
            );
        }

        $byWarehouse = $this->collectionFactory->create();
        $byWarehouse->addFieldToFilter('service_code', $map->getServiceCode());
        $byWarehouse->addFieldToFilter('external_warehouse_id', $map->getExternalWarehouseId());
        if ($excludeId) {
            $byWarehouse->addFieldToFilter('entity_id', ['neq' => $excludeId]);
        }
        if ($byWarehouse->getSize() > 0) {
            throw new WarehouseMapConflictException(
                __('External warehouse already mapped for this service.')
            );
        }
    }
}
