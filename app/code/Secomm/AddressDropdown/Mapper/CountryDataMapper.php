<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Mapper;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Api\Data\CountryInterface;
use Secomm\AddressDropdown\Api\Data\CountryInterfaceFactory;
use Magento\Directory\Model\Country;

/**
 * Converts a collection of City entities to an array of data transfer objects.
 */
class CountryDataMapper
{
    /**
     * @var CountryInterfaceFactory
     */
    private CountryInterfaceFactory $entityDtoFactory;

    /**
     * @param CountryInterfaceFactory $entityDtoFactory
     */
    public function __construct(
        CountryInterfaceFactory $entityDtoFactory
    )
    {
        $this->entityDtoFactory = $entityDtoFactory;
    }

    /**
     * Map magento models to DTO array.
     *
     * @param AbstractCollection $collection
     *
     * @return array|CountryInterface[]
     */
    public function map(AbstractCollection $collection): array
    {
        $results = [];
        /** @var Country $item */
        foreach ($collection->getItems() as $item) {
            /** @var CountryInterface|DataObject $entityDto */
            $entityDto = $this->entityDtoFactory->create();
            $entityDto->addData($item->getData());

            $results[] = $entityDto;
        }

        return $results;
    }
}
