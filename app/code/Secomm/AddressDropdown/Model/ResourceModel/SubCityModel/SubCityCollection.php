<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\ResourceModel\SubCityModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityResource;
use Secomm\AddressDropdown\Model\SubCityModel;

class SubCityCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_city_sub_city_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(SubCityModel::class, SubCityResource::class);
    }
}
