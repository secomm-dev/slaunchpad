<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Plugin;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Block\Address\Grid as AddressGrid;
use Secomm\AddressDropdown\Api\CityRepositoryInterface;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;
use Secomm\AddressDropdown\Helper\Address as AddressHelper;

class AddressGridPlugin
{
    public function __construct(
        protected CityRepositoryInterface $cityRepository,
        protected AddressDropdownHelper   $addressDropdownHelper,
        protected AddressHelper $addressHelper
    )
    {
    }

    /**
     * Modify the getAdditionalAddresses method to display city name for the city attribute.
     *
     * @param AddressGrid $subject
     * @param AddressInterface[] $result
     * @return AddressInterface[]
     */
    public function afterGetAdditionalAddresses(AddressGrid $subject, $result): array
    {
        if (!$this->addressDropdownHelper->isAddressDropdownModuleEnabled()) {
            return $result;
        }
        foreach ($result as $address) {
            $cityDefaultName = $address->getCity();
            $regionId = $address->getRegionId();
            $cityName = $this->addressHelper->getCityNameByDefaultName($cityDefaultName, $regionId);
            if (!is_null($cityName)) {
                $address->setCity($cityName);
            }
        }
        return $result;
    }
}
