<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\ResourceModel\SubCityNameModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityNameResource;
use Secomm\AddressDropdown\Model\SubCityNameModel;

class SubCityNameCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_city_sub_city_name_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(SubCityNameModel::class, SubCityNameResource::class);
    }
}
