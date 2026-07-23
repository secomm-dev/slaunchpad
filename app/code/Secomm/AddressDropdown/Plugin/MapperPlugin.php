<?php

namespace Secomm\AddressDropdown\Plugin;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Model\Address\Mapper;
use Secomm\AddressDropdown\Helper\Data as AddressDataHelper;
use Secomm\AddressDropdown\Helper\Address as AddressHelper;

class MapperPlugin
{
    public function __construct(
        protected AddressDataHelper $addressDataHelper,
        protected AddressHelper     $addressHelper
    )
    {
    }

    /**
     * @param Mapper $subject
     * @param callable $proceed
     * @param AddressInterface $addressDataObject
     * @return array
     */
    public function aroundToFlatArray(Mapper $subject, callable $proceed, AddressInterface $addressDataObject): array
    {
        $flatAddressArray = $proceed($addressDataObject);

        if ($this->addressDataHelper->isAddressDropdownModuleEnabled()) {
            $subCityAttribute = $addressDataObject->getCustomAttribute('sub_city');
            if ($subCityAttribute && $subCityAttribute->getValue()) {
                $subCityValue = $subCityAttribute->getValue();
                $transformedSubCity = $this->addressHelper->getSubCityNameByDefaultName($subCityValue);
                if (!$transformedSubCity) {
                    return $flatAddressArray;
                } else {
                    $flatAddressArray['sub_city'] = $transformedSubCity;
                }
            } else {
                $flatAddressArray['sub_city'] = '';
            }
        }

        return $flatAddressArray;
    }
}
