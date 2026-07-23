<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Mapper;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Api\Data\SubCityInterfaceFactory;
use Secomm\AddressDropdown\Model\SubCityModel;

/**
 * Converts a collection of SubCity entities to an array of data transfer objects.
 */
class SubCityDataMapper
{
    /**
     * @var SubCityInterfaceFactory
     */
    private SubCityInterfaceFactory $entityDtoFactory;

    /**
     * @param SubCityInterfaceFactory $entityDtoFactory
     */
    public function __construct(
        SubCityInterfaceFactory $entityDtoFactory
    )
    {
        $this->entityDtoFactory = $entityDtoFactory;
    }

    /**
     * Map magento models to DTO array.
     *
     * @param AbstractCollection $collection
     *
     * @return array|SubCityInterface[]
     */
    public function map(AbstractCollection $collection): array
    {
        $results = [];
        /** @var SubCityModel $item */
        foreach ($collection->getItems() as $item) {
            /** @var SubCityInterface|DataObject $entityDto */
            $entityDto = $this->entityDtoFactory->create();
            $entityDto->addData($item->getData());

            $results[] = $entityDto;
        }

        return $results;
    }
}
