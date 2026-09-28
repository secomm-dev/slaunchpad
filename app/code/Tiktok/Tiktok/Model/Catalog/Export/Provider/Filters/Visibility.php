<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider\Filters;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\Filters\FilterInterface;

/**
 * @inheritdoc
 */
class Visibility implements FilterInterface
{
    /**
     * @inheritdoc
     */
    public function applyFilter(Collection $collection): void
    {
        $collection->addAttributeToFilter(
            ProductInterface::VISIBILITY,
            ['neq' => ProductVisibility::VISIBILITY_NOT_VISIBLE]
        );
    }
}
