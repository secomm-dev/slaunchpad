<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Form\Region;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

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
            sprintf("location.href = '%s';", $this->getUrl('*/region/new', ['country_id' => $this->getCountryId()])),
            [],
            40
        );
    }
}
