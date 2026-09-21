<?php

namespace Secomm\VNPAY\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class VnpayRefund extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init('vn_pay_refund', 'entity_id');
    }
}
