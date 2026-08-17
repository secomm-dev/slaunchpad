<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\Ghtk\Api\Data\GhtkAddressMapInterface;
use Secomm\Ghtk\Model\GhtkAddressMapFactory;
use Secomm\Ghtk\Model\ResourceModel\GhtkAddressMap as GhtkAddressMapResource;
use Secomm\Ghtk\Model\ResourceModel\GhtkAddressMap\CollectionFactory;

class GhtkAddressMapRepository
{
    public function __construct(
        private GhtkAddressMapFactory $mapFactory,
        private GhtkAddressMapResource $resource,
        private CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $mapId): GhtkAddressMapInterface
    {
        $map = $this->mapFactory->create();
        $this->resource->load($map, $mapId);
        if (!$map->getId()) {
            throw new NoSuchEntityException(__('GHTK address map with id "%1" not found.', $mapId));
        }

        return $map;
    }

    /**
     * Lookup by the canonical key, active rows only. Returns null on miss.
     */
    public function findActive(string $countryId, int $regionId, int $wardId): ?GhtkAddressMapInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(GhtkAddressMapInterface::COUNTRY_ID, $countryId);
        $collection->addFieldToFilter(GhtkAddressMapInterface::REGION_ID, $regionId);
        $collection->addFieldToFilter(GhtkAddressMapInterface::WARD_ID, $wardId);
        $collection->addFieldToFilter(GhtkAddressMapInterface::IS_ACTIVE, 1);
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }

    /**
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Exception
     */
    public function save(GhtkAddressMapInterface $map): GhtkAddressMapInterface
    {
        $this->resource->save($map);

        return $map;
    }

    /**
     * @throws \Exception
     */
    public function delete(GhtkAddressMapInterface $map): bool
    {
        $this->resource->delete($map);

        return true;
    }
}
