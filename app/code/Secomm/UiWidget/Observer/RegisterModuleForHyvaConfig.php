<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Register module templates and utilities with every Hyvä theme build.
 */
class RegisterModuleForHyvaConfig implements ObserverInterface
{
    public function __construct(private readonly ComponentRegistrar $componentRegistrar)
    {
    }

    public function execute(Observer $observer): void
    {
        $config = $observer->getData('config');
        if (!$config instanceof DataObject) {
            return;
        }

        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Secomm_UiWidget');
        if (!is_string($path) || !str_starts_with($path, BP . DIRECTORY_SEPARATOR)) {
            return;
        }

        $extensions = (array)$config->getData('extensions');
        $extensions[] = ['src' => substr($path, strlen(BP) + 1)];
        $config->setData('extensions', $extensions);
    }
}
