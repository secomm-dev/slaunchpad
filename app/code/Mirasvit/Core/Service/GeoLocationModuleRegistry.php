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

namespace Mirasvit\Core\Service;

use Magento\Framework\Module\Manager as ModuleManager;

class GeoLocationModuleRegistry
{
    private $moduleManager;

    private $modulesPool;

    public function __construct(
        ModuleManager $moduleManager,
        array         $modulesPool = []
    ) {
        $this->moduleManager = $moduleManager;
        $this->modulesPool   = $modulesPool;
    }

    /**
     * @return array<string, string> [moduleName => label]
     */
    public function getEnabledModules(): array
    {
        $modules = [];

        foreach ($this->modulesPool as $moduleName => $config) {
            if (!$this->moduleManager->isEnabled($moduleName)) {
                continue;
            }

            $modules[$moduleName] = $config['label'] ?? $moduleName;
        }

        return $modules;
    }

    public function isModuleRegistered(string $moduleName): bool
    {
        return isset($this->modulesPool[$moduleName]);
    }
}
