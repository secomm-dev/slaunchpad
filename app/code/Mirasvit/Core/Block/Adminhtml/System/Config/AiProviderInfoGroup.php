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

namespace Mirasvit\Core\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;
use Mirasvit\Core\Ai\Model\ConfigProvider as CoreConfigProvider;

class AiProviderInfoGroup extends Fieldset
{
    private $coreConfigProvider;

    public function __construct(
        Context            $context,
        Session            $authSession,
        Js                 $jsHelper,
        CoreConfigProvider $coreConfigProvider,
        array              $data = []
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data);
        $this->coreConfigProvider = $coreConfigProvider;
    }

    public function render(AbstractElement $element)
    {
        $html = $this->_getHeaderHtml($element);

        $html .= '<tr><td colspan="4" class="provider-info-cell">';
        $html .= $this->getProviderInfoBlock()->toHtml();
        $html .= '</td></tr>';

        foreach ($element->getElements() as $field) {
            $html .= $field->toHtml();
        }

        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    protected function getProviderInfoBlock(): ProviderInfo
    {
        return $this->getLayout()->createBlock(
            ProviderInfo::class,
            'core.ai.provider.info.' . uniqid()
        );
    }

}
