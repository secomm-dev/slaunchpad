<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\Ghtk\Api\Data\GhtkAddressOverrideInterface;
use Secomm\Ghtk\Model\GhtkAddressOverrideFactory;
use Secomm\Ghtk\Model\ResourceModel\GhtkAddressOverride as GhtkAddressOverrideResource;
use Secomm\Ghtk\Model\ResourceModel\GhtkAddressOverride\CollectionFactory;

/**
 * Read/write access to the GHTK address OVERRIDE table (DEC-TASK7AJ3K8-002).
 * Lookup is by CANONICAL identity (scheme_code, province_code, ward_code) — active rows only;
 * null on miss (a miss is the NORMAL path: canonical name_vi is the default representation).
 */
class GhtkAddressOverrideRepository
{
    public function __construct(
        private GhtkAddressOverrideFactory $overrideFactory,
        private GhtkAddressOverrideResource $resource,
        private CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $mapId): GhtkAddressOverrideInterface
    {
        $override = $this->overrideFactory->create();
        $this->resource->load($override, $mapId);
        if (!$override->getId()) {
            throw new NoSuchEntityException(__('GHTK address override with id "%1" not found.', $mapId));
        }

        return $override;
    }

    /**
     * Optional override lookup by canonical identity. Returns null on miss — callers fall
     * back to canonical name_vi (the default), never treat a miss as an error.
     */
    public function findActive(string $schemeCode, string $provinceCode, string $wardCode): ?GhtkAddressOverrideInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(GhtkAddressOverrideInterface::SCHEME_CODE, $schemeCode);
        $collection->addFieldToFilter(GhtkAddressOverrideInterface::PROVINCE_CODE, $provinceCode);
        $collection->addFieldToFilter(GhtkAddressOverrideInterface::WARD_CODE, $wardCode);
        $collection->addFieldToFilter(GhtkAddressOverrideInterface::IS_ACTIVE, 1);
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }

    /**
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Exception
     */
    public function save(GhtkAddressOverrideInterface $override): GhtkAddressOverrideInterface
    {
        $this->resource->save($override);

        return $override;
    }

    /**
     * @throws \Exception
     */
    public function delete(GhtkAddressOverrideInterface $override): bool
    {
        $this->resource->delete($override);

        return true;
    }
}
