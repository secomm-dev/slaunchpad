<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider\Filters;

use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Init interface methods
 */
interface FilterInterface
{
    /**
     * Add filter to product collection
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @return void
     */
    public function applyFilter(Collection $collection): void;
}
