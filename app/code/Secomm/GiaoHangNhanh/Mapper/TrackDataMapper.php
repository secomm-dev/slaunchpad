<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\GiaoHangNhanh\Mapper;

use Secomm\GiaoHangNhanh\Api\Data\TrackInterface;
use Secomm\GiaoHangNhanh\Api\Data\TrackInterfaceFactory;
use Secomm\GiaoHangNhanh\Model\TrackModel;
use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Converts a collection of Track entities to an array of data transfer objects.
 */
class TrackDataMapper
{
    /**
     * @var TrackInterfaceFactory
     */
    private TrackInterfaceFactory $entityDtoFactory;

    /**
     * @param TrackInterfaceFactory $entityDtoFactory
     */
    public function __construct(
        TrackInterfaceFactory $entityDtoFactory
    )
    {
        $this->entityDtoFactory = $entityDtoFactory;
    }

    /**
     * Map magento models to DTO array.
     *
     * @param AbstractCollection $collection
     *
     * @return array|TrackInterface[]
     */
    public function map(AbstractCollection $collection): array
    {
        $results = [];
        /** @var TrackModel $item */
        foreach ($collection->getItems() as $item) {
            /** @var TrackInterface|DataObject $entityDto */
            $entityDto = $this->entityDtoFactory->create();
            $entityDto->addData($item->getData());

            $results[] = $entityDto;
        }

        return $results;
    }
}
