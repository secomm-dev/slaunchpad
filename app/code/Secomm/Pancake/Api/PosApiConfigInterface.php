<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Api;

/**
 * POS API credentials/settings supplied by the Magento wiring module (scope config).
 */
interface PosApiConfigInterface
{
    /**
     * Whether Pancake POS integration is enabled for the store.
     *
     * @param int|null $storeId Store scope; null uses default config scope
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * POS API base URL without trailing slash.
     *
     * @param int|null $storeId Store scope; null uses default config scope
     */
    public function getBaseUrl(?int $storeId = null): string;

    /**
     * Pancake shop id.
     *
     * @param int|null $storeId Store scope; null uses default config scope
     */
    public function getShopId(?int $storeId = null): string;

    /**
     * Decrypted API key (never log).
     *
     * @param int|null $storeId Store scope; null uses default config scope
     */
    public function getApiKey(?int $storeId = null): string;
}
