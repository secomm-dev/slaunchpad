<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Plugin\Sales\Model;

use Magento\Sales\Model\Order as MageOrder;

/**
 * Class Order
 *
 * @package Secomm\GiaoHangNhanh\Plugin\Sales\Model
 */
class Order
{
    const DEFAULT_ORDER_STATUS = 'ready_to_pick';

    /**
     * @param MageOrder $subject
     * @param $result
     * @return bool
     */
    public function afterCanCancel(MageOrder $subject, $result)
    {
        $ghnStatus = $subject->getData('ghn_status');
        $trackingCode = $subject->getData('tracking_code');

        if ($ghnStatus && $trackingCode && $ghnStatus !== self::DEFAULT_ORDER_STATUS) {
            $result = false;
        }

        return $result;
    }
}
