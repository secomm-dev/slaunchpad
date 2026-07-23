<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\ResourceModel\CityModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Model\CityModel;
use Secomm\AddressDropdown\Model\ResourceModel\CityResource;

class CityCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_region_city_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(CityModel::class, CityResource::class);
    }
}
