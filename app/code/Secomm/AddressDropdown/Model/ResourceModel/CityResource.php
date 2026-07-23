<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Secomm\AddressDropdown\Api\Data\CityInterface;

class CityResource extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_region_city_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('directory_region_city', CityInterface::CITY_ID);
        $this->_useIsObjectNew = true;
    }
}
