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
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\Core\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Mirasvit\Core\Ai\Service\ModuleRegistry;

class AiModulesField extends Field
{
    private $moduleRegistry;

    public function __construct(
        Context        $context,
        ModuleRegistry $moduleRegistry,
        array          $data = []
    ) {
        $this->moduleRegistry = $moduleRegistry;

        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    public function getAiModules(): array
    {
        return $this->moduleRegistry->getAiEnabledModules();
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        if (!$this->getTemplate()) {
            $this->setTemplate('Mirasvit_Core::config/ai-modules-field.phtml');
        }

        return $this;
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
}