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
use Magento\Sales\Model\Order\Invoice;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;

/**
 * Class InvoiceLoadAfter
 * @package Mageplaza\ExtraFee\Observer
 */
class InvoiceLoadAfter implements ObserverInterface
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
        /** @var Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();

        $order      = $invoice->getOrder();
        $isExtraFee = false;
        $isShipping = false;
        $isBilling  = false;
        if ($this->helper->isInvoiced($order) === $invoice->getId()) {
            $extraFee = $this->helper->getObjectExtraFeeTotals($invoice, $order);
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
        }
        $invoice->setHasBillingExtraFee($isBilling);
        $invoice->setHasShippingExtraFee($isShipping);
        $invoice->setHasExtraFee($isExtraFee);

        return $this;
    }
}
