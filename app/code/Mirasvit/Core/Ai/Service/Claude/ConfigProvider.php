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

namespace Mirasvit\Core\Ai\Service\Claude;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Mirasvit\Core\Ai\Service\ProviderConfigInterface;
use Mirasvit\Core\Ai\Model\ConfigProvider as CoreAiConfigProvider;

class ConfigProvider implements ProviderConfigInterface
{
    public const PROVIDER_NAME = 'claude';
    public const PROVIDER_LABEL = 'Anthropic Claude';

    public const CONFIG_PATH_API_KEY = 'mst_core/ai/claude/api_key';
    public const CONFIG_PATH_MODEL   = 'mst_core/ai/claude/model';
    public const CONFIG_PATH_ENABLED = 'mst_core/ai/claude/enabled';

    public const MODEL_CLAUDE_OPUS_4_8          = 'claude-opus-4-8';
    public const MODEL_CLAUDE_OPUS_4_6          = 'claude-opus-4-6';
    public const MODEL_CLAUDE_SONNET_4_6        = 'claude-sonnet-4-6';
    public const MODEL_CLAUDE_OPUS_4_5          = 'claude-opus-4-5';
    public const MODEL_CLAUDE_SONNET_4_5        = 'claude-sonnet-4-5';
    public const MODEL_CLAUDE_HAIKU_4_5         = 'claude-haiku-4-5';
    public const MODEL_CLAUDE_OPUS_4_1          = 'claude-opus-4-1';
    public const MODEL_CLAUDE_OPUS_4            = 'claude-opus-4-0';
    public const MODEL_CLAUDE_SONNET_4          = 'claude-sonnet-4-0';
    public const MODEL_CLAUDE_3_7_SONNET_LATEST = 'claude-3-7-sonnet-latest';
    public const MODEL_CLAUDE_3_5_HAIKU_LATEST  = 'claude-3-5-haiku-latest';

    public const ENDPOINT_MESSAGES = '/v1/messages';

    public const DEFAULT_CLAUDE_TEMPERATURE = 0.7;
    public const DEFAULT_CLAUDE_MAX_TOKENS  = 1000;
    public const DEFAULT_TOP_P              = 1.0;
    public const DEFAULT_TOP_K              = 0;

    public const TOKEN_LIMITS
        = [
            self::MODEL_CLAUDE_OPUS_4_8          => 64000,
            self::MODEL_CLAUDE_OPUS_4_6          => 64000,
            self::MODEL_CLAUDE_SONNET_4_6        => 64000,
            self::MODEL_CLAUDE_OPUS_4_5          => 64000,
            self::MODEL_CLAUDE_SONNET_4_5        => 64000,
            self::MODEL_CLAUDE_HAIKU_4_5         => 64000,
            self::MODEL_CLAUDE_OPUS_4_1          => 32000,
            self::MODEL_CLAUDE_OPUS_4            => 32000,
            self::MODEL_CLAUDE_SONNET_4          => 64000,
            self::MODEL_CLAUDE_3_7_SONNET_LATEST => 64000,
            self::MODEL_CLAUDE_3_5_HAIKU_LATEST  => 8192,
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

    public function getClaudeTopP(): float
    {
        return self::DEFAULT_TOP_P;
    }

    public function getClaudeTopK(): int
    {
        return self::DEFAULT_TOP_K;
    }

    public function getAllModels(): array
    {
        return array_keys($this->getAvailableModels());
    }

    public function getEndpoint(): string
    {
        return self::ENDPOINT_MESSAGES;
    }

    public function getTokenLimitForModel(string $model): int
    {
        return self::TOKEN_LIMITS[$model] ?? self::DEFAULT_CLAUDE_MAX_TOKENS;
    }

    public function getBaseUrl(): string
    {
        return 'https://api.anthropic.com';
    }

    public function getAvailableModels(): array
    {
        return [
            self::MODEL_CLAUDE_OPUS_4_8          => 'Claude Opus 4.8',
            self::MODEL_CLAUDE_OPUS_4_6          => 'Claude Opus 4.6',
            self::MODEL_CLAUDE_SONNET_4_6        => 'Claude Sonnet 4.6',
            self::MODEL_CLAUDE_OPUS_4_5          => 'Claude Opus 4.5',
            self::MODEL_CLAUDE_SONNET_4_5        => 'Claude Sonnet 4.5',
            self::MODEL_CLAUDE_HAIKU_4_5         => 'Claude Haiku 4.5',
            self::MODEL_CLAUDE_OPUS_4_1          => 'Claude Opus 4.1',
            self::MODEL_CLAUDE_OPUS_4            => 'Claude Opus 4',
            self::MODEL_CLAUDE_SONNET_4          => 'Claude Sonnet 4',
            self::MODEL_CLAUDE_3_7_SONNET_LATEST => 'Claude Sonnet 3.7',
            self::MODEL_CLAUDE_3_5_HAIKU_LATEST  => 'Claude Haiku 3.5',
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

        return !empty($model) ? $model : self::MODEL_CLAUDE_3_5_HAIKU_LATEST;
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

        if (strpos($encryptedValue, 'sk-ant-') === 0) {
            return $encryptedValue;
        }

        return $this->encryptor->decrypt($encryptedValue);
    }
}
