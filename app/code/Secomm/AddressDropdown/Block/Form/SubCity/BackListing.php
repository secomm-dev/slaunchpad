<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Form\SubCity;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;

/**
 * Back to list button.
 */
class BackListing extends GenericButton implements ButtonProviderInterface
{
    /**
     * Retrieve Back To Grid button settings.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        return $this->wrapButtonSettings(
            __('Back')->getText(),
            'back',
            sprintf("location.href = '%s';", $this->getUrl('*/city/index',
                [ CityInterface::REGION_ID => $this->getRegionId()])),
            [],
            10
        );
    }
}
