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

namespace Mirasvit\Core\Ai\Service\OpenAi;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Mirasvit\Core\Ai\Service\ProviderConfigInterface;
use Mirasvit\Core\Ai\Model\ConfigProvider as CoreAiConfigProvider;

class ConfigProvider implements ProviderConfigInterface
{
    public const PROVIDER_NAME = 'openai';
    public const PROVIDER_LABEL = 'OpenAI';

    public const CONFIG_PATH_API_KEY = 'mst_core/ai/openai/api_key';
    public const CONFIG_PATH_MODEL   = 'mst_core/ai/openai/model';
    public const CONFIG_PATH_ENABLED = 'mst_core/ai/openai/enabled';

    public const MODEL_GPT55_PRO   = 'gpt-5.5-pro';
    public const MODEL_GPT55       = 'gpt-5.5';
    public const MODEL_GPT54_PRO   = 'gpt-5.4-pro';
    public const MODEL_GPT54       = 'gpt-5.4';
    public const MODEL_GPT52_PRO   = 'gpt-5.2-pro';
    public const MODEL_GPT52       = 'gpt-5.2';
    public const MODEL_GPT51       = 'gpt-5.1';
    public const MODEL_GPT5        = 'gpt-5';
    public const MODEL_GPT5_MINI   = 'gpt-5-mini';
    public const MODEL_GPT5_NANO   = 'gpt-5-nano';
    public const MODEL_GPT41       = 'gpt-4.1';
    public const MODEL_GPT41_MINI  = 'gpt-4.1-mini';
    public const MODEL_GPT41_NANO  = 'gpt-4.1-nano';
    public const MODEL_GPT4        = 'gpt-4';
    public const MODEL_GPT4_TURBO  = 'gpt-4-turbo';
    public const MODEL_GPT4O       = 'gpt-4o';
    public const MODEL_GPT4O_MINI  = 'gpt-4o-mini';
    public const MODEL_GPT35_TURBO = 'gpt-3.5-turbo';

    public const ENDPOINT_RESPONSES = 'responses';

    public const DEFAULT_OPENAI_TEMPERATURE = 0.7;
    public const DEFAULT_OPENAI_MAX_TOKENS  = 2097;
    public const DEFAULT_TOP_P              = 1.0;
    public const DEFAULT_FREQUENCY_PENALTY  = 0.0;
    public const DEFAULT_PRESENCE_PENALTY   = 0.0;

    public const TOKEN_LIMITS
        = [
            self::MODEL_GPT55_PRO   => 128000,
            self::MODEL_GPT55       => 128000,
            self::MODEL_GPT54_PRO   => 128000,
            self::MODEL_GPT54       => 128000,
            self::MODEL_GPT52_PRO   => 128000,
            self::MODEL_GPT52       => 128000,
            self::MODEL_GPT51       => 128000,
            self::MODEL_GPT5        => 128000,
            self::MODEL_GPT5_MINI   => 128000,
            self::MODEL_GPT5_NANO   => 128000,
            self::MODEL_GPT41       => 128000,
            self::MODEL_GPT41_MINI  => 128000,
            self::MODEL_GPT41_NANO  => 128000,
            self::MODEL_GPT4        => 8192,
            self::MODEL_GPT4_TURBO  => 128000,
            self::MODEL_GPT4O       => 128000,
            self::MODEL_GPT4O_MINI  => 128000,
            self::MODEL_GPT35_TURBO => 4096,
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

    public function getEnabledProviders(): array
    {
        $providers = [];

        if ($this->isProviderEnabled()) {
            $providers[] = self::PROVIDER_NAME;
        }

        return $providers;
    }

    public function getOpenAiTopP(): float
    {
        return self::DEFAULT_TOP_P;
    }

    public function getOpenAiFrequencyPenalty(): float
    {
        return self::DEFAULT_FREQUENCY_PENALTY;
    }

    public function getOpenAiPresencePenalty(): float
    {
        return self::DEFAULT_PRESENCE_PENALTY;
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
        return self::TOKEN_LIMITS[$model] ?? self::DEFAULT_OPENAI_MAX_TOKENS;
    }

    public function getBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    public function getAvailableModels(): array
    {
        return [
            self::MODEL_GPT55_PRO   => 'GPT-5.5 Pro',
            self::MODEL_GPT55       => 'GPT-5.5',
            self::MODEL_GPT54_PRO   => 'GPT-5.4 Pro',
            self::MODEL_GPT54       => 'GPT-5.4',
            self::MODEL_GPT52_PRO   => 'GPT-5.2 Pro',
            self::MODEL_GPT52       => 'GPT-5.2',
            self::MODEL_GPT51       => 'GPT-5.1',
            self::MODEL_GPT5        => 'GPT-5',
            self::MODEL_GPT5_MINI   => 'GPT-5 Mini',
            self::MODEL_GPT5_NANO   => 'GPT-5 Nano',
            self::MODEL_GPT41       => 'GPT-4.1',
            self::MODEL_GPT41_MINI  => 'GPT-4.1 Mini',
            self::MODEL_GPT41_NANO  => 'GPT-4.1 Nano',
            self::MODEL_GPT4O       => 'GPT-4o',
            self::MODEL_GPT4O_MINI  => 'GPT-4o Mini',
            self::MODEL_GPT4_TURBO  => 'GPT-4 Turbo',
            self::MODEL_GPT4        => 'GPT-4',
            self::MODEL_GPT35_TURBO => 'GPT-3.5 Turbo',
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

        return !empty($model) ? $model : self::MODEL_GPT4O_MINI;
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

        if (strpos($encryptedValue, 'sk-') === 0) {
            return $encryptedValue;
        }

        return $this->encryptor->decrypt($encryptedValue);
    }
}
