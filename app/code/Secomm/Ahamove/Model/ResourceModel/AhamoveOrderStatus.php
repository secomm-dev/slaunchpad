<?php
/**
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AhamoveOrderStatus extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ahamove_order_status_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('ahamove_order_status', 'entity_id');
        $this->_useIsObjectNew = true;
    }
}
