<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Api\Export;

use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Interface for Product Data Provider
 */
interface ProductDataProviderInterface
{
    /**
     * Retrieve next batch collection
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection|false
     */
    public function getNextBatch(): Collection|false;

    /**
     * Fetch a chunk of products for the given page number
     *
     * @param int $pageNumber
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection|null
     */
    public function getProductsByPage(int $pageNumber): ?Collection;
}
