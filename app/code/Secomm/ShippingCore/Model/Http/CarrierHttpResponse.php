<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Http;

/**
 * TASK-7AJ3K8 — immutable transport response DTO (2xx only — the client throws on everything
 * else). Body is the raw response payload; JSON interpretation stays with the caller unless
 * the caller used sendJson().
 */
final class CarrierHttpResponse
{
    public function __construct(
        private readonly int $status,
        private readonly string $body
    ) {
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
