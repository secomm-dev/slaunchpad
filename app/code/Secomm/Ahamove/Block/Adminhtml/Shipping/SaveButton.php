<?php

namespace Secomm\Ahamove\Block\Adminhtml\Shipping;

use Secomm\Ahamove\Block\Adminhtml\Shipping\Entity\GenericButton;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Save entity button.
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * Retrieve Save button settings.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        return $this->wrapButtonSettings(
            __('Save')->getText(),
            'save primary',
            '',
            [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save'
            ],
            30
        );
    }
}
