<?php
/**
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model\ResourceModel\AhamoveOrderStatus;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\Ahamove\Model\AhamoveOrderStatus as Model;
use Secomm\Ahamove\Model\ResourceModel\AhamoveOrderStatus as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ahamove_order_status_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
