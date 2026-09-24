<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Block\Adminhtml;

use Magento\Backend\Block\Widget\Container;

/**
 * Risk Lists page header: title + Add Phone button.
 */
class Lists extends Container
{
    protected function _construct(): void
    {
        $this->_blockGroup = 'Secomm_CodRisk';
        $this->_controller = 'adminhtml_codrisk_lists';
        $this->_headerText = (string)__('COD Risk Lists');
        parent::_construct();
    }

    protected function _prepareLayout(): self
    {
        $this->addButton(
            'add',
            [
                'label' => __('Add Phone'),
                'class' => 'primary',
                'onclick' => "setLocation('" . $this->getUrl('codrisk/lists/new') . "')",
            ]
        );

        return parent::_prepareLayout();
    }
}
