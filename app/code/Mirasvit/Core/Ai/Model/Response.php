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

use Mirasvit\Core\Ai\Api\Data\AiResponseInterface;

class Response implements AiResponseInterface
{
    private $content          = '';

    private $status           = self::STATUS_SUCCESS;

    private $errorMessage     = null;

    private $errorCode        = null;

    private $usageData        = [];

    private $metadata         = [];

    private $provider         = '';

    private $model            = '';

    private $createdAt        = '';

    private $finishReason     = null;

    private $promptTokens     = 0;

    private $completionTokens = 0;

    public function __construct()
    {
        $this->createdAt = date('Y-m-d H:i:s');
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): AiResponseInterface
    {
        $this->content = $content;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): AiResponseInterface
    {
        $this->status = $status;

        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function hasError(): bool
    {
        return !$this->isSuccess();
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): AiResponseInterface
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): AiResponseInterface
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    public function getUsageData(): array
    {
        return $this->usageData;
    }

    public function setUsageData(array $usageData): AiResponseInterface
    {
        $this->usageData = $usageData;

        return $this;
    }

    public function getTokensUsed(): int
    {
        return (int)($this->usageData['total_tokens'] ?? $this->usageData['tokens'] ?? 0);
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): AiResponseInterface
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): AiResponseInterface
    {
        $this->provider = $provider;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): AiResponseInterface
    {
        $this->model = $model;

        return $this;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(string $createdAt): AiResponseInterface
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

    public function setFinishReason(?string $finishReason): AiResponseInterface
    {
        $this->finishReason = $finishReason;

        return $this;
    }

    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    public function setPromptTokens(int $promptTokens): AiResponseInterface
    {
        $this->promptTokens = $promptTokens;

        return $this;
    }

    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    public function setCompletionTokens(int $completionTokens): AiResponseInterface
    {
        $this->completionTokens = $completionTokens;

        return $this;
    }
}
