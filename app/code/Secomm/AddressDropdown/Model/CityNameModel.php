<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\AddressDropdown\Model\ResourceModel\CityNameResource;

class CityNameModel extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_region_city_name_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(CityNameResource::class);
    }
}
