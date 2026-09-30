<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Cron;

use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Tiktok\Tiktok\Model\Catalog\Export\Mapper\JsonProductAttributeMapperFactory;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\JsonProductDataProviderFactory;
use Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ResourceModel\Website\CollectionFactory;

/**
 * Cron to Sync Catalog Class
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
class SyncCatalogCron
{
    /**
     * SyncCatalogCron constructor
     *
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Mapper\JsonProductAttributeMapperFactory $attributeMapper
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Provider\JsonProductDataProviderFactory $dataProviderFactory
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager $syncFlagManager
     * @param \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder $tiktokApiClientBuilder
     * @param \Magento\Store\Model\ResourceModel\Website\CollectionFactory $websiteCollectionFactory
     */
    public function __construct(
        private readonly JsonProductAttributeMapperFactory $attributeMapper,
        private readonly JsonProductDataProviderFactory $dataProviderFactory,
        private readonly TiktokLogger $logger,
        private readonly SyncFlagManager $syncFlagManager,
        private readonly TiktokApiClientBuilder $tiktokApiClientBuilder,
        private readonly CollectionFactory $websiteCollectionFactory
    ) {
    }

    /**
     * Cronjob Description
     *
     * @param \Magento\Cron\Model\Schedule|\Magento\Framework\DataObject $schedule
     * @return void
     * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
     */
    public function execute($schedule): void
    {
        $websiteId = $this->getWebsiteIdByJobCode((string)$schedule->getJobCode());
        if ($websiteId === null) {
            $this->logger->info('Missing website id for delta catalog sync');
            return;
        }

        $websites = $this->websiteCollectionFactory->create()->addIdFilter($websiteId);
        /** @var \Magento\Store\Model\Website $website */
        foreach ($websites->getItems() as $website) {
            $this->logger->info('Starting sync cron for website: ' . $website->getName());
            $apiClient = $this->tiktokApiClientBuilder->create((int) $website->getId());
            $scopeManager = $apiClient->getScopeManager();
            $isConfigured = $scopeManager->getAccessToken()
                && $scopeManager->getAppSecret()
                && $scopeManager->getFeedId();
            if (!$isConfigured) {
                $this->logger->info('Skipping sync cron for website: ' . $website->getName());
                $this->logger->info('Tiktok account is not configured for current website');
                continue;
            }

            try {
                $uploadProducts = [];
                $deleteProducts = [];
                $uploadBatch = [];
                $attributeMapper = $this->attributeMapper->create(['scopeManager' => $scopeManager]);
                $attributeToSelect = $attributeMapper->getAttributesToSelect();
                $dataProvider = $this->dataProviderFactory->create(
                    [
                        'attributesToSelect' => $attributeToSelect,
                        'websiteId' => $website->getId()
                    ]
                );

                // Fetch product data page by page using the attribute mapper
                while ($products = $dataProvider->getNextBatch()) {
                    foreach ($products as $product) {
                        $this->validateProduct($product)
                            ? $uploadBatch[] = $product
                            : $deleteProducts[] = $product->getSku();
                    }

                    if ($uploadBatch) {
                        $productRows = $attributeMapper->mapPage($uploadBatch);
                        $uploadProducts += $productRows;
                    }
                }

                if (!$uploadProducts && !$deleteProducts) {
                    $this->logger->info('No products found');
                    continue;
                }

                if ($uploadProducts) {
                    $this->logger->info('Sending products to TikTok');
                    $this->updateProducts($uploadProducts, (int)$website->getId());
                }

                if ($deleteProducts) {
                    $this->logger->info('Deleting products from TikTok');
                    $this->removeProducts($deleteProducts, (int)$website->getId());
                }

                $this->logger->info('Product delta sync complete');
                $this->syncFlagManager->updateLastSyncTime((int)$website->getId());
                $skippedProducts = $attributeMapper->getSkippedProducts();
                if ($skippedProducts > 0) {
                    $this->logger->info("Skipped $skippedProducts products due to missing required data");
                }
            } catch (Exception $e) {
                $this->logger->critical('Error during export: ' . $e->getMessage());
            }
        }
    }

    /**
     * Send updated information to TikTok
     *
     * @param array $products
     * @param int $websiteId
     * @return void
     */
    private function updateProducts(array $products, int $websiteId): void
    {
        $apiClient = $this->tiktokApiClientBuilder->create($websiteId);
        $profile = $apiClient->getProfile();
        if (!isset($profile['bc_id'], $profile['catalog_id'])) {
            $this->logger->info('Missing bc_id or catalog_id in profile');
            return;
        }

        try {
            $apiClient->uploadProductsJson(
                $profile['bc_id'],
                $profile['catalog_id'],
                $products,
                $apiClient->getScopeManager()->getFeedId()
            );
        } catch (GuzzleException|\Exception $e) {
            $this->logger->critical('Error during uploading products: ' . $e->getMessage());
        }
    }

    /**
     * Remove products from TikTok
     *
     * @param array $products
     * @param int $websiteId
     * @return void
     */
    private function removeProducts(array $products, int $websiteId): void
    {
        $apiClient = $this->tiktokApiClientBuilder->create($websiteId);
        $profile = $apiClient->getProfile();
        if (!isset($profile['bc_id'], $profile['catalog_id'])) {
            $this->logger->info('Missing bc_id or catalog_id in profile');
            return;
        }

        try {
            $apiClient->removeProducts(
                $profile['bc_id'],
                $profile['catalog_id'],
                $products,
                $apiClient->getScopeManager()->getFeedId()
            );
        } catch (\Exception $e) {
            $this->logger->critical('Error during removing products: ' . $e->getMessage());
        } catch (GuzzleException $e) {
            $this->logger->critical('Error during removing products: ' . $e->getMessage());
        }
    }

    /**
     * Validate product data to identify we should remove him after changes on update
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return bool
     */
    public function validateProduct(ProductInterface $product): bool
    {
        return !(!$product->getStatus() || !$product->getIsSalable());
    }

    /**
     * Retrieve website id
     *
     * @param string $jobCode
     * @return int|null
     */
    private function getWebsiteIdByJobCode(string $jobCode): ?int
    {
        $websiteId = null;
        $idPos = strrpos($jobCode, '_');
        if ($idPos !== false) {
            $websiteId = (int)substr($jobCode, $idPos + 1);
        }

        return $websiteId;
    }
}
