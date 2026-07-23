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
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class CreditmemoSaveAfter
 * @package Mageplaza\ExtraFee\Observer
 */
class CreditmemoSaveAfter implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * CreditmemoSaveAfter constructor.
     *
     * @param Data $helper
     */
    public function __construct(Data $helper, PriceCurrencyInterface $priceCurrency)
    {
        $this->helper = $helper;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();

        $order              = $creditmemo->getOrder();
        $extraFeeTotals     = $this->helper->getObjectExtraFeeTotals($creditmemo, $order);
        $extraFeeData       = $this->helper->jsonDecode($order->getMpExtraFee());
        $isExtraFeeRefunded = false;
        if (!$this->helper->isRefunded($order)) {
            $this->helper->setRefunded($order, $creditmemo->getId());
        }
        foreach ($extraFeeTotals as $fee) {
            if ($fee['rf']) {
                foreach ($extraFeeData['totals'] as $index => $orderFee) {
                    if ($orderFee['code'] === $fee['code']) {
                        $feeValue = round($fee['base_value'], 2);
                        if ($creditmemo->getData($fee['code'])) {
                            $feeValue = $this->priceCurrency->round($creditmemo->getData($fee['code']));
                        }
                        $isExtraFeeRefunded = true;
                        $extraFeeData['totals'][$index]['refund_value'] = isset($orderFee['refund_value']) ? $orderFee['refund_value'] + $feeValue : $feeValue;
                        break;
                    }
                }
            }
        }
        if ($isExtraFeeRefunded) {
            $order->setMpExtraFee($this->helper->jsonEncode($extraFeeData));
        }

        return $this;
    }
}
