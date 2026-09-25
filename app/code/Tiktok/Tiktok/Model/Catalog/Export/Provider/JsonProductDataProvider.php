<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider;

use Tiktok\Tiktok\Model\Catalog\Export\Mapper\JsonProductAttributeMapperFactory;
use Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\ProductCollectionBuilder;
use Tiktok\Tiktok\Model\ScopeManagerBuilder;
use Tiktok\Tiktok\Model\ResourceModel\Product\Collection\AddParentProductData;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\Modifiers\ModifierPool;
use DateTime;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\AbstractProductDataProvider;

/**
 * Product data provider for json export
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class JsonProductDataProvider extends AbstractProductDataProvider
{
    /**
     * JsonProductDataProvider construct
     *
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Provider\ProductCollectionBuilder $productCollectionBuilder
     * @param \Tiktok\Tiktok\Model\ScopeManagerBuilder $scopeManagerBuilder
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Mapper\JsonProductAttributeMapperFactory $jsonMapperFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager $syncFlagManager
     * @param \Tiktok\Tiktok\Model\ResourceModel\Product\Collection\AddParentProductData $addParentProductData
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Provider\Modifiers\ModifierPool $modifierPool
     * @param array $attributesToSelect
     * @param int|null $websiteId
     * @param int $pageSize
     */
    public function __construct(
        ProductCollectionBuilder $productCollectionBuilder,
        ScopeManagerBuilder $scopeManagerBuilder,
        private readonly JsonProductAttributeMapperFactory $jsonMapperFactory,
        StoreManagerInterface $storeManager,
        private readonly SyncFlagManager $syncFlagManager,
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

        $collection->setPageSize($this->getPageSize())
            ->setCurPage($pageNumber)
            ->addAttributeToSelect($this->getAttributesToSelect())
            ->addWebsiteFilter($this->getWebsiteId())
            ->addUrlRewrite()
            ->addAttributeToSelect(ProductInterface::STATUS)
            ->addPriceData();

        $lastSyncTime = $this->getSyncTimeBasedOnFrequency();
        if ($lastSyncTime) {
            $collection->addAttributeToFilter(ProductInterface::UPDATED_AT, ['from' => $lastSyncTime]);
        }

        $collection->addMediaGalleryData();
        $this->addParentProductData->execute($collection);
        $this->modifierPool->execute($collection);
        return $collection;
    }

    /**
     * Get the time based on the cron expression (sync frequency) or last sync time.
     *
     * @return string|null
     * @throws \DateMalformedStringException
     */
    private function getSyncTimeBasedOnFrequency(): ?string
    {
        // Define sync intervals and adjustments based on cron expressions
        $cronIntervals = [
            '0 * * * *' => ['interval' => 3600, 'modify' => '-1 hour'],
            '0 0 * * *' => ['interval' => 86400, 'modify' => '-1 day'],
            '0 0 * * 0' => ['interval' => 604800, 'modify' => '-1 week']
        ];

        // Retrieve the cron expression and last sync time
        $scopeManager = $this->getScopeManager();
        $cronExpression = $scopeManager->getSyncFrequency();
        $currentTime = new DateTime();

        // Fetch the last sync time
        $lastSyncTime = $this->syncFlagManager->getLastSyncTime($this->getWebsiteId());

        // Validate if cron expression is supported
        if (!isset($cronIntervals[$cronExpression])) {
            return null; // Unsupported cron expression
        }

        if ($lastSyncTime) {
            $lastSyncDateTime = new DateTime($lastSyncTime);
            $timeDifference = $currentTime->getTimestamp() - $lastSyncDateTime->getTimestamp();

            // Check if the time difference exceeds the expected interval
            if ($timeDifference > $cronIntervals[$cronExpression]['interval']) {
                return $lastSyncTime;
            }
        }

        // Calculate the time adjustment based on cron expression
        return $currentTime->modify($cronIntervals[$cronExpression]['modify'])->format('Y-m-d H:i:s');
    }

    /**
     * @inheritdoc
     */
    public function getAttributesToSelect(): array
    {
        if (!$this->attributesToSelect) {
            $scopeManager = $this->getScopeManager();
            $attributeMapper = $this->jsonMapperFactory->create(['scopeManager' => $scopeManager]);
            $this->attributesToSelect = $attributeMapper->getAttributesToSelect();
        }

        return $this->attributesToSelect;
    }
}
