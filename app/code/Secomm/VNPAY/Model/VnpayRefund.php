<?php

namespace Secomm\VNPAY\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * VNPAY refund record — one row per online refund request.
 *
 * Tracks the async refund lifecycle (vnp_TransactionStatus 05 processing →
 * 06 sent to bank / 09 rejected) and prevents duplicate refund requests.
 */
class VnpayRefund extends AbstractModel
{
    /**
     * Refund types (vnp_TransactionType)
     */
    public const TYPE_FULL = '02';
    public const TYPE_PARTIAL = '03';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Secomm\VNPAY\Model\ResourceModel\VnpayRefund::class);
    }
}
