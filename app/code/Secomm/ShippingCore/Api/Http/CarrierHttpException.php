<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Http;

/**
 * TASK-7AJ3K8 — transport failure carrying its shared category. The message is safe for logs
 * (no headers, no payload, no credentials are ever embedded by the shared client).
 */
class CarrierHttpException extends \RuntimeException
{
    public function __construct(
        private readonly string $category,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** A CarrierHttpErrorCategory constant. */
    public function getCategory(): string
    {
        return $this->category;
    }
}
