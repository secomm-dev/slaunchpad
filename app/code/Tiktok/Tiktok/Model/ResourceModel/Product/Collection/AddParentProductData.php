<?php

declare(strict_types=1);

namespace Tiktok\Tiktok\Model\ResourceModel\Product\Collection;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Tiktok\Tiktok\Model\ResourceModel\Product\ParentProductProvider;

class AddParentProductData
{
    /**
     * AddParentProductData constructor
     *
     * @param \Tiktok\Tiktok\Model\ResourceModel\Product\ParentProductProvider $parentProductProvider
     */
    public function __construct(
        private readonly ParentProductProvider $parentProductProvider,
    ) {
    }

    /**
     * Adding parent product's data to product
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @return void
     */
    public function execute($collection)
    {
        $products = $collection->getItems();

        $childIds = array_map(
            function ($product) {
                return (int) $product->getData('entity_id');
            },
            $products
        );

        $parents = $this->parentProductProvider->getParentDataByChildIds($childIds);

        $childSkus = array_keys($parents);

        foreach ($products as $product) {
            if (in_array($product->getId(), $childSkus)) {
                $parentData ['parent_sku'] = $parents[$product->getId()]['parent_sku'];
                $parentData ['parent_type'] = $parents[$product->getId()]['parent_type'];
                $product->setData('parent_data', $parentData);
            }
        }
    }
}
