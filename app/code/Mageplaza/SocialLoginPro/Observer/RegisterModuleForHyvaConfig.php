<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  Mageplaza
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SocialLoginPro\Observer;


use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class RegisterModuleForHyvaConfig
 *
 * @package Mageplaza\SocialLoginPro\Observer
 */
class RegisterModuleForHyvaConfig implements ObserverInterface
{
    /**
     * @var ComponentRegistrar
     */
    private ComponentRegistrar $componentRegistrar;

    /**
     * @param ComponentRegistrar $componentRegistrar
     */
    public function __construct(ComponentRegistrar $componentRegistrar)
    {
        $this->componentRegistrar = $componentRegistrar;
    }

    /**
     * @param Observer $event
     * @return void
     */
    public function execute(Observer $event): void
    {
        $config = $event->getData('config');
        $extensions = $config->hasData('extensions') ? $config->getData('extensions') : [];
        $moduleName = implode('_', array_slice(explode('\\', __CLASS__), 0, 2));
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        $extensions[] = ['src' => substr($path, strlen(BP) + 1)];
        $config->setData('extensions', $extensions);
    }
}
