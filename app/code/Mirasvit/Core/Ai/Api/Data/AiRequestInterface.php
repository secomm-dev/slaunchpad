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

interface AiRequestInterface
{
    public function getPrompt();

    public function setPrompt($prompt): self;

    public function getModel(): string;

    public function setModel(string $model): self;

    public function getProvider(): string;

    public function setProvider(string $provider): self;

    public function getMaxTokens(): ?int;

    public function setMaxTokens(int $maxTokens): self;

    public function getTemperature(): float;

    public function setTemperature(float $temperature): self;

    public function getContext(): array;

    public function setContext(array $context): self;

    public function getParameters(): array;

    public function setParameters(array $parameters): self;

    public function addParameter(string $key, $value): self;

    public function getSystemPrompt(): ?string;

    public function setSystemPrompt(?string $systemPrompt): self;

    public function getStopSequences(): ?string;

    public function setStopSequences(?string $stopSequences): self;

    public function getFrequencyPenalty(): ?float;

    public function setFrequencyPenalty(?float $frequencyPenalty): self;

    public function getApiKey(): ?string;

    public function setApiKey(?string $apiKey): self;

    /**
     * @return array<array{type: string, source: string, media_type: string, url?: string, data?: string, filename?: string}>
     */
    public function getAttachments(): array;

    /**
     * @param array<array{type: string, source: string, media_type: string, url?: string, data?: string, filename?: string}> $attachments
     */
    public function setAttachments(array $attachments): self;

    public function addAttachment(array $attachment): self;

    /** @return string[] */
    public function getTools(): array;

    /** @param string[] $tools */
    public function setTools(array $tools): self;

    public function isValid(): bool;
}
