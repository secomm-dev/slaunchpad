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

use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Mirasvit\Core\Service\GeoLocationModuleRegistry;

class GeoLocationGroup extends Fieldset
{
    private $moduleRegistry;

    public function __construct(
        \Magento\Backend\Block\Context $context,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        GeoLocationModuleRegistry $moduleRegistry,
        array $data = []
    ) {
        $this->moduleRegistry = $moduleRegistry;

        parent::__construct($context, $authSession, $jsHelper, $data);
    }

    public function render(AbstractElement $element): string
    {
        $consumers = $this->moduleRegistry->getEnabledModules();

        $comment = (string)__('Determine visitor\'s country, city, and timezone based on their IP address.');
        $comment .= ' ' . __('Free <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank">MaxMind account</a> required for automatic updates (checked weekly).');

        if ($consumers) {
            $comment .= '<br/><strong>' . __('Used by:') . '</strong> ' . implode(', ', $consumers);
        }

        $element->setComment($comment);

        return parent::render($element);
    }
}
