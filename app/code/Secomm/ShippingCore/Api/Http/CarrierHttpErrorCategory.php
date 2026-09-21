<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Http;

/**
 * TASK-7AJ3K8 (DEC-TASK7AJ3K8-001 §1) — the ONE shared transport error taxonomy. Carrier
 * exceptions translate their own details INTO these categories; business semantics (retry or
 * not) are decided by carrier retry policies against the category — never by string matching
 * on messages.
 */
final class CarrierHttpErrorCategory
{
    /** Connection/socket timeout. */
    public const TIMEOUT = 'TIMEOUT';

    /** Transport-level failure before/without an HTTP status (DNS, refused, reset, status 0). */
    public const NETWORK = 'NETWORK';

    /** HTTP 429 — classified, never auto-retried by the shared primitive. */
    public const RATE_LIMIT = 'RATE_LIMIT';

    /** HTTP 5xx. */
    public const SERVER_ERROR = 'SERVER_ERROR';

    /** Other HTTP 4xx. */
    public const CLIENT_ERROR = 'CLIENT_ERROR';

    /** Body present but not the expected format (e.g. invalid JSON). */
    public const INVALID_RESPONSE = 'INVALID_RESPONSE';

    private function __construct()
    {
    }
}
