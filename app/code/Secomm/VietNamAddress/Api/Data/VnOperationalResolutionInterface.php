<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Api\Data;

/**
 * DEC-FEATYA2C0W-004 (D5) / TASK-Q4B98P — outcome of one bridge call. Business-level
 * misses return an unresolved outcome + machine-readable reason; exceptions are reserved
 * for hard failures (misconfiguration of the caller, broken DI).
 */
interface VnOperationalResolutionInterface
{
    /** Caller passed no/zero ids or an empty/unknown scheme code. */
    public const REASON_INVALID_INPUT = 'invalid_input';

    /** Configured active_scheme and the scheme registry disagree, or nothing is installed. */
    public const REASON_SCHEME_NOT_ACTIVE = 'scheme_not_active';

    /** Canonical unit code does not exist in the reference layer for the scheme. */
    public const REASON_UNIT_UNKNOWN = 'unit_unknown';

    /** No runtime row for the given id / canonical unit in the active scheme's dataset. */
    public const REASON_RUNTIME_ROW_MISSING = 'runtime_row_missing';

    /** Runtime row exists but carries no dataset code (legacy stray — see import cleanup). */
    public const REASON_RUNTIME_CODE_MISSING = 'runtime_code_missing';

    /** Runtime row exists but belongs to a non-VN region. */
    public const REASON_NOT_VN_REGION = 'not_vn_region';

    public function isResolved(): bool;

    public function getIdentity(): ?VnOperationalIdentityInterface;

    /** One of the REASON_* constants when unresolved, null when resolved. */
    public function getReason(): ?string;
}
