<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — city/area narrowing inside Mageplaza's rate matching.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Plugin\Rate;

use Launchpad\MageplazaTableRate\Model\City\CityRateScopeResolver;
use Mageplaza\TableRateShipping\Model\ResourceModel\Rate\Collection;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Applies the optional City/Area precedence AFTER Mageplaza's own matching, on the SAME
 * collection instance — Mageplaza then prices the surviving rows with its own formula and
 * CalculateRule. FilterByRequest has exactly two production callers (the mptablerate carrier
 * and this bridge's internal calculation), so the narrowing semantics are identical for the
 * normal checkout path and the fallback path (no duplicated matching logic anywhere).
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class CollectionFilterPlugin
{
    public function __construct(
        private readonly CityRateScopeResolver $cityRateScopeResolver
    ) {
    }

    /**
     * @param mixed $cartData Mageplaza cart scalars (weight/subtotal/qty), passed through
     */
    public function aroundFilterByRequest(Collection $subject, callable $proceed, $request, $cartData): Collection
    {
        $proceed($request, $cartData);

        if ($request instanceof RateRequest) {
            $this->cityRateScopeResolver->apply($subject, $request);
        }

        return $subject;
    }
}
