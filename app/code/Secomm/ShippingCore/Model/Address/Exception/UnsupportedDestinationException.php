<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * TASK-5XDG1P — the resolution manager was asked to resolve a destination outside the Vietnam
 * canonical scope (non-VN countryId).
 *
 * Explicit not-applicable channel (Phase E-B directive §6): the canonical 4-status result
 * contract (EXACT/MAPPED/AMBIGUOUS/UNMAPPED) describes Vietnam canonical-resolution outcomes
 * ONLY — a non-Vietnam destination is never mislabeled UNMAPPED and no fifth status is
 * introduced for it. Pattern follows the module exception style of
 * GhnLocationMappingException (fail-closed, extends LocalizedException). Callers in later
 * carrier rate/submit phases filter by destination country before invoking the manager.
 */
class UnsupportedDestinationException extends LocalizedException
{
}
