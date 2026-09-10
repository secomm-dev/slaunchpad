<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

/**
 * POS adapter flag that decides whether FulfillmentCore may write that POS log folder.
 */
interface PosLogConfigInterface
{
    /**
     * Stable service key. Must match the log folder name (example: pancake).
     */
    public function getServiceCode(): string;

    /**
     * Whether this POS module has Enable log turned on.
     *
     * @param int|null $storeId Store scope; null uses default config scope
     */
    public function isLogEnabled(?int $storeId = null): bool;
}
