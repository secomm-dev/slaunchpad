<?php

namespace Secomm\Ahamove\Block\Adminhtml\Shipping;

use Secomm\Ahamove\Block\Adminhtml\Shipping\Entity\GenericButton;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Delete entity button.
 */
class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * Retrieve Delete button settings.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        if (!$this->getEntityId()) {
            return [];
        }

        return $this->wrapButtonSettings(
            __('Delete')->getText(),
            'delete',
            sprintf("deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this entity?'),
                $this->getUrl(
                    'ahamove/shipping/delete',
                    ['id' => $this->getEntityId()]
                )
            ),
            [],
            20
        );
    }
}
