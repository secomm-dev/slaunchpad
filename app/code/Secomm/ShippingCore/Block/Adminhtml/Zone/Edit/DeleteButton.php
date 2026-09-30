<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Block\Adminhtml\Zone\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Framework\View\Element\Template;

class DeleteButton extends Template implements ButtonProviderInterface
{
    private GenericButton $genericButton;

    public function __construct(Template\Context $context, GenericButton $genericButton, array $data = [])
    {
        parent::__construct($context, $data);
        $this->genericButton = $genericButton;
    }

    public function getButtonData(): array
    {
        if ($this->genericButton->getZoneId() === null) {
            return [];
        }

        return [
            'label' => __('Delete Zone'),
            'class' => 'delete',
            'on_click' => sprintf(
                'deleteConfirm(\'%s\', \'%s\')',
                __('Are you sure you want to delete this zone? Carriers referencing it will stop matching it.'),
                $this->genericButton->getUrl('*/*/delete', ['zone_id' => $this->genericButton->getZoneId()])
            ),
            'sort_order' => 40,
        ];
    }
}
