<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Form\Region;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;

/**
 * Delete entity button.
 */
class Delete extends GenericButton implements ButtonProviderInterface
{
    /**
     * Retrieve Delete button settings.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        if (!$this->getRegionId()) {
            return [];
        }

        if ($this->isRegionDefault()) {
            return [];
        }

        return $this->wrapButtonSettings(
            __('Delete')->getText(),
            'delete',
            sprintf("deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this Region?'),
                $this->getUrl(
                    '*/*/delete',
                    [CityInterface::REGION_ID => $this->getRegionId()]
                )
            ),
            [],
            20
        );
    }
}
