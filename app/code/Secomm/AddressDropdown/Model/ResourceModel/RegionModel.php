<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\AddressDropdown\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class RegionModel extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_country_region_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('directory_country_region', 'region_id');
        $this->_useIsObjectNew = true;
    }
}
