<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\ResourceModel\CityNameModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Model\CityNameModel;
use Secomm\AddressDropdown\Model\ResourceModel\CityNameResource;

class CityNameCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_region_city_name_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(CityNameModel::class, CityNameResource::class);
    }
}
