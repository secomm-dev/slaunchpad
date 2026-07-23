<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityNameResource;

class SubCityNameModel extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_city_sub_city_name_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(SubCityNameResource::class);
    }
}
