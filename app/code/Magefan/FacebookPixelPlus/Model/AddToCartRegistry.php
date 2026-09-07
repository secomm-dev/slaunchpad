<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Model;

class AddToCartRegistry
{
    /**
     * @var array|null
     */
    private $items;

    /**
     * Add item to registry
     *
     * @param mixed $item
     * @return void
     */
    public function addItem($item): void
    {
        if (null == $this->items) {
            $this->items = [];
        }

        $this->items[] = $item;
    }

    /**
     * Unset all items in registry
     *
     * @return void
     */
    public function unsetItems(): void
    {
        $this->items = null;
    }

    /**
     * Get all items from registry
     *
     * @return array
     */
    public function getItems(): array
    {
        if (null == $this->items) {
            $this->items = [];
        }

        return $this->items;
    }
}
