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

namespace Mirasvit\Core\Ai\Service\Grok;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Mirasvit\Core\Ai\Service\ProviderConfigInterface;
use Mirasvit\Core\Ai\Model\ConfigProvider as CoreAiConfigProvider;

class ConfigProvider implements ProviderConfigInterface
{
    public const PROVIDER_NAME  = 'grok';
    public const PROVIDER_LABEL = 'xAI Grok';

    public const CONFIG_PATH_API_KEY = 'mst_core/ai/grok/api_key';
    public const CONFIG_PATH_MODEL   = 'mst_core/ai/grok/model';
    public const CONFIG_PATH_ENABLED = 'mst_core/ai/grok/enabled';

    public const MODEL_GROK_4_3      = 'grok-4.3';
    public const MODEL_GROK_4_20     = 'grok-4.20';
    public const MODEL_GROK_4_1_FAST = 'grok-4-1-fast-non-reasoning';
    public const MODEL_GROK_4        = 'grok-4';
    public const MODEL_GROK_3        = 'grok-3';
    public const MODEL_GROK_3_MINI   = 'grok-3-mini';

    public const ENDPOINT_RESPONSES = 'responses';

    public const TOKEN_LIMITS
        = [
            self::MODEL_GROK_4_3      => 256000,
            self::MODEL_GROK_4_20     => 256000,
            self::MODEL_GROK_4_1_FAST => 2000000,
            self::MODEL_GROK_4        => 256000,
            self::MODEL_GROK_3        => 131072,
            self::MODEL_GROK_3_MINI   => 131072,
        ];

    private $scopeConfig;

    private $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface   $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor   = $encryptor;
    }

    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(CoreAiConfigProvider::CONFIG_PATH_ENABLED);
    }

    public function getTimeout(): int
    {
        return (int)$this->scopeConfig->getValue(CoreAiConfigProvider::CONFIG_PATH_TIMEOUT);
    }

    public function getMaxTokens(): int
    {
        return (int)$this->scopeConfig->getValue(CoreAiConfigProvider::CONFIG_PATH_MAX_TOKENS);
    }

    public function getTemperature(): float
    {
        $temperature = (float)$this->scopeConfig->getValue(CoreAiConfigProvider::CONFIG_PATH_TEMPERATURE);

        return $temperature >= 0 ? $temperature : CoreAiConfigProvider::DEFAULT_TEMPERATURE;
    }

    public function getAllModels(): array
    {
        return array_keys($this->getAvailableModels());
    }

    public function getEndpointForModel(string $model): string
    {
        return self::ENDPOINT_RESPONSES;
    }

    public function getTokenLimitForModel(string $model): int
    {
        return self::TOKEN_LIMITS[$model] ?? 131072;
    }

    public function getBaseUrl(): string
    {
        return 'https://api.x.ai/v1';
    }

    public function getAvailableModels(): array
    {
        return [
            self::MODEL_GROK_4_3      => 'Grok 4.3',
            self::MODEL_GROK_4_20     => 'Grok 4.20',
            self::MODEL_GROK_4_1_FAST => 'Grok 4.1 Fast',
            self::MODEL_GROK_4        => 'Grok 4',
            self::MODEL_GROK_3        => 'Grok 3',
            self::MODEL_GROK_3_MINI   => 'Grok 3 Mini',
        ];
    }

    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function getProviderLabel(): string
    {
        return self::PROVIDER_LABEL;
    }

    public function getDefaultModel(): string
    {
        $model = (string)$this->scopeConfig->getValue(self::CONFIG_PATH_MODEL);

        return !empty($model) ? $model : self::MODEL_GROK_4_1_FAST;
    }

    public function isProviderEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::CONFIG_PATH_ENABLED);
    }

    public function getApiKey(): string
    {
        $encryptedValue = (string)$this->scopeConfig->getValue(self::CONFIG_PATH_API_KEY);

        if (empty($encryptedValue)) {
            return '';
        }

        if (strpos($encryptedValue, 'xai-') === 0) {
            return $encryptedValue;
        }

        return $this->encryptor->decrypt($encryptedValue);
    }
}
