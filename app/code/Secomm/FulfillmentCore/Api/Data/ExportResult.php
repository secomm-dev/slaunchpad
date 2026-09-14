<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api\Data;

/**
 * Immutable export result with factory helpers.
 */
final class ExportResult implements ExportResultInterface
{
    private function __construct(
        private readonly bool $success,
        private readonly ?string $externalOrderId,
        private readonly ?string $errorCode
    ) {
    }

    /**
     * @param string $externalOrderId Remote OMS order id
     */
    public static function ok(string $externalOrderId): self
    {
        return new self(true, $externalOrderId, null);
    }

    /**
     * @param string $errorCode Generic non-PII failure code (e.g. warehouse_unmapped)
     */
    public static function fail(string $errorCode): self
    {
        return new self(false, null, $errorCode);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getExternalOrderId(): ?string
    {
        return $this->externalOrderId;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
