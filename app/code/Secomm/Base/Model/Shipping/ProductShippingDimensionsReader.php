<?php
/**
 * Copyright © Secomm All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\Base\Model\Shipping;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Model\Quote\Item;
use Secomm\Base\Api\Data\ShippingDimensions;
use Secomm\Base\Api\ShippingDimensionsReaderInterface;

/**
 * TASK-RT50KH — default reader for the P1 product shipping-dimension contract
 * (attributes `length` / `width` / `height`: decimal, GLOBAL, cm — DEC-TASKRT50KH-001).
 *
 * Composite resolution (the estimator hands over the ALREADY-EXPANDED shipping item):
 * - configurable, ship-together → the SELECTED simple child item carries the purchased unit
 *   (its product is the authoritative unit);
 * - bundle, ship-together → the parent item (merchant maintains bundle-level packed dims —
 *   same "one solid unit" semantics Magento already applies to the parent's weight);
 * - ship-separately bundles → the estimator hands over the simple child items directly;
 * - simple / grouped-purchased simples → own product;
 * - virtual/downloadable items never reach shipping estimation (skipped upstream).
 *
 * Completeness: a unit's dimensions are authoritative only when all three are present,
 * numeric and > 0 — they are CEILed to whole centimeters. Anything else → null ("missing"):
 * callers must not reject on missing dimensions and must not substitute defaults.
 */
final class ProductShippingDimensionsReader implements ShippingDimensionsReaderInterface
{
    /**
     * Attribute codes of the P1 shipping-dimension contract (cm).
     */
    public const ATTRIBUTE_LENGTH = 'length';

    public const ATTRIBUTE_WIDTH = 'width';

    public const ATTRIBUTE_HEIGHT = 'height';

    public function read(Item $item): ?ShippingDimensions
    {
        $product = $this->resolveProduct($item);
        if ($product === null) {
            return null;
        }

        $length = $this->toCm($product->getData(self::ATTRIBUTE_LENGTH));
        $width = $this->toCm($product->getData(self::ATTRIBUTE_WIDTH));
        $height = $this->toCm($product->getData(self::ATTRIBUTE_HEIGHT));
        if ($length === null || $width === null || $height === null) {
            // Completeness contract — a partial or invalid set is treated as missing data,
            // never as a carrier rejection and never patched with defaults.
            return null;
        }

        return new ShippingDimensions($length, $width, $height);
    }

    /**
     * The product whose shipping dimensions represent the sellable unit on this item.
     */
    private function resolveProduct(Item $item): ?ProductInterface
    {
        $product = $item->getProduct();
        if ($product === null) {
            return null;
        }

        if ($item->getProductType() === 'configurable') {
            // Ship-together configurable: the purchased unit is the SELECTED simple child.
            $children = $item->getChildren();
            $child = $children[0] ?? null;
            $childProduct = $child?->getProduct();

            return $childProduct ?? null;
        }

        // Ship-together bundles keep the parent as the single solid unit (parent-level
        // packed dims, mirroring the parent-resolved weight semantics); everything else
        // (simple, grouped-purchased simple, ship-separately child) is its own product.
        return $product;
    }

    /**
     * Numeric + > 0 → ceil to whole centimeters; anything else (missing, zero, negative,
     * non-numeric text) → null. Never guesses, never substitutes.
     */
    private function toCm(mixed $raw): ?int
    {
        if ($raw === null || is_bool($raw) || !is_numeric($raw)) {
            return null;
        }
        $value = (float) $raw;

        return $value > 0.0 ? (int) ceil($value) : null;
    }
}
