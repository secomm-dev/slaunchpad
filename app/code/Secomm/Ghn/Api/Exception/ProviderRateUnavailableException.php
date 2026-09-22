<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Api\Exception;

/**
 * SPEC-FEAT-FQWEQ3 §11 — GHN cannot quote a fee for the requested route/service (e.g. no service
 * available for the destination). A business "no rate" answer, not a technical failure.
 */
class ProviderRateUnavailableException extends GhnApiException
{
}
