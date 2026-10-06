<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * Post-publish operations against a provider for an already issued invoice.
 *
 * @api
 * @since 1.0.0
 */
interface InvoiceDocumentServiceInterface
{
    public const DOWNLOAD_PDF = 'pdf';
    public const DOWNLOAD_XML = 'xml';

    /**
     * Fetch current status of an issued invoice.
     *
     * @param string $transactionId
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getStatus(string $transactionId, ?int $storeId = null): array;

    /**
     * Fetch the published invoice view payload.
     *
     * @param string $transactionId
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getPublishedView(string $transactionId, ?int $storeId = null): array;

    /**
     * Download an issued invoice file.
     *
     * @param string $transactionId
     * @param string $type One of self::DOWNLOAD_PDF or self::DOWNLOAD_XML.
     * @param int|null $storeId
     * @return array{filename: string, mime: string, contents: string}
     * @throws LocalizedException
     */
    public function download(string $transactionId, string $type, ?int $storeId = null): array;

    /**
     * Cancel an issued invoice.
     *
     * @param string $transactionId MeInvoice transaction id.
     * @param string $invSeries Invoice series (InvSeries) used when the invoice was issued.
     * @param string $reason Cancellation reason.
     * @param int|null $storeId
     * @return array{success: bool, message: string, raw: array<string, mixed>}
     * @throws LocalizedException
     */
    public function cancel(string $transactionId, string $invSeries, string $reason, ?int $storeId = null): array;

    /**
     * Send published invoice email to the customer via MeInvoice.
     *
     * @param string $transactionId
     * @param string $receiverName
     * @param string $receiverEmail
     * @param string|null $ccEmail
     * @param string|null $replyEmail
     * @param int|null $storeId
     * @return array{success: bool, message: string, raw: array<string, mixed>}
     * @throws LocalizedException
     */
    public function sendEmail(
        string $transactionId,
        string $receiverName,
        string $receiverEmail,
        ?string $ccEmail = null,
        ?string $replyEmail = null,
        ?int $storeId = null
    ): array;
}
