<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Block\Sales\Order\Shipment\Create;

use Magento\Sales\Model\Order;
use Mageplaza\ExtraFee\Block\Sales\Order\AbstractExtraFee;

/**
 * Class ExtraFeeUpdateQty
 * @package Mageplaza\ExtraFee\Block\Sales\Order\Shipment\Create
 */
class ExtraFeeUpdateQty extends AbstractExtraFee
{
    /**
     * @return mixed
     */
    protected function getOrder()
    {
        if (!$this->order) {
            $this->order = $this->getShipmentOrder();
        }

        return $this->order;
    }

    /**
     * @param string $area
     *
     * @return array
     */
    public function getExtraFeeInfo($area)
    {
        /** @var Order $order */
        $order = $this->getOrder();
        $order->getBaseCurrency();
        $shipment       = $this->getShipment();
        $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($shipment, $order);
        $result         = [];
        foreach ($extraFeeTotals as $fee) {
            if ($fee['display_area'] === $area) {
                $result[] = $fee;
            }
        }

        return $result;
    }
}
