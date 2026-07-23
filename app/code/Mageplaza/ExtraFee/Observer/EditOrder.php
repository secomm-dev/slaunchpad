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

/**
 * Class EditOrder
 * @package Mageplaza\ExtraFee\Observer
 */
class EditOrder implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * EditOrder constructor.
     *
     * @param Data $helperData
     */
    public function __construct(Data $helperData)
    {
        $this->helperData = $helperData;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

        if ($this->helperData->isEnabled() && $order->getMpExtraFee()) {
            $extraFee = Data::jsonDecode($order->getMpExtraFee());
            if (isset($extraFee['is_invoiced'])) {
                unset($extraFee['is_invoiced']);
            }
            $quote->setMpExtraFee(Data::jsonEncode($extraFee));
        }
    }
}
