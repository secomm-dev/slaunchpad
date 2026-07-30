<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Mapper;

use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Api\Data\RefundInterfaceFactory;
use Secomm\ZaloPay\Model\RefundModel;
use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Converts a collection of Refund entities to an array of data transfer objects.
 */
class RefundDataMapper
{
    /**
     * @var RefundInterfaceFactory
     */
    private RefundInterfaceFactory $entityDtoFactory;

    /**
     * @param RefundInterfaceFactory $entityDtoFactory
     */
    public function __construct(
        RefundInterfaceFactory $entityDtoFactory
    ) {
        $this->entityDtoFactory = $entityDtoFactory;
    }

    /**
     * Map magento models to DTO array.
     *
     * @param AbstractCollection $collection
     *
     * @return array|RefundInterface[]
     */
    public function map(AbstractCollection $collection): array
    {
        $results = [];
        /** @var RefundModel $item */
        foreach ($collection->getItems() as $item) {
            /** @var RefundInterface|DataObject $entityDto */
            $entityDto = $this->entityDtoFactory->create();
            $entityDto->addData($item->getData());

            $results[] = $entityDto;
        }

        return $results;
    }
}
