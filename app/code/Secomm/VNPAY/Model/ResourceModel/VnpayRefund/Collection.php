<?php

namespace Secomm\VNPAY\Model\ResourceModel\VnpayRefund;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\VNPAY\Model\VnpayRefund;
use Secomm\VNPAY\Model\ResourceModel\VnpayRefund as VnpayRefundResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(VnpayRefund::class, VnpayRefundResource::class);
    }
}
