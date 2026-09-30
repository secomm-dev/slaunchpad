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

class SaveButton extends Template implements ButtonProviderInterface
{
    private GenericButton $genericButton;

    public function __construct(Template\Context $context, GenericButton $genericButton, array $data = [])
    {
        parent::__construct($context, $data);
        $this->genericButton = $genericButton;
    }

    public function getButtonData(): array
    {
        return [
            'label' => __('Save Zone'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 30,
        ];
    }
}
