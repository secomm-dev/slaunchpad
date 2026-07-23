<?php

namespace Secomm\AddressDropdown\Plugin;

use Magento\Multishipping\Block\DataProviders\Billing;
use Magento\Quote\Model\Quote\Address;
use Secomm\AddressDropdown\Helper\Address as AddressHelper;
use Secomm\AddressDropdown\Helper\Data as AddressDataHelper;

class DataProvidersPlugin
{
    public function __construct(
        protected AddressHelper     $addressHelper,
        protected AddressDataHelper $addressDataHelper,
    )
    {
    }

    /**
     * @param Billing $subject
     * @param callable $proceed
     * @param Address $address
     * @return string
     */
    public function aroundGetAddressHtml(Billing $subject, callable $proceed, Address $address): string
    {
        // Check if the module is enabled
        if ($this->addressDataHelper->isAddressDropdownModuleEnabled()) {
            // Modify the address data
            $subCity = $address->getData('sub_city');
            $subCity = $subCity ? $this->addressHelper->getSubCityNameByDefaultName($subCity) : '';
            $address->setData('sub_city', $subCity);
        }

        // Call the original method with the potentially modified address
        return $proceed($address);
    }
}
