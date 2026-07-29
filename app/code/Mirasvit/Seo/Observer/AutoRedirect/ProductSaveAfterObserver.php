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



namespace Mirasvit\Seo\Observer\AutoRedirect;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Model\Config;
use Mirasvit\Seo\Service\AutoRedirect\ProductUrlProvider;
use Mirasvit\Seo\Service\AutoRedirect\RuleManager;
use Mirasvit\Seo\Service\AutoRedirect\TargetResolver;

/**
 * On product save: when auto-redirect-on-disable is on, generate a redirect for a disabled product
 * and remove the auto-rule when the product is (re-)enabled (AC 1, AC 7).
 */
class ProductSaveAfterObserver implements ObserverInterface
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
     * @var TargetResolver
     */
    private $targetResolver;

    /**
     * @var RuleManager
     */
    private $ruleManager;

    /**
     * Product ids already handled in this request — the save dispatches this event several times
     * (per store view / reindex pass), and we only want to rebuild the rules once.
     *
     * @var array<int, bool>
     */
    private $processed = [];

    public function __construct(
        Config                $config,
        StoreManagerInterface $storeManager,
        ProductUrlProvider    $urlProvider,
        TargetResolver        $targetResolver,
        RuleManager           $ruleManager
    ) {
        $this->config         = $config;
        $this->storeManager   = $storeManager;
        $this->urlProvider    = $urlProvider;
        $this->targetResolver = $targetResolver;
        $this->ruleManager    = $ruleManager;
    }

    public function execute(Observer $observer): void
    {
        /** @var ProductInterface $product */
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        $productId = (int)$product->getId();
        if (isset($this->processed[$productId])) {
            return;
        }
        $this->processed[$productId] = true;

        $isDisabled = (int)$product->getStatus() === Status::STATUS_DISABLED;

        if (!$isDisabled) {
            // Re-enabled: product is reachable again — always remove its auto-rules (AC 7).
            $this->ruleManager->removeForProduct($productId);
            return;
        }

        // Disabled: only touch rules if the feature is active for at least one store.
        // When the feature is globally off, leave existing auto-rules intact so they are not
        // silently wiped the next time a disabled product is saved after the feature is turned off.
        $storeIds      = $this->getStoreIds($product);
        $featureActive = false;
        foreach ($storeIds as $storeId) {
            if ($this->config->isAutoRedirectOnDisableEnabled($storeId)) {
                $featureActive = true;
                break;
            }
        }

        if (!$featureActive) {
            return;
        }

        $this->ruleManager->removeForProduct($productId);

        $categoryIds = $this->targetResolver->getProductCategoryIds($productId);

        foreach ($storeIds as $storeId) {
            if (!$this->config->isAutoRedirectOnDisableEnabled($storeId)) {
                continue;
            }

            $requestPaths = $this->urlProvider->getRequestPaths($productId, $storeId);
            $target       = $this->targetResolver->resolve($categoryIds, $storeId);

            $this->ruleManager->generate($productId, $storeId, $requestPaths, $target);
        }
    }

    /**
     * Store views the product belongs to (skip the admin store 0).
     *
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
