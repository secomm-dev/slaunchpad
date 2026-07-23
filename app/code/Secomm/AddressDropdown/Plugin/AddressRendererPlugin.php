<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Plugin;

use Magento\Customer\Block\Address\Renderer\DefaultRenderer;
use Secomm\AddressDropdown\Api\CityRepositoryInterface;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;
use Secomm\AddressDropdown\Helper\Address as AddressHelper;

class AddressRendererPlugin
{
    public function __construct(
        protected CityRepositoryInterface $cityRepository,
        protected AddressDropdownHelper   $addressDropdownHelper,
        protected AddressHelper $addressHelper
    )
    {
    }

    /**
     * Modify the renderArray method to display city name for the city attribute.
     *
     * @param DefaultRenderer $subject
     * @param callable $proceed
     * @param array $addressAttributes
     * @param $format
     * @return string
     */
    public function aroundRenderArray(DefaultRenderer $subject, callable $proceed, array $addressAttributes, $format = null): string
    {
        if (!$this->addressDropdownHelper->isAddressDropdownModuleEnabled()) {
            return $proceed($addressAttributes, $format);
        }
        if (isset($addressAttributes['city']) && isset($addressAttributes['region_id'])) {
            $cityDefaultName = $addressAttributes['city'];
            $regionId = $addressAttributes['region_id'];
            $locale = $addressAttributes['locale'] ?? null;
            $cityName = $this->getCityNameByDefaultName($cityDefaultName, $regionId, $locale);
            //move sub_city on top of city
            if (isset($addressAttributes['sub_city'])) {
                $subCity = $this->addressHelper->getSubCityNameByDefaultName($addressAttributes['sub_city'], $cityDefaultName, $locale);
                unset($addressAttributes['sub_city']);
                $addressAttributes = ['sub_city' => $subCity] + $addressAttributes;
            }

            if (!is_null($cityName)) {
                $addressAttributes['city'] = $cityName;
            }
        }
        return $proceed($addressAttributes, $format);
    }

    /**
     * Get city name by ID.
     *
     * @param string $defaultName
     * @param $regionId
     * @return string|null
     */
    protected function getCityNameByDefaultName(string $defaultName, $regionId, $locale): ?string
    {
        try {
            return $this->addressHelper->getCityNameByDefaultName($defaultName, $regionId, $locale);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
    }
}
