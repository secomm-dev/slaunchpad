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

use Mirasvit\Core\Ai\Api\Data\AiRequestInterface;

class Request implements AiRequestInterface
{
    private         $prompt           = '';

    private $model            = '';

    private $provider         = '';

    private $maxTokens        = null;

    private $temperature      = 0.7;

    private $systemPrompt     = null;

    private $context          = [];

    private $parameters       = [];

    private $stopSequences    = null;

    private $frequencyPenalty = null;

    private $apiKey           = null;

    private $attachments      = [];

    private $tools            = [];

    public function getPrompt()
    {
        return $this->prompt;
    }

    public function setPrompt($prompt): AiRequestInterface
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): AiRequestInterface
    {
        $this->model = $model;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): AiRequestInterface
    {
        $this->provider = $provider;

        return $this;
    }

    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function setMaxTokens(int $maxTokens): AiRequestInterface
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): AiRequestInterface
    {
        $this->temperature = $temperature;

        return $this;
    }


    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): AiRequestInterface
    {
        $this->context = $context;

        return $this;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): AiRequestInterface
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function addParameter(string $key, $value): AiRequestInterface
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function isValid(): bool
    {
        if (empty($this->prompt)) {
            return false;
        }

        if (empty($this->model)) {
            return false;
        }

        if (empty($this->provider)) {
            return false;
        }

        if ($this->maxTokens !== null && $this->maxTokens <= 0) {
            return false;
        }

        if ($this->temperature < 0.0 || $this->temperature > 2.0) {
            return false;
        }

        return true;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function setSystemPrompt(?string $systemPrompt): AiRequestInterface
    {
        $this->systemPrompt = $systemPrompt;

        return $this;
    }

    public function getStopSequences(): ?string
    {
        return $this->stopSequences;
    }

    public function setStopSequences(?string $stopSequences): AiRequestInterface
    {
        $this->stopSequences = $stopSequences;

        return $this;
    }

    public function getFrequencyPenalty(): ?float
    {
        return $this->frequencyPenalty;
    }

    public function setFrequencyPenalty(?float $frequencyPenalty): AiRequestInterface
    {
        $this->frequencyPenalty = $frequencyPenalty;

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): AiRequestInterface
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function setAttachments(array $attachments): AiRequestInterface
    {
        $this->attachments = $attachments;

        return $this;
    }

    public function addAttachment(array $attachment): AiRequestInterface
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function setTools(array $tools): AiRequestInterface
    {
        $this->tools = $tools;

        return $this;
    }
}
