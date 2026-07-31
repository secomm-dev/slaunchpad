<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\GiaoHangNhanh\Model;

class Order extends \Magento\Sales\Model\Order
{
    /**
     * This Function is used to get order by tracking code
     *
     * @param string $trackingCode
     * @return $this
     */
    public function getOrderByTrackingCode($trackingCode)
    {
        return $this->loadByAttribute('tracking_code', $trackingCode);
    }
}
