<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\AddressDropdown\Model\ResourceModel\RegionModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Model\Region;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_country_region_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(Region::class, RegionModel::class);
    }
}
