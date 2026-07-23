<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Mapper;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\CityInterfaceFactory;
use Secomm\AddressDropdown\Model\CityModel;

/**
 * Converts a collection of City entities to an array of data transfer objects.
 */
class CityDataMapper
{
    /**
     * @var CityInterfaceFactory
     */
    private CityInterfaceFactory $entityDtoFactory;

    /**
     * @param CityInterfaceFactory $entityDtoFactory
     */
    public function __construct(
        CityInterfaceFactory $entityDtoFactory
    )
    {
        $this->entityDtoFactory = $entityDtoFactory;
    }

    /**
     * Map magento models to DTO array.
     *
     * @param AbstractCollection $collection
     *
     * @return array|CityInterface[]
     */
    public function map(AbstractCollection $collection): array
    {
        $results = [];
        /** @var CityModel $item */
        foreach ($collection->getItems() as $item) {
            /** @var CityInterface|DataObject $entityDto */
            $entityDto = $this->entityDtoFactory->create();
            $entityDto->addData($item->getData());

            $results[] = $entityDto;
        }

        return $results;
    }
}
