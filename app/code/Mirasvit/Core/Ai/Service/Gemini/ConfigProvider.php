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

namespace Mirasvit\Core\Ai\Service\Gemini;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Mirasvit\Core\Ai\Service\ProviderConfigInterface;
use Mirasvit\Core\Ai\Model\ConfigProvider as CoreAiConfigProvider;

class ConfigProvider implements ProviderConfigInterface
{
    public const PROVIDER_NAME = 'gemini';
    public const PROVIDER_LABEL = 'Google Gemini';

    public const CONFIG_PATH_API_KEY = 'mst_core/ai/gemini/api_key';
    public const CONFIG_PATH_MODEL   = 'mst_core/ai/gemini/model';
    public const CONFIG_PATH_ENABLED = 'mst_core/ai/gemini/enabled';

    public const MODEL_GEMINI_3_5_FLASH      = 'gemini-3.5-flash';
    public const MODEL_GEMINI_3_1_PRO        = 'gemini-3.1-pro-preview';
    public const MODEL_GEMINI_3_1_FLASH_LITE = 'gemini-3.1-flash-lite-preview';
    public const MODEL_GEMINI_3_FLASH        = 'gemini-3-flash-preview';

    public const MODEL_GEMINI_2_5_PRO        = 'gemini-2.5-pro';
    public const MODEL_GEMINI_2_5_FLASH      = 'gemini-2.5-flash';
    public const MODEL_GEMINI_2_5_FLASH_LITE = 'gemini-2.5-flash-lite';

    public const MODEL_GEMINI_2_0_FLASH      = 'gemini-2.0-flash';
    public const MODEL_GEMINI_2_0_FLASH_LITE = 'gemini-2.0-flash-lite';

    public const DEPRECATED_MODEL_MAP = [
        'gemini-3-pro-preview' => self::MODEL_GEMINI_3_1_PRO,
    ];

    public const ENDPOINT_GENERATE_CONTENT = '/v1beta/models/{model}:generateContent';

    public const DEFAULT_GEMINI_TEMPERATURE = 0.7;
    public const DEFAULT_GEMINI_MAX_TOKENS  = 8192;
    public const DEFAULT_TOP_P              = 1.0;
    public const DEFAULT_TOP_K              = 40;

    public const TOKEN_LIMITS
        = [
            self::MODEL_GEMINI_3_5_FLASH      => 1048576,
            self::MODEL_GEMINI_3_1_PRO        => 1048576,
            self::MODEL_GEMINI_3_1_FLASH_LITE => 1048576,
            self::MODEL_GEMINI_3_FLASH        => 1048576,
            self::MODEL_GEMINI_2_5_PRO        => 1048576,
            self::MODEL_GEMINI_2_5_FLASH      => 1048576,
            self::MODEL_GEMINI_2_5_FLASH_LITE => 1048576,
            self::MODEL_GEMINI_2_0_FLASH      => 1048576,
            self::MODEL_GEMINI_2_0_FLASH_LITE => 1048576,
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

    public function getGeminiTopP(): float
    {
        return self::DEFAULT_TOP_P;
    }

    public function getGeminiTopK(): int
    {
        return self::DEFAULT_TOP_K;
    }

    public function resolveModel(string $model): string
    {
        return self::DEPRECATED_MODEL_MAP[$model] ?? $model;
    }

    public function getAllModels(): array
    {
        return array_keys($this->getAvailableModels());
    }

    public function getEndpoint(): string
    {
        return self::ENDPOINT_GENERATE_CONTENT;
    }

    public function getTokenLimitForModel(string $model): int
    {
        return self::TOKEN_LIMITS[$model] ?? self::DEFAULT_GEMINI_MAX_TOKENS;
    }

    public function getBaseUrl(): string
    {
        return 'https://generativelanguage.googleapis.com';
    }

    public function getAvailableModels(): array
    {
        return [
            self::MODEL_GEMINI_3_5_FLASH      => 'Gemini 3.5 Flash',
            self::MODEL_GEMINI_3_1_PRO        => 'Gemini 3.1 Pro',
            self::MODEL_GEMINI_3_1_FLASH_LITE => 'Gemini 3.1 Flash Lite',
            self::MODEL_GEMINI_3_FLASH        => 'Gemini 3 Flash',
            self::MODEL_GEMINI_2_5_PRO        => 'Gemini 2.5 Pro',
            self::MODEL_GEMINI_2_5_FLASH      => 'Gemini 2.5 Flash',
            self::MODEL_GEMINI_2_5_FLASH_LITE => 'Gemini 2.5 Flash Lite',
            self::MODEL_GEMINI_2_0_FLASH      => 'Gemini 2.0 Flash',
            self::MODEL_GEMINI_2_0_FLASH_LITE => 'Gemini 2.0 Flash Lite',
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

        if (empty($model)) {
            return self::MODEL_GEMINI_2_5_FLASH;
        }

        return self::DEPRECATED_MODEL_MAP[$model] ?? $model;
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

        if (strpos($encryptedValue, 'AIza') === 0) {
            return $encryptedValue;
        }

        return $this->encryptor->decrypt($encryptedValue);
    }
}
