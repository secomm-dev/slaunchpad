<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * SPEC-FEAT-FQWEQ3 §7 — runtime resolution failure: no APPROVED mapping for the canonical unit
 * (unmapped / ambiguous-never-auto-approved / provider unit disabled). Fail closed — callers turn
 * this into "method unavailable", never into a guessed address (BUG-JBX3H9 legacy precedent).
 */
class GhnMappingNotFoundException extends LocalizedException
{
}
