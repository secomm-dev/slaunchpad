<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider\Modifiers;

use Magento\Catalog\Api\Data\ProductInterface;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\Modifiers\ModifierInterface;

class AddProductUrl implements ModifierInterface
{
    /**
     * @inheritdoc
     */
    public function modify(ProductInterface $product): void
    {
        $product->setData('product_url', $product->getProductUrl());
    }
}
