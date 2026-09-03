<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Api;

use Magento\Framework\Exception\LocalizedException;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;

/**
 * DEC-FEATYA2C0W-003 / TASK-J9AVGK — directional scheme-pair resolution over the
 * administrative mapping layer. Vietnam-specific translation lives HERE (per the §17
 * ownership matrix); Phase E ShippingCore orchestration consumes this contract.
 *
 * Semantics:
 * - source == target scheme and the unit exists in the historical layer → EXACT.
 * - cross-scheme candidates = union of outgoing edges (source side matches) and incoming
 *   edges (target side matches) — authored rows are single-direction, so the reverse of a
 *   merge (A→C, B→C; resolving C backwards) surfaces BOTH A and B as AMBIGUOUS.
 * - exactly 1 candidate → MAPPED; more → AMBIGUOUS (candidates, NEVER auto-picked);
 *   none → UNMAPPED with an explicit reason.
 *
 * Unknown scheme codes (not in the VnSchemes catalog) are configuration faults → exception.
 * Unresolvable units are NOT exceptions — they are statuses.
 */
interface VnAdminAddressResolverInterface
{
    /**
     * @throws LocalizedException unknown source or target scheme code
     */
    public function resolve(string $sourceScheme, string $sourceCode, string $targetScheme): VnAddressResolutionInterface;
}
