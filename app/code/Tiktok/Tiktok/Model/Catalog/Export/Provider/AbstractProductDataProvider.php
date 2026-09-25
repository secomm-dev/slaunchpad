<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Tiktok\Tiktok\Api\Export\ProductDataProviderInterface;
use Tiktok\Tiktok\Model\Config\ScopeManager;
use Tiktok\Tiktok\Model\ScopeManagerBuilder;

/**
 * Product Data Provide Abstract Class
 */
abstract class AbstractProductDataProvider implements ProductDataProviderInterface
{
    /**
     * @var int|null
     */
    protected ?int $totalPages = null;

    /**
     * @var int
     */
    protected int $currentPage = 1;

    /**
     * @var \Tiktok\Tiktok\Model\Config\ScopeManager|null
     */
    private ?ScopeManager $scopeManager = null;

    /**
     * AbstractProductDataProvider constructor
     *
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Provider\ProductCollectionBuilder $productCollectionBuilder
     * @param \Tiktok\Tiktok\Model\ScopeManagerBuilder $scopeManagerBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param array $attributesToSelect
     * @param int|null $websiteId
     * @param int $pageSize
     */
    public function __construct(
        protected ProductCollectionBuilder $productCollectionBuilder,
        protected ScopeManagerBuilder $scopeManagerBuilder,
        protected StoreManagerInterface $storeManager,
        protected array $attributesToSelect = [],
        protected ?int $websiteId = null,
        protected int $pageSize = 1000
    ) {
    }

    /**
     * Retrieve next batch collection
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection|false
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getNextBatch(): Collection|false
    {
        $collection = $this->getProductsByPage($this->currentPage);
        if ($collection === null || $collection->count() === 0) {
            return false;
        }

        $this->currentPage++;
        return $collection;
    }

    /**
     * Fetch a chunk of products for the given page number
     *
     * @param int $pageNumber
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getProductsByPage(int $pageNumber): ?Collection
    {
        $collection = $this->getProductCollectionByPage($pageNumber);

        if (empty($this->totalPages)) {
            $this->totalPages = $collection->getLastPageNumber();
        }

        // Check if requested page number is out of bounds
        if ($pageNumber > $this->totalPages) {
            return null;
        }

        return $collection;
    }

    /**
     * Get the product collection with attributes to select
     *
     * @param int $pageNumber
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    abstract public function getProductCollectionByPage(int $pageNumber): ?Collection;

    /**
     * Retrieve array of required attributes
     *
     * @return array
     */
    abstract public function getAttributesToSelect(): array;

    /**
     * Get current progress information
     *
     * @return array
     */
    public function getProgress(): array
    {
        return [
            'currentPage' => $this->currentPage,
            'totalPages' => $this->totalPages,
            'isComplete' => $this->totalPages !== null && $this->currentPage > $this->totalPages
        ];
    }

    /**
     * Retrieve page size
     *
     * @return int
     */
    protected function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Retrieve scope manager
     *
     * @return \Tiktok\Tiktok\Model\Config\ScopeManager
     */
    protected function getScopeManager(): ScopeManager
    {
        if (null === $this->scopeManager) {
            $this->scopeManager = $this->scopeManagerBuilder->create($this->getWebsiteId());
        }

        return $this->scopeManager;
    }

    /**
     * Retrieve website ID
     *
     * @return int|null
     */
    protected function getWebsiteId(): ?int
    {
        if (null === $this->websiteId) {
            $this->websiteId = $this->storeManager->getDefaultStoreView()->getWebsiteId();
        }

        return $this->websiteId;
    }

    /**
     * Set current store id to global store manager
     *
     * @return void
     */
    protected function setCurrentStore(): void
    {
        $store = $this->storeManager->getStoreByWebsiteId($this->getWebsiteId());
        if ($store) {
            $storeId = array_shift($store);
            $this->storeManager->setCurrentStore($storeId);
        }
    }
}
