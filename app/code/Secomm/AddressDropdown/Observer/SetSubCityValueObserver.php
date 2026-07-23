<?php

namespace Secomm\AddressDropdown\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\AddressDropdown\Helper\Address;
use Secomm\AddressDropdown\Helper\Data;

class SetSubCityValueObserver implements ObserverInterface
{
    public function __construct(
        protected Address $addressHelper,
        protected Data    $dataHelper
    )
    {
    }

    public function execute(Observer $observer)
    {
        if (!$this->dataHelper->isAddressDropdownModuleEnabled()) {
            return;
        }
        $address = $observer->getEvent()->getAddress();
        if ($address && $address->hasData()) {
            $subCity = $address->getSubCity();
            $cityDefaultName = $address->getCity() ?? null;
            if ($subCity) {
                $address->setSubCity($this->addressHelper->getSubCityNameByDefaultName($subCity, $cityDefaultName));
            }
        }
    }
}
