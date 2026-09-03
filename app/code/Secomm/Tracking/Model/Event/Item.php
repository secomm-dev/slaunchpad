<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Event;

/**
 * FEAT-31X6N2 / SPEC-FEAT-31X6N2 §4 — one line item of a TrackingEvent.
 * Prices are display-currency values (the chain the merchant reconciles against).
 */
final class Item
{
    /**
     * @param string $itemId SKU (vendor adapters map to content id)
     * @param string $itemName Product name
     * @param string|null $itemCategory First category name
     * @param float $price Unit price, display currency
     * @param float $quantity Quantity
     */
    public function __construct(
        public readonly string $itemId,
        public readonly string $itemName,
        public readonly ?string $itemCategory,
        public readonly float $price,
        public readonly float $quantity
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'item_name' => $this->itemName,
            'item_category' => $this->itemCategory,
            'price' => $this->price,
            'quantity' => $this->quantity,
        ];
    }
}
