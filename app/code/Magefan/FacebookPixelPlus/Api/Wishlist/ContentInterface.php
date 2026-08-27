<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Api\Wishlist;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Wishlist\Model\Item;

interface ContentInterface
{
    /**
     * Get wishlist content
     *
     * @param Item $wishlistItem
     * @return array
     * @throws NoSuchEntityException
     */
    public function get(Item $wishlistItem): array;
}
