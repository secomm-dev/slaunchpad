<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api\Data;

/**
 * Result of OrderExporterInterface::export().
 */
interface ExportResultInterface
{
    public function isSuccess(): bool;

    /**
     * Remote OMS order id when successful; null on failure.
     */
    public function getExternalOrderId(): ?string;

    /**
     * Generic error code (never PII/secrets). Null on success.
     */
    public function getErrorCode(): ?string;
}
