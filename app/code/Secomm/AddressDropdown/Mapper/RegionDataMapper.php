<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Mapper;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterfaceFactory;
use Magento\Directory\Model\Region;

/**
 * Converts a collection of City entities to an array of data transfer objects.
 */
class RegionDataMapper
{
    /**
     * @var RegionInterfaceFactory
     */
    private RegionInterfaceFactory $entityDtoFactory;

    /**
     * @param RegionInterfaceFactory $entityDtoFactory
     */
    public function __construct(
        RegionInterfaceFactory $entityDtoFactory
    )
    {
        $this->entityDtoFactory = $entityDtoFactory;
    }

    /**
     * Map magento models to DTO array.
     *
     * @param AbstractCollection $collection
     *
     * @return array|RegionInterface[]
     */
    public function map(AbstractCollection $collection): array
    {
        $results = [];
        /** @var Region $item */
        foreach ($collection->getItems() as $item) {
            /** @var RegionInterface|DataObject $entityDto */
            $entityDto = $this->entityDtoFactory->create();
            $entityDto->addData($item->getData());

            $results[] = $entityDto;
        }

        return $results;
    }
}
