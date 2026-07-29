<?php

namespace Secomm\Ahamove\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class RefreshButton extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_template = 'Secomm_Ahamove::system/config/button.phtml';

    public function __construct(
        Context             $context, array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    )
    {
        parent::__construct($context, $data, $secureRenderer);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl()
    {
        return $this->getUrl('ahamove/system/refreshtoken');
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')
            ->setData(['id' => 'refresh_token_button', 'label' => __('Refresh Token'),]);
        return $button->toHtml();
    }
}