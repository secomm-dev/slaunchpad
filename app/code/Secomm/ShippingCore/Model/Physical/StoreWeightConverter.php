<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Physical;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

/**
 * DEC-TASK9Q5ZAK-001 — the ONE store-weight-unit → grams conversion for carrier operations
 * (moved from Secomm_Ghn Model\Unit — dependency direction Ghn→ShippingCore requires shared
 * services to live here).
 *
 * Fail-closed semantics: the store weight unit (`general/locale/weight_unit` — Magento core
 * ships 'kgs' | 'lbs') decides whether the raw product weight reads as kilograms or pounds; a
 * missing/unexpected unit is NEVER guessed — the operation is refused (LocalizedException →
 * INVALID_CONFIGURATION at the carrier boundary).
 */
class StoreWeightConverter
{
    /** Store weight-unit values shipped by Magento core. */
    public const UNIT_KGS = 'kgs';
    public const UNIT_LBS = 'lbs';

    /** One pound in grams (exact conversion factor). */
    private const GRAMS_PER_LB = 453.59237;

    /** One kilogram in grams. */
    private const GRAMS_PER_KG = 1000.0;

    private const XML_PATH_WEIGHT_UNIT = 'general/locale/weight_unit';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * @throws LocalizedException when the store weight unit is missing/unrecognized
     */
    public function toGrams(float $weight, ?int $storeId = null): float
    {
        return $weight * match ($this->resolveWeightUnit($storeId)) {
            self::UNIT_LBS => self::GRAMS_PER_LB,
            default => self::GRAMS_PER_KG,
        };
    }

    private function resolveWeightUnit(?int $storeId): string
    {
        $unit = strtolower(trim(
            (string) $this->scopeConfig->getValue(self::XML_PATH_WEIGHT_UNIT, ScopeInterface::SCOPE_STORE, $storeId)
        ));

        if ($unit === self::UNIT_KGS || $unit === self::UNIT_LBS) {
            return $unit;
        }

        throw new LocalizedException(__(
            'Shipping weight: store weight unit must be "kgs" or "lbs" '
            . '(Stores → Configuration → General → Locale Options → Weight Unit); got "%1".',
            $unit
        ));
    }
}
