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

namespace Mageplaza\ExtraFee\Plugin\Sales\Model;

use Magento\Sales\Model\Order;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class CanCreditmemo
 * @package Mageplaza\ExtraFee\Plugin\Sales\Model
 */
class CanCreditmemo
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * CanCreditmemo constructor.
     *
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param Order $subject
     */
    public function beforeCanCreditmemo(Order $subject)
    {
        $extraFeeTotals = $this->helper->getExtraFeeTotals($subject);

        $isRefundAllExtraFee = true;
        foreach ($extraFeeTotals as $fee) {
            if ($fee['rf'] !== '1') {
                $isRefundAllExtraFee = false;
                break;
            }
        }

        if (!$isRefundAllExtraFee && !$this->validateQty($subject) &&
            in_array($subject->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED], true)
        ) {
            $subject->setForcedCanCreditmemo(false);
        }
    }

    /**
     * @param Order $subject
     *
     * @return bool
     */
    public function validateQty($subject)
    {
        foreach ($subject->getItems() as $item) {
            if ($item->getQtyRefunded() < $item->getQtyOrdered()) {
                return true;
            }
        }

        return false;
    }
}
