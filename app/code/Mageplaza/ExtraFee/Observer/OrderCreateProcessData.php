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
 * Class OrderCreateProcessData
 * @package Mageplaza\ExtraFee\Observer
 */
class OrderCreateProcessData implements ObserverInterface
{
    protected $helper;

    /**
     * OrderCreateProcessData constructor.
     *
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Process post data and set usage of Extra Fee into order creation model
     *
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        $model = $observer->getEvent()->getOrderCreateModel();
        $data  = $observer->getEvent()->getRequest();
        $quote = $model->getQuote();
        if (isset($data['mp_extra_fee'])) {
            $formData  = explode(',', $data['mp_extra_fee']);
            $area      = $data['mp_extra_fee_area'];
            $areaArray = explode(',', $area);
            foreach ($areaArray as $key => $item) {
                $this->helper->setMpExtraFee($quote, $formData[$key], $item);
            }
            $this->helper->getCheckoutSession()->setMpArea($area);
        }

        return $this;
    }
}
