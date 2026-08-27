<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Wishlist\Model\Item;

interface AddToWishlistInterface
{
    /**
     * Get FB pixel data
     *
     * @param Item $wishlistItem
     * @return array
     * @throws NoSuchEntityException
     */
    public function get(Item $wishlistItem): array;
}
