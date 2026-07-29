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

namespace Mirasvit\Core\Ai\Service;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager as ModuleManager;
use Mirasvit\Core\Ai\Model\ConfigProvider;

class ModuleRegistry
{
    const DEFAULT_PROVIDER_MESSAGE = 'Current default provider';

    private $moduleManager;

    private $coreConfigProvider;

    private $modulesPool;

    public function __construct(
        ModuleManager  $moduleManager,
        ConfigProvider $coreConfigProvider,
        array          $modulesPool = []
    ) {
        $this->moduleManager      = $moduleManager;
        $this->coreConfigProvider = $coreConfigProvider;
        $this->modulesPool        = $modulesPool;
    }

    public function getAiEnabledModules(): array
    {
        $aiModules = [];

        foreach ($this->modulesPool as $moduleName => $config) {
            if (!$this->moduleManager->isEnabled($moduleName)) {
                continue;
            }

            if ($this->isModuleAiEnabled($config)) {
                $aiModules[] = [
                    'module_name' => $moduleName,
                    'ai_provider' => $this->getModuleAiProvider($config),
                ];
            }
        }

        return $aiModules;
    }

    private function isModuleAiEnabled(array $config): bool
    {
        if (!$config['ai_enabled_method'] || $config['ai_enabled_method'] === 'null') {
            return true;
        }

        try {
            $configProvider = ObjectManager::getInstance()->get($config['config_class']);
            $method         = $config['ai_enabled_method'];

            return $configProvider->$method();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getModuleAiProvider(array $config): string
    {
        if (!$config['ai_provider_method'] || $config['ai_provider_method'] === 'null') {
            return self::DEFAULT_PROVIDER_MESSAGE;
        }

        try {
            $configProvider = ObjectManager::getInstance()->get($config['config_class']);
            $method         = $config['ai_provider_method'];
            $result         = $configProvider->$method();

            if (is_string($result)) {
                return $this->coreConfigProvider->getProviderLabel($result);
            } elseif (is_object($result) && method_exists($result, 'getDefaultProvider')) {
                $provider = $result->getDefaultProvider();

                return $provider ? $this->coreConfigProvider->getProviderLabel($provider) : (string)__('Not configured');
            }

            return self::DEFAULT_PROVIDER_MESSAGE;
        } catch (\Exception $e) {
            return (string)__('Unknown');
        }
    }
}
