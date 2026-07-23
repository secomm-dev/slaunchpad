<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Form\City;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Back to list button.
 */
class BackListing extends GenericButton implements ButtonProviderInterface
{
    public function __construct(Data $data, Context $context)
    {
        parent::__construct($data, $context);
    }

    /**
     * Retrieve Back To Grid button settings.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $countryId = $this->data->getCountryIdByRegionId($this->getRegionId());
        return $this->wrapButtonSettings(
            __('Back')->getText(),
            'back',
            sprintf("location.href = '%s';", $this->getUrl('*/region/index',
                [RegionInterface::COUNTRY_ID => $countryId])),
            [],
            10
        );
    }
}
