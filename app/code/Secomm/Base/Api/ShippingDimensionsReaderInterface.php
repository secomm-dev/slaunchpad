<?php
/**
 * Copyright © Secomm All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\Base\Api;

use Magento\Quote\Model\Quote\Item;
use Secomm\Base\Api\Data\ShippingDimensions;

/**
 * TASK-RT50KH — product shipping-dimension read contract (P1, DEC-TASKRT50KH-001).
 *
 * Contract semantics:
 * - Values represent ONE sellable unit's PACKED shipping dimensions (not naked product
 *   dimensions), in centimeters.
 * - Authoritative ONLY when all three values are present, numeric and > 0; each is CEILed to
 *   a whole centimeter (conservative at carrier boundaries — 149.2cm stays quotable at a
 *   150cm limit, 150.1cm becomes 151 and rejects).
 * - Anything else (partial set, zero/negative, non-numeric, blank) → null = "missing":
 *   consumers must NOT reject on missing dimensions and must NOT substitute defaults.
 * - Composite resolution is part of the read: a ship-together configurable item yields the
 *   SELECTED simple child's dimensions, a ship-together bundle yields the bundle's own
 *   (merchant-maintained) dimensions, ship-separately children yield their own product.
 * - Never throws.
 */
interface ShippingDimensionsReaderInterface
{
    public function read(Item $item): ?ShippingDimensions;
}
