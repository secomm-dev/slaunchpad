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

namespace Mirasvit\Core\Ai\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Mirasvit\Core\Ai\Service\ProviderConfigInterface;

class ConfigProvider
{
    private $scopeConfig;

    private $encryptor;

    private $providerConfigs;

    public const CONFIG_PATH_ENABLED          = 'mst_core/ai/enabled';
    public const CONFIG_PATH_DEFAULT_PROVIDER = 'mst_core/ai/default_provider';
    public const CONFIG_PATH_TIMEOUT          = 'mst_core/ai/timeout';
    public const CONFIG_PATH_MAX_TOKENS       = 'mst_core/ai/max_tokens';
    public const CONFIG_PATH_TEMPERATURE      = 'mst_core/ai/temperature';

    public const DEFAULT_TEMPERATURE = 0.7;

    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_CLAUDE = 'claude';
    public const PROVIDER_GEMINI = 'gemini';
    public const PROVIDER_GROK   = 'grok';

    public const AVAILABLE_PROVIDERS
        = [
            self::PROVIDER_OPENAI,
            self::PROVIDER_CLAUDE,
            self::PROVIDER_GEMINI,
            self::PROVIDER_GROK,
        ];

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface   $encryptor,
        array                $providerConfigs = []
    ) {
        $this->scopeConfig     = $scopeConfig;
        $this->encryptor       = $encryptor;
        $this->providerConfigs = $providerConfigs;
    }

    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::CONFIG_PATH_ENABLED);
    }

    public function getDefaultProvider(): string
    {
        return (string)$this->scopeConfig->getValue(self::CONFIG_PATH_DEFAULT_PROVIDER);
    }

    public function getApiKey(string $provider): string
    {
        $providerConfig = $this->getProviderConfig($provider);

        return $providerConfig->getApiKey();
    }

    public function getDefaultModel(string $provider): string
    {
        $providerConfig = $this->getProviderConfig($provider);

        return $providerConfig->getDefaultModel();
    }

    public function isProviderEnabled(string $provider): bool
    {
        $providerConfig = $this->getProviderConfig($provider);

        return $providerConfig->isProviderEnabled();
    }

    public function getTimeout(): int
    {
        return (int)$this->scopeConfig->getValue(self::CONFIG_PATH_TIMEOUT);
    }

    public function getMaxTokens(): int
    {
        return (int)$this->scopeConfig->getValue(self::CONFIG_PATH_MAX_TOKENS);
    }

    public function getTemperature(): float
    {
        $temperature = (float)$this->scopeConfig->getValue(self::CONFIG_PATH_TEMPERATURE);

        return $temperature >= 0 ? $temperature : self::DEFAULT_TEMPERATURE;
    }

    public function getEnabledProviders(): array
    {
        $enabledProviders = [];

        foreach (self::AVAILABLE_PROVIDERS as $provider) {
            if ($this->isProviderEnabled($provider)) {
                $enabledProviders[] = $provider;
            }
        }

        return $enabledProviders;
    }

    public function isDebugModeEnabled(): bool
    {
        // Check environment variable first for forced debug mode
        if (getenv('MST_AI_DEBUG') === '1') {
            return true;
        }

        return (bool)$this->scopeConfig->getValue('mst_core/ai/debug_mode');
    }

    public function getRetryAttempts(): int
    {
        return 3;
    }

    public function getAvailableModels(string $provider): array
    {
        $providerConfig = $this->getProviderConfig($provider);
        if ($providerConfig) {
            return $providerConfig->getAvailableModels();
        }

        return [];
    }

    public function getProviderLabel(string $provider): string
    {
        $providerConfig = $this->getProviderConfig($provider);

        return $providerConfig->getProviderLabel();
    }


    public function getProviderConfig(string $provider): ProviderConfigInterface
    {
        foreach ($this->providerConfigs as $providerConfig) {
            if ($providerConfig->getProviderName() === $provider) {
                return $providerConfig;
            }
        }

        throw new \Exception(sprintf('Provider config not found for: %s', $provider));
    }
}
