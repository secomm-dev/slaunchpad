<?php
namespace Tiktok\Tiktok\Model\Catalog\Export\Provider\Modifiers;

use Magento\Catalog\Api\Data\ProductInterface;

interface ModifierInterface
{
    /**
     * Add/Update data
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return void
     */
    public function modify(ProductInterface $product): void;
}
