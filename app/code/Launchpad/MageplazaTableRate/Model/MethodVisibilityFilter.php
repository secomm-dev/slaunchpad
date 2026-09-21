<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — per-method customer visibility filter.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model;

use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Shipping\Model\Rate\Result;

/**
 * Hides Mageplaza methods flagged show_to_customer=0 from the NORMAL checkout result of the
 * mptablerate carrier — at the single outer rate-collection seam, so storefront, REST and
 * GraphQL all see the same method set. Carrier visibility (GHN/GHTK/…) is never touched
 * (directive §10: membership controls nothing but fallback grouping).
 *
 * Methods without a settings row are native Mageplaza methods and stay visible.
 */
class MethodVisibilityFilter
{
    private const CARRIER_CODE = 'mptablerate';

    public function __construct(
        private readonly MethodSettingsProvider $settingsProvider
    ) {
    }

    public function filter(Result $result): void
    {
        $hiddenMethodIds = $this->settingsProvider->getHiddenMethodIds();
        if ($hiddenMethodIds === []) {
            return;
        }

        $kept = [];
        $changed = false;
        foreach ($result->getAllRates() as $rate) {
            if ($rate instanceof Method
                && $rate->getCarrier() === self::CARRIER_CODE
                && isset($hiddenMethodIds[(int) $rate->getMethod()])
            ) {
                $changed = true;

                continue;
            }
            $kept[] = $rate;
        }

        if (!$changed) {
            return;
        }

        $result->reset();
        foreach ($kept as $rate) {
            $result->append($rate);
        }
    }
}
