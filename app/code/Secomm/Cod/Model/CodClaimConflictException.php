<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Model;

use Magento\Framework\Exception\LocalizedException;

/**
 * TASK-DFGFZ9 phase 3 closure (DEC-TASKDFGFZ9-004) — the order already holds an ACTIVE
 * collection claim through a DIFFERENT (carrier, provider_reference); the engine-level
 * unique `active_order_claim` index refused this attempt's claim. Extends
 * LocalizedException so a carrier may propagate it natively (GHTK native label flow
 * aborts with this message) or translate it into its own outcome (GHN COD_REJECTED).
 */
final class CodClaimConflictException extends LocalizedException
{
}
