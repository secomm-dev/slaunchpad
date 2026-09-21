<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Api\Exception;

/**
 * TASK-GKHXY1 r2 — provider rejected the request BEFORE processing because a usage/quota
 * throttle fired (HTTP 429). Unlike a 5xx, a 429 is definitive evidence the operation was NOT
 * applied provider-side, which is why mutation services may classify it TECHNICAL_FAILURE
 * (retryable after the window clears — still never retried automatically).
 *
 * Split from ProviderServiceUnavailableException so mutation services can honour the r2
 * uncertainty taxonomy: 429 → TECHNICAL_FAILURE, 5xx → UNKNOWN_RESULT.
 */
class ProviderRateLimitException extends GhnApiException
{
}
