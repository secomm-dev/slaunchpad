<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\AddressDropdown\Plugin;

use Magento\Customer\Model\Customer\DataProviderWithDefaultAddresses;

/**
 * Plugin for managing assistance_allowed extension attribute in Customer form Data Provider.
 */
class DataProviderWithDefaultAddressesPlugin
{
    public function __construct(
        protected \Secomm\AddressDropdown\Helper\Data $addressDropdownHelper,
        protected \Secomm\AddressDropdown\Helper\Address $addressHelper
    )
    {
    }

    /**
     * @param DataProviderWithDefaultAddresses $subject
     * @param array $result
     * @return array
     */
    public function afterGetData(
        DataProviderWithDefaultAddresses $subject,
        array                            $result
    ): array
    {
        if (!$this->addressDropdownHelper->isAddressDropdownModuleEnabled()) {
            return $result;
        }
        foreach ($result as $id => $entityData) {
            if ($id) {
                if (isset($entityData['default_billing_address']['city'])) {
                    $regionId = $entityData['default_billing_address']['region_id'] ?? null;
                    $cityBilling = $entityData['default_billing_address']['city'];
                    if (isset($entityData['default_billing_address']['sub_city'])) {
                        $subCityBilling = $entityData['default_billing_address']['sub_city'];
                        $subCityBilling = $this->addressHelper->getSubCityNameByDefaultName($subCityBilling, $cityBilling);
                        if ($subCityBilling) {
                            $result[$id]['default_billing_address']['sub_city'] = $subCityBilling;
                        }
                    }
                    $cityBilling = $this->addressHelper->getCityNameByDefaultName($cityBilling, $regionId);
                    if ($cityBilling) {
                        $result[$id]['default_billing_address']['city'] = $cityBilling;
                    }
                }

                //shippingAddress
                if (isset($entityData['default_shipping_address']['city'])) {
                    $cityShipping = $entityData['default_shipping_address']['city'];
                    $regionId = $entityData['default_shipping_address']['region_id'] ?? null;
                    if (isset($entityData['default_shipping_address']['sub_city'])) {
                        $subCityShipping = $entityData['default_shipping_address']['sub_city'];
                        $subCityShipping = $this->addressHelper->getSubCityNameByDefaultName($subCityShipping, $cityShipping);
                        if ($subCityShipping) {
                            $result[$id]['default_shipping_address']['sub_city'] = $subCityShipping;
                        }
                    }
                    $cityShipping = $this->addressHelper->getCityNameByDefaultName($cityShipping, $regionId);
                    if ($cityShipping) {
                        $result[$id]['default_shipping_address']['city'] = $cityShipping;
                    }
                }
            }
        }

        return $result;
    }
}
