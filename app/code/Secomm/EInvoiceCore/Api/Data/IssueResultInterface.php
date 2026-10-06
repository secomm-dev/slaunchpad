<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api\Data;

/**
 * Result of an invoice issue attempt.
 *
 * @api
 * @since 1.0.0
 */
interface IssueResultInterface
{
    public const SUCCESS = 'success';
    public const EXTERNAL_ID = 'external_id';
    public const MESSAGE = 'message';
    public const RAW_RESPONSE = 'raw_response';

    /**
     * Whether the provider accepted the invoice issuance.
     *
     * @return bool
     */
    public function isSuccess(): bool;

    /**
     * Set success flag.
     *
     * @param bool $success
     * @return $this
     */
    public function setSuccess(bool $success): self;

    /**
     * External reference ID from the provider, if any.
     *
     * @return string|null
     */
    public function getExternalId(): ?string;

    /**
     * Set external reference ID.
     *
     * @param string|null $externalId
     * @return $this
     */
    public function setExternalId(?string $externalId): self;

    /**
     * Human-readable outcome message.
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Set outcome message.
     *
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): self;

    /**
     * Raw provider response for auditing.
     *
     * @return array<string, mixed>
     */
    public function getRawResponse(): array;

    /**
     * Set raw provider response.
     *
     * @param array $rawResponse
     * @return $this
     */
    public function setRawResponse(array $rawResponse): self;
}
