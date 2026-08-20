<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AhamoveCityDetail extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ahamove_city_detail_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('ahamove_city_detail', 'entity_id');
        $this->_useIsObjectNew = true;
    }
}
