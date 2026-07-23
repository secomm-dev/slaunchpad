<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Mapper;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Api\Data\SubCityNameInterface;
use Secomm\AddressDropdown\Api\Data\SubCityNameInterfaceFactory;
use Secomm\AddressDropdown\Model\SubCityNameModel;

/**
 * Converts a collection of SubCityName entities to an array of data transfer objects.
 */
class SubCityNameDataMapper
{
    /**
     * @var SubCityNameInterfaceFactory
     */
    private SubCityNameInterfaceFactory $entityDtoFactory;

    /**
     * @param SubCityNameInterfaceFactory $entityDtoFactory
     */
    public function __construct(
        SubCityNameInterfaceFactory $entityDtoFactory
    )
    {
        $this->entityDtoFactory = $entityDtoFactory;
    }

    /**
     * Map magento models to DTO array.
     *
     * @param AbstractCollection $collection
     *
     * @return array|SubCityNameInterface[]
     */
    public function map(AbstractCollection $collection): array
    {
        $results = [];
        /** @var SubCityNameModel $item */
        foreach ($collection->getItems() as $item) {
            /** @var SubCityNameInterface|DataObject $entityDto */
            $entityDto = $this->entityDtoFactory->create();
            $entityDto->addData($item->getData());

            $results[] = $entityDto;
        }

        return $results;
    }
}
