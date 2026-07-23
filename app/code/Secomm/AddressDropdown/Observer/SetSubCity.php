<?php

namespace Secomm\AddressDropdown\Observer;


use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SetSubCity implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $shippingAddress = $order->getShippingAddress();

        $subCity = $observer->getEvent()->getRequest()->getParam('shippingAddress.sub_city');
        $shippingAddress->setData('sub_city', $subCity);

        return $this;
    }
}
