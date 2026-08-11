<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping;

use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping as LocationMappingResource;
use Secomm\GhnAddressMapper\Model\Data\LocationMappingData;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            LocationMappingData::class,
            LocationMappingResource::class
        );
    }
}
