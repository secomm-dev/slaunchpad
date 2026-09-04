<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api;

use Secomm\AddressDropdown\Api\Data\AddressProfileInterface;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — resolves the active Address Profile for a country (+ optional context).
 *
 * Resolution is a plain store-scoped config lookup `address/profiles/mapping` (countryId => profile
 * code) — deliberately NOT a rules engine (DEC-FEAT2PZQKJ-001 Decision 3).
 */
interface AddressProfileResolverInterface
{
    /**
     * Generic contexts (advisory; per-context override is not implemented — reserved for future config).
     */
    public const CONTEXT_CUSTOMER_ADDRESS = 'customer_address';
    public const CONTEXT_CHECKOUT = 'checkout';
    public const CONTEXT_ADMIN_ADDRESS = 'admin_address';
    public const CONTEXT_INTEGRATION = 'integration';

    /**
     * Resolve the active profile for a country in the current store scope.
     *
     * - Country not mapped => null => caller renders native Magento address fields (no cascade).
     * - Country mapped to a profile code that no module declares => null + warning log (config fault
     *   must never break the storefront path).
     *
     * @param string $countryId
     * @param string|null $context Reserved per-context override key; current implementations resolve
     *                             identically for every context.
     * @return AddressProfileInterface|null
     */
    public function resolve(string $countryId, ?string $context = null): ?AddressProfileInterface;
}
