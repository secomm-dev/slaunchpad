<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Api\Exception;

/**
 * SPEC-FEAT-FQWEQ3 §11 — our request was malformed or the route/service is not supported by GHN
 * (HTTP 4xx / provider code 400/404 family).
 */
class ProviderInvalidRequestException extends GhnApiException
{
}
