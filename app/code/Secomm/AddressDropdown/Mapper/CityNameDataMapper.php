<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Mapper;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Api\Data\CityNameInterface;
use Secomm\AddressDropdown\Api\Data\CityNameInterfaceFactory;
use Secomm\AddressDropdown\Model\CityNameModel;

/**
 * Converts a collection of CityName entities to an array of data transfer objects.
 */
class CityNameDataMapper
{
    /**
     * @var CityNameInterfaceFactory
     */
    private CityNameInterfaceFactory $entityDtoFactory;

    /**
     * @param CityNameInterfaceFactory $entityDtoFactory
     */
    public function __construct(
        CityNameInterfaceFactory $entityDtoFactory
    )
    {
        $this->entityDtoFactory = $entityDtoFactory;
    }

    /**
     * Map magento models to DTO array.
     *
     * @param AbstractCollection $collection
     *
     * @return array|CityNameInterface[]
     */
    public function map(AbstractCollection $collection): array
    {
        $results = [];
        /** @var CityNameModel $item */
        foreach ($collection->getItems() as $item) {
            /** @var CityNameInterface|DataObject $entityDto */
            $entityDto = $this->entityDtoFactory->create();
            $entityDto->addData($item->getData());

            $results[] = $entityDto;
        }

        return $results;
    }
}
