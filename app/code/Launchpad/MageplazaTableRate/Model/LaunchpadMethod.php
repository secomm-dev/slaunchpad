<?php
/*
 * TASK-SEC-C3 — stateless-channel customer-group resolution for Mageplaza methods.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model;

use Mageplaza\TableRateShipping\Model\Method;

/**
 * Vendor `Method::isActive()` derives the customer group from the AMBIENT customer session —
 * correct on storefront/admin surfaces, but a plain REST token request has no session bridge,
 * so an authenticated customer is evaluated AS GUEST (NOT_LOGGED_IN).
 *
 * The override adds an OPTIONAL explicit group parameter: callers that hold a trustworthy
 * context (the fallback provider resolves it from the rate request's quote) pass it in and
 * bypass the session entirely; every existing caller (Mageplaza standalone, native carrier)
 * keeps the untouched session semantics — backward compatible by signature.
 */
class LaunchpadMethod extends Method
{
    /**
     * @param mixed $storeId
     * @param int|null $customerGroupId explicit, context-resolved group (stateless channels)
     */
    public function isActive($storeId = 0, ?int $customerGroupId = null): bool
    {
        if ($customerGroupId === null) {
            return parent::isActive($storeId);
        }

        $stores = explode(',', (string) $this->getStoreId());
        $groups = explode(',', (string) $this->getCustomerGroup());

        return (int) $this->getStatus() === 1
            && (bool) array_intersect([$storeId, 0], $stores)
            && (bool) array_intersect([$customerGroupId], $groups);
    }
}
