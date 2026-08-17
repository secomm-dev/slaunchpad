<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\ResourceModel\AhamoveCityDetail;

use Secomm\Ahamove\Model\AhamoveCityDetail;
use Secomm\Ahamove\Model\ResourceModel\AhamoveCityDetail as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class AhamoveCityDetailCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Initialize collection model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(AhamoveCityDetail::class, ResourceModel::class);
    }
}
