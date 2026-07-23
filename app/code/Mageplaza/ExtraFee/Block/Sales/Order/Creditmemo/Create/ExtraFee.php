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

namespace Mageplaza\ExtraFee\Block\Sales\Order\Creditmemo\Create;

use Magento\Sales\Model\Order;
use Mageplaza\ExtraFee\Block\Sales\Order\AbstractExtraFee;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Block\Sales\Order\Creditmemo\Create
 */
class ExtraFee extends AbstractExtraFee
{
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
        $extraFeeTotals = $this->helper->getExtraFeeTotals($order);
        $result         = [];
        if (($this->registry->registry('current_creditmemo')
            && $this->registry->registry('current_creditmemo')->getItems())) {
            $creditmemo     = $this->registry->registry('current_creditmemo');
            $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($creditmemo, $order);
        }
        foreach ($extraFeeTotals as $fee) {
            if ((int) $fee['display_area'] === $area && $fee['rf'] === '1') {
                $result[] = $fee;
            }
        }

        return $result;
    }

    /**
     * @return mixed
     */
    protected function getOrder()
    {
        if (!$this->order) {
            $this->order = $this->registry->registry('current_creditmemo')->getOrder();
        }

        return $this->order;
    }
}
