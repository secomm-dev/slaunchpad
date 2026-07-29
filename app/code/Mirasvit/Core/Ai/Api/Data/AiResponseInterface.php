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

namespace Mirasvit\Core\Ai\Api\Data;

interface AiResponseInterface
{
    public const FIELD_CONTENT       = 'content';
    public const FIELD_STATUS        = 'status';
    public const FIELD_ERROR_MESSAGE = 'error_message';
    public const FIELD_ERROR_CODE    = 'error_code';
    public const FIELD_USAGE_DATA    = 'usage_data';
    public const FIELD_METADATA      = 'metadata';
    public const FIELD_PROVIDER      = 'provider';
    public const FIELD_MODEL         = 'model';
    public const FIELD_CREATED_AT    = 'created_at';

    public const STATUS_SUCCESS      = 'success';
    public const STATUS_ERROR        = 'error';
    public const STATUS_TIMEOUT      = 'timeout';
    public const STATUS_RATE_LIMITED = 'rate_limited';

    public function getContent(): string;

    public function setContent(string $content): self;

    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function isSuccess(): bool;

    public function hasError(): bool;

    public function getErrorMessage(): ?string;

    public function setErrorMessage(?string $errorMessage): self;

    public function getErrorCode(): ?string;

    public function setErrorCode(?string $errorCode): self;

    public function getUsageData(): array;

    public function setUsageData(array $usageData): self;

    public function getTokensUsed(): int;

    public function getMetadata(): array;

    public function setMetadata(array $metadata): self;

    public function getProvider(): string;

    public function setProvider(string $provider): self;

    public function getModel(): string;

    public function setModel(string $model): self;

    public function getCreatedAt(): string;

    public function setCreatedAt(string $createdAt): self;

    public function getFinishReason(): ?string;

    public function setFinishReason(?string $finishReason): self;

    public function getPromptTokens(): int;

    public function setPromptTokens(int $promptTokens): self;

    public function getCompletionTokens(): int;

    public function setCompletionTokens(int $completionTokens): self;
}