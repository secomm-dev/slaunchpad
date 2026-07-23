<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Form\SubCity;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterface;

/**
 * Save entity button.
 */
class AddNew extends GenericButton implements ButtonProviderInterface
{
    /**
     * Retrieve Save button settings.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        return $this->wrapButtonSettings(
            __('Add New')->getText(),
            'add primary',
            sprintf("location.href = '%s';", $this->getUrl(
                '*/subcity/new',
                [
                    CityInterface::CITY_ID => $this->getCityId()
                ]
            )),
            [],
            40
        );
    }
}
