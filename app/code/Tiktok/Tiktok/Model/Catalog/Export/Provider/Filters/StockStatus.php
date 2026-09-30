<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider\Filters;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status as LegacyStatusResource;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\Filters\FilterInterface;

/**
 * @inheritdoc
 */
class StockStatus implements FilterInterface
{
    /**
     * StockStatus constructor
     *
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\Status $resourceStatus
     * @param bool $addInStockFilter
     */
    public function __construct(
        private readonly LegacyStatusResource $resourceStatus,
        private readonly bool $addInStockFilter = false
    ) {
    }

    /**
     * @inheritdoc
     */
    public function applyFilter(Collection $collection): void
    {
        $this->resourceStatus->addStockDataToCollection($collection, $this->addInStockFilter);
    }
}
