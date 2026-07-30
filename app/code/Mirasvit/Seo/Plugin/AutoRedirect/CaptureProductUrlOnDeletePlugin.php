<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Seo\Plugin\AutoRedirect;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Model\Config;
use Mirasvit\Seo\Service\AutoRedirect\ProductUrlProvider;
use Mirasvit\Seo\Service\AutoRedirect\RemovedProductUrlStorage;
use Mirasvit\Seo\Service\AutoRedirect\TargetResolver;

/**
 * Captures a product's storefront URL(s) BEFORE deletion.
 *
 * Magento's core ProductProcessUrlRewriteRemovingObserver removes the product's url_rewrite rows on
 * the same `catalog_product_delete_before` event, and observer order within one event is not
 * guaranteed — so a delete-before observer can read after the rewrites are already gone. A `before`
 * plugin on the repository delete runs strictly before the whole delete chain, so the SEO-friendly
 * rewrite paths are still present when we read them (AC 2, AC 5 for deleted products).
 */
class CaptureProductUrlOnDeletePlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductUrlProvider
     */
    private $urlProvider;

    /**
     * @var RemovedProductUrlStorage
     */
    private $storage;

    /**
     * @var TargetResolver
     */
    private $targetResolver;

    public function __construct(
        Config                   $config,
        StoreManagerInterface    $storeManager,
        ProductUrlProvider       $urlProvider,
        RemovedProductUrlStorage $storage,
        TargetResolver           $targetResolver
    ) {
        $this->config         = $config;
        $this->storeManager   = $storeManager;
        $this->urlProvider    = $urlProvider;
        $this->storage        = $storage;
        $this->targetResolver = $targetResolver;
    }

    /**
     * @return ProductInterface[]
     */
    public function beforeDelete(ProductRepositoryInterface $subject, ProductInterface $product): array
    {
        $this->capture($product);

        return [$product];
    }

    /**
     * @return string[]
     */
    public function beforeDeleteById(ProductRepositoryInterface $subject, $sku): array
    {
        try {
            $product = $subject->get((string)$sku);
            $this->capture($product);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // intentional no-op: nothing to capture for a non-existent SKU.
        }

        return [$sku];
    }

    private function capture(ProductInterface $product): void
    {
        if (!$product->getId()) {
            return;
        }

        // Resolve store list once; skip all DB work if no store has the feature on.
        $storeIds        = $this->getStoreIds($product);
        $enabledStoreIds = array_filter($storeIds, function ($storeId) {
            return $this->config->isAutoRedirectOnDeleteEnabled($storeId);
        });

        if (!$enabledStoreIds) {
            return;
        }

        $productId = (int)$product->getId();

        // Stash category ids (read from the committed link table) so the delete-after observer can
        // resolve the target after the product and its category links are gone.
        $product->setData(
            'mst_seo_deleted_category_ids',
            $this->targetResolver->getProductCategoryIds($productId)
        );

        $this->storage->clear($productId);

        foreach ($enabledStoreIds as $storeId) {
            $requestPaths = $this->urlProvider->getRequestPaths($productId, $storeId);
            $this->storage->capture($productId, $storeId, $requestPaths);
        }
    }

    /**
     * @return int[]
     */
    private function getStoreIds(ProductInterface $product): array
    {
        $storeIds = [];
        if ($product instanceof \Magento\Catalog\Model\Product) {
            $storeIds = array_map('intval', $product->getStoreIds());
        }

        if (!$storeIds) {
            foreach ($this->storeManager->getStores() as $store) {
                $storeIds[] = (int)$store->getId();
            }
        }

        return array_values(array_filter($storeIds, function ($id) {
            return $id > 0;
        }));
    }
}
