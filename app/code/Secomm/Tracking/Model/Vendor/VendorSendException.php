<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Vendor;

/**
 * FEAT-31X6N2 — vendor transport/API failure. Carries retryability so the flush
 * service can fail fast on 4xx and back off on 5xx (spec §11).
 */
class VendorSendException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly bool $retryable = true,
        public readonly ?string $responseSummary = null
    ) {
        parent::__construct($message);
    }
}
