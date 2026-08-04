<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model\ResourceModel\RefundModel;

use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\ResourceModel\RefundResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class RefundCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'zalo_pay_refund_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(RefundModel::class, RefundResource::class);
    }
}
