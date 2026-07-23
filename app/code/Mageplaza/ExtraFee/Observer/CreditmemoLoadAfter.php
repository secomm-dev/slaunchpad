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

namespace Mageplaza\ExtraFee\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;

/**
 * Class CreditmemoLoadAfter
 * @package Mageplaza\ExtraFee\Observer
 */
class CreditmemoLoadAfter implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * CreditmemoLoadAfter constructor.
     *
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param Observer $observer
     *
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();

        $order      = $creditmemo->getOrder();
        $isExtraFee = false;
        $isShipping = false;
        $isBilling  = false;
        if ($this->helper->isRefunded($order) === $creditmemo->getId()) {
            $extraFee = $this->helper->getObjectExtraFeeTotals($creditmemo, $order);
            foreach ($extraFee as $fee) {
                if ($fee['rf'] === '1') {
                    continue;
                }
                switch ((int) $fee['display_area']) {
                    case DisplayArea::PAYMENT_METHOD:
                        $isBilling = true;
                        break;
                    case DisplayArea::SHIPPING_METHOD:
                        $isShipping = true;
                        break;
                    case DisplayArea::CART_SUMMARY:
                        $isExtraFee = true;
                        break;
                }
            }
        }
        $creditmemo->setHasBillingExtraFee($isBilling);
        $creditmemo->setHasShippingExtraFee($isShipping);
        $creditmemo->setHasExtraFee($isExtraFee);
    }
}
