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
use Magento\Sales\Model\Order;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class MultiShippingConvertQuoteToOrder
 * @package Mageplaza\ExtraFee\Observer
 */
class MultiShippingConvertQuoteToOrder implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @param Data $helperData
     */
    public function __construct(Data $helperData)
    {
        $this->helperData = $helperData;
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order   = $observer->getEvent()->getOrder();
        $address = $observer->getEvent()->getAddress();

        $this->helperData->setExtraFeeNote($address);
        $this->helperData->setExtraFeeForItems($order, $address);

        return $this;
    }
}
