<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\AddressDropdown\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel;

class Region extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_country_region_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(RegionModel::class);
    }
}
