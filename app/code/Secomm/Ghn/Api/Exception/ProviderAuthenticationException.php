<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Api\Exception;

/**
 * SPEC-FEAT-FQWEQ3 §11 — token/ShopId rejected by GHN (HTTP 401/403 or provider auth error code).
 */
class ProviderAuthenticationException extends GhnApiException
{
}
