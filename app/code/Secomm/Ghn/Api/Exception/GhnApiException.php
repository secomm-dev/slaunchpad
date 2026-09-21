<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Api\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * SPEC-FEAT-FQWEQ3 §11 — base class of the GHN provider exception taxonomy. Raw GHN errors never
 * leak past the client boundary: GhnApiClient translates them into the typed subclasses. ShippingCore
 * never sees these classes — carrier adapters translate them into outcome statuses + failure reasons
 * at the boundary (foundation uses outcome semantics, not provider exception types).
 */
abstract class GhnApiException extends LocalizedException
{
}
