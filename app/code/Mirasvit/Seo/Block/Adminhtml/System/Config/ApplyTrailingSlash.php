<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\Seo\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ApplyTrailingSlash extends Field
{
    protected function _prepareLayout(): ApplyTrailingSlash
    {
        parent::_prepareLayout();

        if (!$this->getTemplate()) {
            $this->setTemplate('Mirasvit_Seo::system/config/trailing_slash/apply.phtml');
        }

        return $this;
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $originalData = $element->getOriginalData();

        $this->addData(
            [
                'button_label' => __($originalData['button_label']),
                'html_id' => $element->getHtmlId(),
                'ajax_url' => $this->_urlBuilder->getUrl('seo/system_config_trailingSlash/apply')
                                . '?store_id=' . $this->getStoreId()
                                . '&website_id=' . $this->getWebsiteId(),
            ]
        );

        return $this->_toHtml();
    }

    private function getStoreId(): ?int
    {
        if (!$this->hasData('store_id')) {
            $this->setData('store_id', (int)$this->getRequest()->getParam('store'));
        }

        return $this->getData('store_id');
    }

    private function getWebsiteId(): ?int
    {
        if (!$this->hasData('website_id')) {
            $this->setData('website_id', (int)$this->getRequest()->getParam('website'));
        }

        return $this->getData('website_id');
    }
}
