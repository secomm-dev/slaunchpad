<?php

namespace Secomm\AddressDropdown\Plugin;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote\Address;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;class AddSubCityImportAddressPlugin
{
    public function __construct(
        protected AddressDropdownHelper   $addressDropdownHelper,
    )
    {
    }

    /**
     * @param Address $subject
     * @param $result
     * @param AddressInterface $address
     * @return mixed
     */
    public function afterImportCustomerAddressData(Address $subject, $result, AddressInterface $address): mixed
    {
        if (!$this->addressDropdownHelper->isAddressDropdownModuleEnabled()) {
            return $result;
        }else{
            if ($address->getCustomAttribute('sub_city')) {
                $subject->setSubCity($address->getCustomAttribute('sub_city')->getValue());
            }
        }
        return $result;
    }
}
