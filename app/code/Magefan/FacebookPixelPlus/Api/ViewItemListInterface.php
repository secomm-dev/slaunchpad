<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Api;

use Magento\Framework\Exception\NoSuchEntityException;

interface ViewItemListInterface
{
    /**
     * Get FB Pixel
     *
     * @param array $productItems
     * @return array
     * @throws NoSuchEntityException
     */
    public function get(array $productItems): array;
}
