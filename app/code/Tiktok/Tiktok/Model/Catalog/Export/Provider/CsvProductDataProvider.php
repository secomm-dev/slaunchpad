<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider;

use Tiktok\Tiktok\Model\Catalog\Export\Provider\ProductCollectionBuilder;
use Tiktok\Tiktok\Model\ScopeManagerBuilder;
use Tiktok\Tiktok\Model\Catalog\Export\Mapper\CsvProductAttributeMapperFactory;
use Tiktok\Tiktok\Model\ResourceModel\Product\Collection\AddParentProductData;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\Modifiers\ModifierPool;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Store\Model\StoreManagerInterface;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\AbstractProductDataProvider;

/**
 * Product data provider for csv export
 */
class CsvProductDataProvider extends AbstractProductDataProvider
{
    /**
     * CsvProductDataProvider construct
     *
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Provider\ProductCollectionBuilder $productCollectionBuilder
     * @param \Tiktok\Tiktok\Model\ScopeManagerBuilder $scopeManagerBuilder
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Mapper\CsvProductAttributeMapperFactory $csvMapperFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Tiktok\Tiktok\Model\ResourceModel\Product\Collection\AddParentProductData $addParentProductData
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Provider\Modifiers\ModifierPool $modifierPool
     * @param array $attributesToSelect
     * @param int|null $websiteId
     * @param int $pageSize
     */
    public function __construct(
        ProductCollectionBuilder $productCollectionBuilder,
        ScopeManagerBuilder $scopeManagerBuilder,
        private readonly CsvProductAttributeMapperFactory $csvMapperFactory,
        StoreManagerInterface $storeManager,
        private readonly AddParentProductData $addParentProductData,
        private readonly ModifierPool $modifierPool,
        array $attributesToSelect = [],
        ?int $websiteId = null,
        int $pageSize = 1000
    ) {
        parent::__construct(
            $productCollectionBuilder,
            $scopeManagerBuilder,
            $storeManager,
            $attributesToSelect,
            $websiteId,
            $pageSize
        );
    }

    /**
     * @inheritdoc
     */
    public function getProductCollectionByPage(int $pageNumber): ?Collection
    {
        $this->setCurrentStore();
        $collection = $this->productCollectionBuilder->execute();
        $attributesToSelect = $this->getAttributesToSelect();
        if ($attributesToSelect) {
            $collection->addAttributeToSelect($attributesToSelect);
        }

        $collection->addWebsiteFilter($this->websiteId)
            ->addPriceData()
            ->addUrlRewrite()
            ->setPageSize($this->getPageSize())
            ->setCurPage($pageNumber);

        $collection->addMediaGalleryData();
        $this->addParentProductData->execute($collection);
        $this->modifierPool->execute($collection);
        return $collection;
    }

    /**
     * @inheritdoc
     */
    public function getAttributesToSelect(): array
    {
        if (!$this->attributesToSelect) {
            $scopeManager = $this->getScopeManager();
            $attributeMapper = $this->csvMapperFactory->create(['scopeManager' => $scopeManager]);
            $this->attributesToSelect = $attributeMapper->getAttributesToSelect();
        }

        return $this->attributesToSelect;
    }
}
