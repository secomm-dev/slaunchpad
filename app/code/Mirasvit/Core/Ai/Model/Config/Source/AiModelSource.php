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

namespace Mirasvit\Core\Ai\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mirasvit\Core\Ai\Model\ConfigProvider;

class AiModelSource implements OptionSourceInterface
{
    private $configProvider;

    public function __construct(ConfigProvider $configProvider)
    {
        $this->configProvider = $configProvider;
    }

    public function toOptionArray(): array
    {
        $options = [];

        foreach (ConfigProvider::AVAILABLE_PROVIDERS as $provider) {
            $models        = $this->configProvider->getAvailableModels($provider);
            $providerLabel = $this->getProviderLabel($provider);

            foreach ($models as $modelCode => $modelLabel) {
                $options[] = [
                    'value' => $modelCode,
                    'label' => sprintf('[%s] %s', $providerLabel, $modelLabel),
                ];
            }
        }

        return $options;
    }

    public function getModelsForProvider(string $provider): array
    {
        $options = [];
        $models  = $this->configProvider->getAvailableModels($provider);

        foreach ($models as $modelCode => $modelLabel) {
            $options[] = [
                'value' => $modelCode,
                'label' => $modelLabel,
            ];
        }

        return $options;
    }

    public function getOpenAiModels(): array
    {
        return $this->getModelsForProvider(ConfigProvider::PROVIDER_OPENAI);
    }

    public function getClaudeModels(): array
    {
        return $this->getModelsForProvider(ConfigProvider::PROVIDER_CLAUDE);
    }

    public function getGeminiModels(): array
    {
        return $this->getModelsForProvider(ConfigProvider::PROVIDER_GEMINI);
    }

    public function getGrokModels(): array
    {
        return $this->getModelsForProvider(ConfigProvider::PROVIDER_GROK);
    }

    private function getProviderLabel(string $providerCode): string
    {
        try {
            return (string)__($this->configProvider->getProviderLabel($providerCode));
        } catch (\Exception $e) {
            return (string)__(ucfirst($providerCode));
        }
    }
}
