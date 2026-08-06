<?php

namespace Secomm\Ahamove\Block\Adminhtml\System\Config;


use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Config\Block\System\Config\Form\Field;

class DisableField extends Field
{
    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setReadonly(true);
        return $element->getElementHtml();
    }
}
