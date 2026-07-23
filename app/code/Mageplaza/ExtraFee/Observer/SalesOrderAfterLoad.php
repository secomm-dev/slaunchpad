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
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;

/**
 * Class SalesOrderAfterLoad
 * @package Mageplaza\ExtraFee\Observer
 */
class SalesOrderAfterLoad implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * SalesOrderAfterLoad constructor.
     *
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * After load observer for order
     *
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        $order      = $observer->getEvent()->getOrder();
        $isExtraFee = false;
        $isShipping = false;
        $isBilling  = false;
        $extraFee   = $this->helper->getExtraFeeTotals($order);
        foreach ($extraFee as $fee) {
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
        $order->setHasBillingExtraFee($isBilling);
        $order->setHasShippingExtraFee($isShipping);
        $order->setHasExtraFee($isExtraFee);

        return $this;
    }
}
