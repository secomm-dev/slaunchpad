<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Api\Exception;

/**
 * SPEC-FEAT-FQWEQ3 §11 — GHN side cannot serve the request right now (5xx, rate limited, route
 * temporarily unavailable).
 */
class ProviderServiceUnavailableException extends GhnApiException
{
}
