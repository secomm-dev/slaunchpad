<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\Filters\FilterPool;

/**
 * Retrieve base collection with applied filters
 */
class ProductCollectionBuilder
{
    /**
     * ProductCollectionBuilder construct
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Provider\Filters\FilterPool|null $filterPool
     */
    public function __construct(
        private readonly ProductCollectionFactory $collectionFactory,
        private readonly ?FilterPool $filterPool = null
    ) {
    }

    /**
     * Retrieve product collection
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function execute(): Collection
    {
        $collection = $this->collectionFactory->create();
        $this->filterPool?->execute($collection);
        return $collection;
    }
}
